<?php

namespace App\Jobs;

use App\Models\DataQualityScanRun;
use App\Services\DataQuality\DataQualityScanner;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Throwable;

class RunDataQualityScan implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public int $timeout = 3600;

    public int $tries = 1;

    public function __construct(public readonly int $scanRunId) {}

    public function handle(DataQualityScanner $scanner): void
    {
        $scanner->execute(DataQualityScanRun::query()->findOrFail($this->scanRunId));
    }

    public function failed(?Throwable $exception): void
    {
        DataQualityScanRun::query()->whereKey($this->scanRunId)->update([
            'status' => 'failed',
            'finished_at' => now(),
            'error_message' => mb_substr($exception?->getMessage() ?? 'Queue worker stopped the scan.', 0, 65000),
        ]);
    }
}
