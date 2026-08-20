<?php

namespace App\Services\Reports;

use App\Jobs\GenerateOfficialReportExport;
use App\Models\ReportExportJob;
use Illuminate\Support\Facades\DB;

final class OfficialReportExportDispatcher
{
    public function dispatchDue(?int $limit = null): int
    {
        $limit ??= max(1, (int) config('library.reports.export_dispatch_batch', 100));
        $ids = ReportExportJob::query()
            ->where('status', 'queued')
            ->whereNull('file_deleted_at')
            ->where(fn ($query) => $query->whereNull('dispatch_after')->orWhere('dispatch_after', '<=', now('UTC')))
            ->where(fn ($query) => $query->whereNull('dispatched_at')->orWhere('dispatched_at', '<=', now('UTC')->subMinutes(5)))
            ->orderBy('id')
            ->limit($limit)
            ->pluck('id');

        $dispatched = 0;
        foreach ($ids as $id) {
            $claimed = DB::transaction(function () use ($id): bool {
                $job = ReportExportJob::query()->lockForUpdate()->find($id);
                if ($job === null || $job->status !== 'queued'
                    || ($job->dispatched_at !== null && $job->dispatched_at->isAfter(now('UTC')->subMinutes(5)))) {
                    return false;
                }
                $job->update(['dispatched_at' => now('UTC')]);

                return true;
            });
            if (! $claimed) {
                continue;
            }
            try {
                GenerateOfficialReportExport::dispatch((int) $id)->onQueue('reports');
            } catch (\Throwable $exception) {
                ReportExportJob::query()->whereKey($id)->where('status', 'queued')->update([
                    'dispatched_at' => null,
                    'updated_at' => now('UTC'),
                ]);
                throw $exception;
            }
            $dispatched++;
        }

        return $dispatched;
    }
}
