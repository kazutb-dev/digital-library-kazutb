<?php

namespace App\Jobs;

use App\Models\ReportExportJob;
use App\Services\AuditLogger;
use App\Services\Catalog\LibraryNotificationService;
use App\Services\Reports\OfficialReportRenderer;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable as FoundationQueueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class GenerateOfficialReportExport implements ShouldQueue
{
    use FoundationQueueable, InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $timeout = 180;

    public function __construct(public readonly int $exportId) {}

    public function handle(
        OfficialReportRenderer $renderer,
        AuditLogger $audit,
        LibraryNotificationService $notifications,
    ): void {
        $leaseToken = (string) Str::uuid();
        $leaseSeconds = max(60, (int) config('library.reports.export_lease_seconds', 300));
        $export = DB::transaction(function () use ($leaseToken, $leaseSeconds): ?ReportExportJob {
            $locked = ReportExportJob::query()->lockForUpdate()->with(['snapshot', 'requester'])->find($this->exportId);
            if ($locked === null || $locked->status === 'ready') {
                return null;
            }
            $staleGenerating = $locked->status === 'generating'
                && ($locked->lease_expires_at === null || $locked->lease_expires_at->isPast());
            if (! in_array($locked->status, ['queued', 'failed'], true) && ! $staleGenerating) {
                return null;
            }
            $activeKey = hash('sha256', implode('|', [
                $locked->snapshot->public_id,
                $locked->requested_by,
                $locked->format,
            ]));
            $hasCompetingLease = ReportExportJob::query()
                ->where('active_key', $activeKey)
                ->where('id', '!=', $locked->getKey())
                ->whereIn('status', ['queued', 'generating'])
                ->exists();
            if ($hasCompetingLease) {
                return null;
            }
            $locked->update([
                'status' => 'generating',
                'progress' => 10,
                'attempts' => $locked->attempts + 1,
                'started_at' => now('UTC'),
                'completed_at' => null,
                'error_message' => null,
                'public_error_code' => null,
                'lease_token' => $leaseToken,
                'lease_expires_at' => now('UTC')->addSeconds($leaseSeconds),
                'last_heartbeat_at' => now('UTC'),
                'active_key' => $activeKey,
            ]);

            return $locked->fresh(['snapshot', 'requester']);
        });
        if ($export === null) {
            return;
        }

        $temporary = null;
        $storedPath = null;
        $committed = false;
        try {
            $this->heartbeat($export, $leaseToken, 30, $leaseSeconds);
            $rendered = $renderer->render($export->snapshot, $export->format);
            $temporary = $rendered['path'];
            $maximumSize = max(1048576, (int) config('library.reports.max_export_bytes', 52428800));
            if ($rendered['size'] > $maximumSize) {
                throw new RuntimeException('The generated report exceeds the configured file-size limit.');
            }
            $this->heartbeat($export, $leaseToken, 75, $leaseSeconds);
            $path = sprintf(
                'official-reports/exports/%s/revision-%04d/%s-%s.%s',
                $export->snapshot->lineage_id,
                $export->snapshot->revision,
                $export->public_id,
                $leaseToken,
                $rendered['extension'],
            );
            $stream = fopen($temporary, 'rb');
            if ($stream === false || ! Storage::disk('local')->put($path, $stream)) {
                is_resource($stream) && fclose($stream);
                throw new RuntimeException('Unable to store the official report export.');
            }
            fclose($stream);
            $storedPath = $path;
            $storedHash = hash_init('sha256');
            $storedStream = Storage::disk('local')->readStream($path);
            if (! is_resource($storedStream)) {
                throw new RuntimeException('Unable to verify the stored official report export.');
            }
            hash_update_stream($storedHash, $storedStream);
            fclose($storedStream);
            if (! hash_equals($rendered['hash'], hash_final($storedHash))) {
                Storage::disk('local')->delete($path);
                throw new RuntimeException('Stored official report export failed hash verification.');
            }
            $filename = sprintf(
                '%s-%s.%s',
                strtolower($export->snapshot->report_number),
                $export->snapshot->approved_at?->format('Ymd') ?? now()->format('Ymd'),
                $rendered['extension'],
            );
            $committed = DB::transaction(function () use ($export, $leaseToken, $path, $filename, $rendered, $audit): bool {
                $updated = ReportExportJob::query()
                    ->whereKey($export->getKey())
                    ->where('status', 'generating')
                    ->where('lease_token', $leaseToken)
                    ->update([
                        'status' => 'ready',
                        'progress' => 100,
                        'file_disk' => 'local',
                        'file_path' => $path,
                        'file_name' => $filename,
                        'mime_type' => $rendered['mime'],
                        'file_size' => $rendered['size'],
                        'file_hash' => $rendered['hash'],
                        'completed_at' => now('UTC'),
                        'retention_until' => now('UTC')->addDays(max(1, (int) config('library.reports.export_retention_days', 365))),
                        'file_deleted_at' => null,
                        'active_key' => null,
                        'lease_token' => null,
                        'lease_expires_at' => null,
                        'last_heartbeat_at' => now('UTC'),
                        'updated_at' => now('UTC'),
                    ]);
                if ($updated !== 1) {
                    return false;
                }
                $audit->logRequired(
                    actionType: 'official_report.export_completed',
                    entityType: 'official_report_export',
                    entityId: $export->public_id,
                    newValues: [
                        'snapshot_id' => $export->snapshot->public_id,
                        'format' => $export->format,
                        'file_hash' => $rendered['hash'],
                        'file_size' => $rendered['size'],
                    ],
                    scope: 'operational',
                    actor: $export->requester,
                );

                return true;
            });
            if (! $committed) {
                throw new RuntimeException('The report export lease was lost before completion.');
            }
            $export->refresh();
            if ($export->requester !== null) {
                try {
                    $notifications->sendLocalized(
                        $export->requester,
                        'report_export_ready',
                        'official_reports.notifications.ready_title',
                        'official_reports.notifications.ready_body',
                        ['number' => $export->snapshot->report_number, 'format' => strtoupper($export->format)],
                        ['export_id' => $export->public_id, 'snapshot_id' => $export->snapshot->public_id],
                    );
                } catch (Throwable $notificationFailure) {
                    report($notificationFailure);
                }
            }
        } catch (Throwable $exception) {
            report($exception);
            if (! $committed && is_string($storedPath)) {
                Storage::disk('local')->delete($storedPath);
            }
            $errorCode = $this->publicErrorCode($exception);
            $failed = DB::transaction(function () use ($export, $leaseToken, $errorCode, $audit): bool {
                $updated = ReportExportJob::query()
                    ->whereKey($export->getKey())
                    ->where('status', 'generating')
                    ->where('lease_token', $leaseToken)
                    ->update([
                        'status' => 'failed',
                        'progress' => 0,
                        'error_message' => 'Report generation failed; consult protected application logs.',
                        'public_error_code' => $errorCode,
                        'completed_at' => now('UTC'),
                        'active_key' => null,
                        'lease_token' => null,
                        'lease_expires_at' => null,
                        'last_heartbeat_at' => now('UTC'),
                        'updated_at' => now('UTC'),
                    ]);
                if ($updated !== 1) {
                    return false;
                }
                $audit->logRequired(
                    actionType: 'official_report.export_failed',
                    entityType: 'official_report_export',
                    entityId: $export->public_id,
                    newValues: ['format' => $export->format, 'error_code' => $errorCode],
                    scope: 'operational',
                    actor: $export->requester,
                );

                return true;
            });
            if (! $failed) {
                // Another worker owns the lease or has already committed a
                // valid file. This stale delivery must not overwrite it.
                return;
            }
            $export->refresh();
            if ($export->requester !== null) {
                try {
                    $notifications->sendLocalized(
                        $export->requester,
                        'report_export_failed',
                        'official_reports.notifications.failed_title',
                        'official_reports.notifications.failed_body',
                        ['number' => $export->snapshot->report_number, 'format' => strtoupper($export->format)],
                        ['export_id' => $export->public_id, 'snapshot_id' => $export->snapshot->public_id],
                    );
                } catch (Throwable $notificationFailure) {
                    report($notificationFailure);
                }
            }
            throw $exception;
        } finally {
            if (is_string($temporary)) {
                @unlink($temporary);
            }
        }
    }

    private function heartbeat(ReportExportJob $export, string $leaseToken, int $progress, int $leaseSeconds): void
    {
        $updated = ReportExportJob::query()
            ->whereKey($export->getKey())
            ->where('status', 'generating')
            ->where('lease_token', $leaseToken)
            ->update([
                'progress' => $progress,
                'last_heartbeat_at' => now('UTC'),
                'lease_expires_at' => now('UTC')->addSeconds($leaseSeconds),
                'updated_at' => now('UTC'),
            ]);
        if ($updated !== 1) {
            throw new RuntimeException('The report export lease was lost.');
        }
        $export->refresh();
    }

    private function publicErrorCode(Throwable $exception): string
    {
        return $exception instanceof RuntimeException
            ? 'REPORT_GENERATION_FAILED'
            : 'REPORT_INTERNAL_ERROR';
    }
}
