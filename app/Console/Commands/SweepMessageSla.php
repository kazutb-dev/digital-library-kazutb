<?php

namespace App\Console\Commands;

use App\Services\Messages\MessageSlaService;
use Illuminate\Console\Command;

class SweepMessageSla extends Command
{
    protected $signature = 'library:messages-sweep';

    protected $description = 'Send idempotent message SLA reminders and escalations';

    public function handle(MessageSlaService $sla): int
    {
        $result = $sla->sweep();
        $this->info(sprintf('Message SLA sweep: %d reminder(s), %d escalation(s).', $result['reminded'], $result['escalated']));

        return self::SUCCESS;
    }
}
