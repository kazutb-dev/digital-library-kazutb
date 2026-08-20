<?php

namespace App\Jobs;

use App\Integrations\IntegrationHubService;
use App\Models\IntegrationOutboxMessage;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessIntegrationOutbox implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public int $timeout = 60;

    public function __construct(public readonly int $messageId)
    {
        $this->onQueue('integrations');
    }

    public function handle(IntegrationHubService $hub): void
    {
        $message = IntegrationOutboxMessage::query()->find($this->messageId);
        if ($message !== null) {
            $hub->deliver($message);
        }
    }
}
