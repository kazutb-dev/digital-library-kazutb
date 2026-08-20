<?php

namespace App\Console\Commands;

use App\Services\ExternalResources\ExternalResourceNotificationOutboxService;
use Illuminate\Console\Command;

class ProcessExternalResourceNotificationOutbox extends Command
{
    protected $signature = 'library:external-resources:notifications
        {--limit=100 : Maximum queued notifications to process}';

    protected $description = 'Retry pending external-resource licence and health notifications';

    public function handle(ExternalResourceNotificationOutboxService $outbox): int
    {
        $limit = filter_var($this->option('limit'), FILTER_VALIDATE_INT, [
            'options' => ['min_range' => 1, 'max_range' => 1000],
        ]);
        if ($limit === false) {
            $this->error('The --limit value must be an integer between 1 and 1000.');

            return self::INVALID;
        }

        $processed = $outbox->drain($limit);
        $this->info("Processed {$processed} external-resource notification(s).");

        return self::SUCCESS;
    }
}
