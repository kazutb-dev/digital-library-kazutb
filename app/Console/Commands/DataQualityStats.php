<?php

namespace App\Console\Commands;

use App\Models\DataQualityIssue;
use App\Models\DataQualityScanRun;
use Illuminate\Console\Command;

class DataQualityStats extends Command
{
    protected $signature = 'library:data-quality:stats';

    protected $description = 'Print current persistent data-quality queue statistics';

    public function handle(): int
    {
        $this->table(['metric', 'value'], [
            ['actionable', DataQualityIssue::query()->actionable()->count()],
            ['critical', DataQualityIssue::query()->actionable()->where('severity', 'critical')->count()],
            ['high', DataQualityIssue::query()->actionable()->where('severity', 'high')->count()],
            ['overdue', DataQualityIssue::query()->actionable()->where('due_at', '<', now())->count()],
            ['resolved', DataQualityIssue::query()->where('status', 'resolved')->count()],
            ['scan_runs', DataQualityScanRun::query()->count()],
        ]);

        return self::SUCCESS;
    }
}
