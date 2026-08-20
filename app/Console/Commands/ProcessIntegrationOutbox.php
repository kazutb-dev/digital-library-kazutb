<?php

namespace App\Console\Commands;

use App\Jobs\ProcessIntegrationOutbox as ProcessIntegrationOutboxJob;
use App\Models\IntegrationOutboxMessage;
use Illuminate\Console\Command;

class ProcessIntegrationOutbox extends Command
{
    protected $signature = 'library:integrations:dispatch {--limit=100}';

    protected $description = 'Dispatch due Integration Hub outbox messages';

    public function handle(): int
    {
        $limit = max(1, min(500, (int) $this->option('limit')));
        IntegrationOutboxMessage::query()
            ->where('status', 'processing')
            ->where('locked_at', '<=', now('UTC')->subMinutes(5))
            ->update(['status' => 'failed', 'locked_at' => null, 'next_attempt_at' => now('UTC'), 'error_code' => 'lease_expired']);
        $messages = IntegrationOutboxMessage::query()->whereIn('status', ['pending', 'failed'])->where(fn ($query) => $query->whereNull('next_attempt_at')->orWhere('next_attempt_at', '<=', now('UTC')))->orderBy('id')->limit($limit)->pluck('id');
        foreach ($messages as $id) {
            ProcessIntegrationOutboxJob::dispatch((int) $id);
        }
        $this->info('Dispatched '.$messages->count().' integration message(s).');

        return self::SUCCESS;
    }
}
