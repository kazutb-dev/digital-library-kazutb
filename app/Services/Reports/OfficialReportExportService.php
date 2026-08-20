<?php

namespace App\Services\Reports;

use App\Models\OfficialReportSnapshot;
use App\Models\ReportExportJob;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

final class OfficialReportExportService
{
    public function __construct(
        private readonly ReportRegistry $registry,
        private readonly AuditLogger $audit,
        private readonly OfficialReportExportDispatcher $dispatcher,
    ) {}

    public function request(
        OfficialReportSnapshot $snapshot,
        string $format,
        User $actor,
        ?string $clientKey = null,
    ): ReportExportJob {
        if (! in_array($snapshot->status, ['approved', 'superseded', 'archived'], true)) {
            throw new RuntimeException('Only an approved official report snapshot can be exported.');
        }
        $definition = $this->registry->official($snapshot->report_type);
        if (! in_array($format, $definition->exports, true)) {
            throw new RuntimeException("Format {$format} is not registered for {$snapshot->report_type}.");
        }
        $material = implode('|', [
            $snapshot->public_id,
            $actor->getKey(),
            $format,
            trim((string) ($clientKey ?: 'canonical')),
        ]);
        $idempotencyKey = hash('sha256', $material);

        $existing = ReportExportJob::query()->where('idempotency_key', $idempotencyKey)->first();
        if ($existing !== null) {
            if ($existing->status === 'queued') {
                $this->dispatcher->dispatchDue();
            }

            return $existing;
        }

        [$export, $dispatch] = DB::transaction(function () use ($snapshot, $format, $actor, $idempotencyKey): array {
            // Serialize the per-user admission check on a real row. PostgreSQL
            // rejects `COUNT(*) ... FOR UPDATE`, and locking the user also
            // prevents two concurrent requests from both observing a free
            // export slot before either inserts its job.
            User::query()->whereKey($actor->getKey())->lockForUpdate()->firstOrFail();
            $activeKey = hash('sha256', implode('|', [$snapshot->public_id, $actor->getKey(), $format]));
            $active = ReportExportJob::query()->where('active_key', $activeKey)
                ->whereIn('status', ['queued', 'generating'])
                ->lockForUpdate()
                ->first();
            if ($active !== null) {
                return [$active, false];
            }
            $maximum = max(1, (int) config('library.reports.export_user_active_limit', 4));
            $activeCount = ReportExportJob::query()->where('requested_by', $actor->getKey())
                ->whereIn('status', ['queued', 'generating'])
                ->count();
            if ($activeCount >= $maximum) {
                throw new RuntimeException("A user may have at most {$maximum} active report exports.");
            }
            // firstOrCreate catches a concurrent unique-key race and reloads
            // the winner by active_key. The unique database constraint is the
            // final arbiter even when callers use different client keys.
            try {
                $export = ReportExportJob::query()->firstOrCreate(
                    ['active_key' => $activeKey],
                    [
                        'public_id' => (string) Str::uuid(),
                        'snapshot_id' => $snapshot->getKey(),
                        'requested_by' => $actor->getKey(),
                        'format' => $format,
                        'status' => 'queued',
                        'progress' => 0,
                        'idempotency_key' => $idempotencyKey,
                        'dispatch_after' => now('UTC'),
                    ],
                );
            } catch (UniqueConstraintViolationException $exception) {
                // A ready row may have cleared active_key between our initial
                // idempotency lookup and insert. In that race the immutable
                // client key still identifies the canonical winner.
                $export = ReportExportJob::query()
                    ->where('idempotency_key', $idempotencyKey)
                    ->orWhere('active_key', $activeKey)
                    ->first();
                if ($export === null) {
                    throw $exception;
                }
            }
            if (! $export->wasRecentlyCreated) {
                return [$export, false];
            }
            $this->audit->logRequired(
                actionType: 'official_report.export_queued',
                entityType: 'official_report_export',
                entityId: $export->public_id,
                newValues: [
                    'snapshot_id' => $snapshot->public_id,
                    'format' => $format,
                    'idempotency_key' => $idempotencyKey,
                ],
                scope: 'operational',
                actor: $actor,
            );

            return [$export, true];
        });

        if ($dispatch) {
            $this->dispatcher->dispatchDue();
        }

        return $export->fresh();
    }

    public function retry(ReportExportJob $export, User $actor): ReportExportJob
    {
        $export = DB::transaction(function () use ($export, $actor): ReportExportJob {
            $locked = ReportExportJob::query()->lockForUpdate()->findOrFail($export->getKey());
            if ($locked->status !== 'failed') {
                throw new RuntimeException('Only a failed official report export can be retried.');
            }
            $snapshotPublicId = (string) $locked->snapshot()->value('public_id');
            $activeKey = hash('sha256', implode('|', [$snapshotPublicId, $locked->requested_by, $locked->format]));
            $conflict = ReportExportJob::query()
                ->where('active_key', $activeKey)
                ->where('id', '!=', $locked->getKey())
                ->whereIn('status', ['queued', 'generating'])
                ->exists();
            if ($conflict) {
                throw new RuntimeException('An equivalent official report export is already active.');
            }
            $locked->update([
                'status' => 'queued', 'progress' => 0, 'error_message' => null,
                'public_error_code' => null, 'started_at' => null, 'completed_at' => null,
                'lease_token' => null, 'lease_expires_at' => null, 'last_heartbeat_at' => null,
                'dispatch_after' => now('UTC'), 'dispatched_at' => null, 'active_key' => $activeKey,
            ]);
            $this->audit->logRequired(
                actionType: 'official_report.export_retried',
                entityType: 'official_report_export',
                entityId: $locked->public_id,
                newValues: ['attempts' => $locked->attempts],
                scope: 'operational',
                actor: $actor,
            );

            return $locked->fresh();
        });
        $this->dispatcher->dispatchDue();

        return $export;
    }
}
