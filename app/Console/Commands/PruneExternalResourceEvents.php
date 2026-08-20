<?php

namespace App\Console\Commands;

use App\Services\ExternalResources\ExternalResourceAnalytics;
use Illuminate\Console\Command;

class PruneExternalResourceEvents extends Command
{
    protected $signature = 'library:external-resources:prune-events
        {--limit=5000 : Maximum events to delete in one run}';

    protected $description = 'Delete external-resource analytics after their configured retention period';

    public function handle(ExternalResourceAnalytics $analytics): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 50_000],
        ]);
        if ($limit === false) {
            $this->error('The --limit value must be an integer between 1 and 50000.');

            return self::INVALID;
        }

        $deleted = $analytics->pruneExpired($limit);
        $this->info("Pruned {$deleted} expired external-resource event(s).");

        return self::SUCCESS;
    }
}
