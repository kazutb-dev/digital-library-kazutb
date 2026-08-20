<?php

namespace App\Console\Commands;

use App\Models\OfficialReportSnapshot;
use App\Models\ReportExportJob;
use App\Services\AuditLogger;
use App\Services\Reports\OfficialReportExportDispatcher;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Throwable;

final class SweepOfficialReports extends Command
{
    protected $signature = 'library:reports-sweep';

    protected $description = 'Recover report export leases, dispatch queued jobs, and enforce report retention';

    public function handle(OfficialReportExportDispatcher $dispatcher, AuditLogger $audit): int
    {
        $recovered = $this->recoverStaleExports($audit);
        $dispatched = $dispatcher->dispatchDue();
        $deleted = $this->pruneExpiredExports($audit);
        $snapshots = $this->markExpiredSnapshots($audit);

        $this->info("Recovered: {$recovered}, dispatched: {$dispatched}, export files deleted: {$deleted}, snapshots archived: {$snapshots}");

        return self::SUCCESS;
    }

    private function recoverStaleExports(AuditLogger $audit): int
    {
        $count = 0;
        ReportExportJob::query()
            ->where('status', 'generating')
            ->where(fn ($query) => $query->whereNull('lease_expires_at')->orWhere('lease_expires_at', '<=', now('UTC')))
            ->orderBy('id')
            ->chunkById(100, function ($exports) use (&$count, $audit): void {
                foreach ($exports as $export) {
                    DB::transaction(function () use ($export, &$count, $audit): void {
                        $locked = ReportExportJob::query()->lockForUpdate()->find($export->getKey());
                        if ($locked === null || $locked->status !== 'generating'
                            || ($locked->lease_expires_at !== null && $locked->lease_expires_at->isFuture())) {
                            return;
                        }
                        if ($locked->file_disk && $locked->file_path) {
                            try {
                                $disk = Storage::disk($locked->file_disk);
                                if ($disk->exists($locked->file_path)
                                    && (! $disk->delete($locked->file_path) || $disk->exists($locked->file_path))) {
                                    $this->auditRecoveryFailure($audit, $locked, 'storage_delete_rejected');

                                    return;
                                }
                            } catch (Throwable $exception) {
                                report($exception);
                                $this->auditRecoveryFailure($audit, $locked, 'storage_exception');

                                return;
                            }
                        }
                        $locked->update([
                            'status' => 'queued',
                            'progress' => 0,
                            'file_disk' => null,
                            'file_path' => null,
                            'file_name' => null,
                            'mime_type' => null,
                            'file_size' => null,
                            'file_hash' => null,
                            'lease_token' => null,
                            'lease_expires_at' => null,
                            'last_heartbeat_at' => null,
                            'dispatch_after' => now('UTC'),
                            'dispatched_at' => null,
                            'error_message' => null,
                            'public_error_code' => null,
                            'completed_at' => null,
                        ]);
                        $audit->logRequired(
                            'official_report.export_recovered',
                            'official_report_export',
                            $locked->public_id,
                            newValues: ['attempts' => $locked->attempts],
                            scope: 'operational',
                            actor: ['name' => 'Report scheduler', 'role' => 'system'],
                        );
                        $count++;
                    });
                }
            });

        return $count;
    }

    private function auditRecoveryFailure(AuditLogger $audit, ReportExportJob $export, string $reason): void
    {
        $audit->logRequired(
            'official_report.export_recovery_failed',
            'official_report_export',
            $export->public_id,
            newValues: ['reason' => $reason],
            scope: 'operational',
            actor: ['name' => 'Report scheduler', 'role' => 'system'],
        );
    }

    private function pruneExpiredExports(AuditLogger $audit): int
    {
        $count = 0;
        ReportExportJob::query()
            ->whereIn('status', ['ready', 'failed'])
            ->whereNotNull('retention_until')
            ->where('retention_until', '<=', now('UTC'))
            ->whereNull('file_deleted_at')
            ->orderBy('id')
            ->chunkById(100, function ($exports) use (&$count, $audit): void {
                foreach ($exports as $export) {
                    DB::transaction(function () use ($export, &$count, $audit): void {
                        $locked = ReportExportJob::query()->lockForUpdate()->find($export->getKey());
                        if ($locked === null
                            || $locked->file_deleted_at !== null
                            || ! in_array($locked->status, ['ready', 'failed'], true)
                            || $locked->retention_until === null
                            || $locked->retention_until->isFuture()) {
                            return;
                        }

                        if ($locked->file_disk && $locked->file_path) {
                            try {
                                $deleted = Storage::disk($locked->file_disk)->delete($locked->file_path);
                            } catch (Throwable $exception) {
                                report($exception);
                                $this->auditRetentionFailure($audit, $locked, 'storage_exception');

                                return;
                            }
                            if (! $deleted) {
                                $this->auditRetentionFailure($audit, $locked, 'storage_delete_rejected');

                                return;
                            }
                        }

                        $locked->update(['file_deleted_at' => now('UTC'), 'active_key' => null]);
                        $audit->logRequired(
                            'official_report.export_retention_enforced',
                            'official_report_export',
                            $locked->public_id,
                            newValues: ['retention_until' => $locked->retention_until?->toIso8601String()],
                            scope: 'operational',
                            actor: ['name' => 'Report scheduler', 'role' => 'system'],
                        );
                        $count++;
                    });
                }
            });

        return $count;
    }

    private function auditRetentionFailure(AuditLogger $audit, ReportExportJob $export, string $reason): void
    {
        $audit->logRequired(
            'official_report.export_retention_failed',
            'official_report_export',
            $export->public_id,
            newValues: [
                'retention_until' => $export->retention_until?->toIso8601String(),
                'reason' => $reason,
            ],
            scope: 'operational',
            actor: ['name' => 'Report scheduler', 'role' => 'system'],
        );
    }

    private function markExpiredSnapshots(AuditLogger $audit): int
    {
        if (config('library.reports.expired_snapshot_action', 'preserve') !== 'archive') {
            return 0;
        }

        $count = 0;
        OfficialReportSnapshot::query()
            ->whereIn('status', ['approved', 'superseded'])
            ->whereNotNull('retention_until')
            ->where('retention_until', '<=', now('UTC'))
            ->orderBy('id')
            ->chunkById(100, function ($snapshots) use (&$count, $audit): void {
                foreach ($snapshots as $snapshot) {
                    DB::transaction(function () use ($snapshot, &$count, $audit): void {
                        $locked = OfficialReportSnapshot::query()->lockForUpdate()->find($snapshot->getKey());
                        if ($locked === null
                            || ! in_array($locked->status, ['approved', 'superseded'], true)
                            || $locked->retention_until === null
                            || $locked->retention_until->isFuture()) {
                            return;
                        }
                        DB::table('official_report_snapshots')->whereKey($locked->getKey())->update([
                            'status' => 'archived',
                            'archived_at' => now('UTC'),
                            'updated_at' => now('UTC'),
                        ]);
                        $audit->logRequired(
                            'official_report.retention_archived',
                            'official_report_snapshot',
                            $locked->public_id,
                            scope: 'operational',
                            actor: ['name' => 'Report scheduler', 'role' => 'system'],
                        );
                        $count++;
                    });
                }
            });

        return $count;
    }
}
