<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class PruneRepositoryUsage extends Command
{
    protected $signature = 'repository:usage-prune {--days=1095 : Retention window in days}';

    protected $description = 'Delete repository daily usage aggregates outside the retention window';

    public function handle(): int
    {
        $days = filter_var($this->option('days'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 30, 'max_range' => 3650],
        ]);
        if ($days === false) {
            $this->error('Retention must be between 30 and 3650 days.');

            return self::INVALID;
        }

        if (! Schema::hasTable('repository_usage_daily')) {
            return self::SUCCESS;
        }

        $deleted = DB::table('repository_usage_daily')
            ->where('occurred_on', '<', today('UTC')->subDays($days)->toDateString())
            ->delete();

        $this->info("Repository usage aggregates pruned: {$deleted}");

        return self::SUCCESS;
    }
}
