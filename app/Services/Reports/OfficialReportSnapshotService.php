<?php

namespace App\Services\Reports;

use App\Models\OfficialReportSnapshot;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

final class OfficialReportSnapshotService
{
    public const SCHEMA_VERSION = 1;

    public function __construct(
        private readonly LibraryReportService $reports,
        private readonly ReportRegistry $registry,
        private readonly AuditLogger $audit,
        private readonly ReportFileIntegrity $files,
    ) {}

    public function create(
        string $type,
        ReportFilters $filters,
        User $actor,
        ?string $revisionNote = null,
    ): OfficialReportSnapshot {
        $this->registry->official($type);
        $lineageId = (string) Str::uuid();
        DB::table('official_report_lineages')->insertOrIgnore([
            'lineage_id' => $lineageId,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);

        return $this->persist(
            type: $type,
            filters: $filters,
            actor: $actor,
            lineageId: $lineageId,
            revision: 1,
            previous: null,
            revisionNote: $revisionNote,
        );
    }

    public function revise(
        OfficialReportSnapshot $previous,
        User $actor,
        string $revisionNote,
    ): OfficialReportSnapshot {
        if (! in_array($previous->status, ['approved', 'rejected', 'superseded'], true)) {
            throw new RuntimeException('Only an approved, superseded or rejected snapshot can start a new revision.');
        }
        $this->assertIntegrity($previous);

        $repeatableRead = DB::connection()->getDriverName() === 'pgsql';

        return DB::transaction(function () use ($previous, $actor, $revisionNote, $repeatableRead): OfficialReportSnapshot {
            // This must be the first statement in the transaction. Revision
            // numbering and every source query then share one PostgreSQL
            // snapshot while the lineage row serializes competing writers.
            if ($repeatableRead) {
                DB::statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ');
            }
            $this->lockLineage($previous->lineage_id);
            $latest = OfficialReportSnapshot::query()
                ->where('lineage_id', $previous->lineage_id)
                ->orderByDesc('revision')
                ->lockForUpdate()
                ->firstOrFail();
            if ($latest->getKey() !== $previous->getKey()) {
                throw new RuntimeException('A newer report revision already exists for this lineage.');
            }
            $filters = $this->filtersFromSnapshot($previous);

            return $this->persist(
                type: $previous->report_type,
                filters: $filters,
                actor: $actor,
                lineageId: $previous->lineage_id,
                revision: $latest->revision + 1,
                previous: $previous,
                revisionNote: $revisionNote,
            );
        });
    }

    public function submit(OfficialReportSnapshot $snapshot, User $actor): OfficialReportSnapshot
    {
        return $this->transition($snapshot, $actor, 'generated', [
            'status' => 'pending_review',
            'submitted_by' => $actor->getKey(),
            'submitted_at' => now('UTC'),
            'decision_note' => null,
        ], 'official_report.submitted');
    }

    public function approve(OfficialReportSnapshot $snapshot, User $actor, ?string $note = null): OfficialReportSnapshot
    {
        return DB::transaction(function () use ($snapshot, $actor, $note): OfficialReportSnapshot {
            $this->lockLineage($snapshot->lineage_id);
            $locked = OfficialReportSnapshot::query()->lockForUpdate()->findOrFail($snapshot->getKey());
            if ($locked->status !== 'pending_review') {
                throw new RuntimeException("Official report cannot transition from {$locked->status}; expected pending_review.");
            }
            $this->assertIntegrity($locked);

            // The partial unique index allows only one approved row per
            // lineage. Supersede the current winner before promoting the new
            // revision; the enclosing transaction makes both changes atomic.
            $olderApproved = OfficialReportSnapshot::query()
                ->where('lineage_id', $locked->lineage_id)
                ->where('id', '!=', $locked->getKey())
                ->where('status', 'approved')
                ->lockForUpdate()
                ->get();
            foreach ($olderApproved as $older) {
                OfficialReportSnapshot::query()->whereKey($older->getKey())->update([
                    'status' => 'superseded',
                    'superseded_by_snapshot_id' => $locked->getKey(),
                    'updated_at' => now('UTC'),
                ]);
                $this->audit->logRequired(
                    actionType: 'official_report.superseded',
                    entityType: 'official_report_snapshot',
                    entityId: $older->public_id,
                    oldValues: $this->auditValues($older),
                    newValues: $this->auditValues($older->fresh()),
                    scope: 'operational',
                    actor: $actor,
                );
            }

            $approved = $this->transition($snapshot, $actor, 'pending_review', [
                'status' => 'approved',
                'approved_by' => $actor->getKey(),
                'approved_at' => now('UTC'),
                'rejected_by' => null,
                'rejected_at' => null,
                'decision_note' => $note,
            ], 'official_report.approved');

            return $approved->fresh();
        });
    }

    public function reject(OfficialReportSnapshot $snapshot, User $actor, string $note): OfficialReportSnapshot
    {
        return $this->transition($snapshot, $actor, 'pending_review', [
            'status' => 'rejected',
            'rejected_by' => $actor->getKey(),
            'rejected_at' => now('UTC'),
            'approved_by' => null,
            'approved_at' => null,
            'decision_note' => $note,
        ], 'official_report.rejected');
    }

    public function deleteDraft(OfficialReportSnapshot $snapshot, User $actor): void
    {
        if (! in_array($snapshot->status, ['draft', 'generated'], true)) {
            throw new RuntimeException('Only a draft official report snapshot can be deleted.');
        }

        $this->assertIntegrity($snapshot);
        $archive = OfficialReportSnapshot::canonicalJson($snapshot->source_data);
        $disk = Storage::disk($snapshot->archive_disk);
        $deleted = false;
        try {
            DB::transaction(function () use ($snapshot, $actor, $disk, &$deleted): void {
                $locked = OfficialReportSnapshot::query()->lockForUpdate()->findOrFail($snapshot->getKey());
                if (! in_array($locked->status, ['draft', 'generated'], true)) {
                    throw new RuntimeException('Only a draft official report snapshot can be deleted.');
                }
                $this->assertIntegrity($locked);
                if (! $disk->delete($locked->archive_path) && $disk->exists($locked->archive_path)) {
                    throw new RuntimeException('Unable to remove the draft report source archive.');
                }
                $deleted = true;
                $this->audit->logRequired(
                    actionType: 'official_report.draft_deleted',
                    entityType: 'official_report_snapshot',
                    entityId: $locked->public_id,
                    oldValues: $this->auditValues($locked),
                    scope: 'operational',
                    actor: $actor,
                );
                $locked->delete();
            });
        } catch (Throwable $exception) {
            // Compensate a failed audit/DB commit after the file operation so
            // a live row can never be left pointing at a missing archive.
            if ($deleted
                && OfficialReportSnapshot::query()->whereKey($snapshot->getKey())->exists()
                && ! $disk->exists($snapshot->archive_path)
                && ! $disk->put($snapshot->archive_path, $archive)) {
                report(new RuntimeException('Critical: unable to restore a report archive after draft deletion rollback.', previous: $exception));
            }
            throw $exception;
        }
    }

    public function archive(OfficialReportSnapshot $snapshot, User $actor): OfficialReportSnapshot
    {
        if (! in_array($snapshot->status, ['approved', 'superseded'], true)) {
            throw new RuntimeException('Only an approved or superseded report can be archived.');
        }

        return DB::transaction(function () use ($snapshot, $actor): OfficialReportSnapshot {
            $this->lockLineage($snapshot->lineage_id);
            $locked = OfficialReportSnapshot::query()->lockForUpdate()->findOrFail($snapshot->getKey());
            if (! in_array($locked->status, ['approved', 'superseded'], true)) {
                throw new RuntimeException('Only an approved or superseded report can be archived.');
            }
            $this->assertIntegrity($locked);
            $before = $this->auditValues($locked);
            OfficialReportSnapshot::query()->whereKey($locked->getKey())->update([
                'status' => 'archived',
                'archived_by' => $actor->getKey(),
                'archived_at' => now('UTC'),
                'updated_at' => now('UTC'),
            ]);
            $fresh = $locked->fresh();
            $this->audit->logRequired(
                actionType: 'official_report.archived',
                entityType: 'official_report_snapshot',
                entityId: $fresh->public_id,
                oldValues: $before,
                newValues: $this->auditValues($fresh),
                scope: 'operational',
                actor: $actor,
            );

            return $fresh;
        });
    }

    public function assertIntegrity(OfficialReportSnapshot $snapshot): void
    {
        if (! $snapshot->sourceIsIntact()) {
            throw new RuntimeException('Official report source hash verification failed.');
        }

        $this->files->assert(
            Storage::disk($snapshot->archive_disk),
            $snapshot->archive_path,
            (string) $snapshot->source_hash,
            (int) $snapshot->archive_size,
        );
    }

    private function persist(
        string $type,
        ReportFilters $filters,
        User $actor,
        string $lineageId,
        int $revision,
        ?OfficialReportSnapshot $previous,
        ?string $revisionNote,
    ): OfficialReportSnapshot {
        $publicId = (string) Str::uuid();
        $reportNumber = $this->reportNumber($type, $lineageId, $revision);
        $live = $this->consistentDataset($type, $filters);
        $payload = [
            'schema_version' => self::SCHEMA_VERSION,
            'report_type' => $type,
            'report_number' => $reportNumber,
            'revision' => $revision,
            'report_title' => $this->reports->title($type),
            'filters' => $filters->toArray(),
            'period' => [
                'from_utc' => $filters->from->toIso8601String(),
                'to_utc' => $filters->to->toIso8601String(),
                'timezone' => (string) config('app.library_timezone', 'Asia/Almaty'),
            ],
            'metrics' => $live['metrics'],
            'columns' => $live['columns'],
            'rows' => $live['rows'],
            'breakdowns' => $live['breakdowns'],
            'generated_at_utc' => now('UTC')->toIso8601String(),
        ];
        $json = OfficialReportSnapshot::canonicalJson($payload);
        $hash = hash('sha256', $json);
        $archivePath = sprintf(
            'official-reports/snapshots/%s/revision-%04d-%s.json',
            $lineageId,
            $revision,
            $publicId,
        );
        $disk = Storage::disk('local');
        if (! $disk->put($archivePath, $json)) {
            throw new RuntimeException('Unable to store the immutable report source archive.');
        }

        try {
            return DB::transaction(function () use ($type, $filters, $actor, $lineageId, $revision, $previous, $revisionNote, $publicId, $reportNumber, $payload, $hash, $archivePath, $json): OfficialReportSnapshot {
                $snapshot = OfficialReportSnapshot::query()->create([
                    'public_id' => $publicId,
                    'report_number' => $reportNumber,
                    'lineage_id' => $lineageId,
                    'revision' => $revision,
                    'previous_snapshot_id' => $previous?->getKey(),
                    'report_type' => $type,
                    'period_preset' => $filters->preset,
                    'period_from' => $filters->from,
                    'period_to' => $filters->to,
                    'filters' => $filters->toArray(),
                    'source_data' => $payload,
                    'source_hash' => $hash,
                    'schema_version' => self::SCHEMA_VERSION,
                    'status' => 'generated',
                    'revision_note' => $revisionNote,
                    'archive_disk' => 'local',
                    'archive_path' => $archivePath,
                    'archive_hash' => $hash,
                    'archive_size' => strlen($json),
                    'created_by' => $actor->getKey(),
                    'retention_until' => now('UTC')->addYears(max(1, (int) config('library.reports.official_retention_years', 10))),
                ]);
                $this->audit->logRequired(
                    actionType: $revision === 1 ? 'official_report.snapshot_created' : 'official_report.revision_created',
                    entityType: 'official_report_snapshot',
                    entityId: $snapshot->public_id,
                    newValues: $this->auditValues($snapshot),
                    scope: 'operational',
                    actor: $actor,
                );

                return $snapshot->fresh();
            });
        } catch (Throwable $exception) {
            $disk->delete($archivePath);
            throw $exception;
        }
    }

    /** @param array<string, mixed> $changes */
    private function transition(
        OfficialReportSnapshot $snapshot,
        User $actor,
        string $expectedStatus,
        array $changes,
        string $action,
    ): OfficialReportSnapshot {
        return DB::transaction(function () use ($snapshot, $actor, $expectedStatus, $changes, $action): OfficialReportSnapshot {
            $locked = OfficialReportSnapshot::query()->lockForUpdate()->findOrFail($snapshot->getKey());
            if ($locked->status !== $expectedStatus) {
                throw new RuntimeException("Official report cannot transition from {$locked->status}; expected {$expectedStatus}.");
            }
            $before = $this->auditValues($locked);
            $locked->update($changes);
            $this->audit->logRequired(
                actionType: $action,
                entityType: 'official_report_snapshot',
                entityId: $locked->public_id,
                oldValues: $before,
                newValues: $this->auditValues($locked->fresh()),
                scope: 'operational',
                actor: $actor,
            );

            return $locked->fresh();
        });
    }

    private function filtersFromSnapshot(OfficialReportSnapshot $snapshot): ReportFilters
    {
        $filters = $snapshot->filters;

        return new ReportFilters(
            preset: $snapshot->period_preset,
            from: Carbon::instance($snapshot->period_from)->toMutable(),
            to: Carbon::instance($snapshot->period_to)->toMutable(),
            branchId: isset($filters['branch_id']) ? (int) $filters['branch_id'] : null,
            fundId: isset($filters['fund_id']) ? (int) $filters['fund_id'] : null,
            resourceType: $filters['resource_type'] ?? null,
            userSegment: $filters['user_segment'] ?? null,
            language: $filters['language'] ?? null,
            udc: $filters['udc'] ?? null,
            status: $filters['status'] ?? null,
            subject: $filters['subject'] ?? null,
            accessType: $filters['access_type'] ?? null,
            operation: $filters['operation'] ?? null,
            acquisitionSource: $filters['acquisition_source'] ?? null,
        );
    }

    /** @return array<string, mixed> */
    private function auditValues(OfficialReportSnapshot $snapshot): array
    {
        return [
            'public_id' => $snapshot->public_id,
            'report_number' => $snapshot->report_number,
            'lineage_id' => $snapshot->lineage_id,
            'revision' => $snapshot->revision,
            'report_type' => $snapshot->report_type,
            'status' => $snapshot->status,
            'source_hash' => $snapshot->source_hash,
        ];
    }

    private function reportNumber(string $type, string $lineageId, int $revision): string
    {
        $prefix = match ($type) {
            'acquisitions' => 'ACQ',
            'fund-usage' => 'FUND',
            'users' => 'USR',
            'electronic-resources' => 'ERES',
            default => 'RPT',
        };

        return sprintf('%s-%s-%s-R%03d', $prefix, now('UTC')->format('Y'), strtoupper(substr(str_replace('-', '', $lineageId), 0, 8)), $revision);
    }

    private function lockLineage(string $lineageId): void
    {
        DB::table('official_report_lineages')->insertOrIgnore([
            'lineage_id' => $lineageId,
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
        DB::table('official_report_lineages')->where('lineage_id', $lineageId)->lockForUpdate()->first();
    }

    /** @return array<string, mixed> */
    private function consistentDataset(string $type, ReportFilters $filters): array
    {
        $connection = DB::connection();
        $driver = $connection->getDriverName();
        $ownsTransaction = $connection->transactionLevel() === 0;

        if (! $ownsTransaction) {
            return $this->reports->dataset($type, $filters);
        }

        $connection->beginTransaction();
        try {
            if ($driver === 'pgsql') {
                $connection->statement('SET TRANSACTION ISOLATION LEVEL REPEATABLE READ READ ONLY');
            }
            $report = $this->reports->dataset($type, $filters);
            $connection->commit();

            return $report;
        } catch (Throwable $exception) {
            $connection->rollBack();
            throw $exception;
        }
    }
}
