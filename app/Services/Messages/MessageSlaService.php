<?php

namespace App\Services\Messages;

use App\Models\ContactMessage;
use App\Models\MessageSlaEvent;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class MessageSlaService
{
    public function __construct(private readonly MessageNotificationService $notifications, private readonly AuditLogger $audit) {}

    /** @return array{reminded:int,escalated:int} */
    public function sweep(): array
    {
        $result = ['reminded' => 0, 'escalated' => 0];
        if (! Schema::hasColumn('contact_messages', 'due_at')) {
            return $result;
        }
        $reminderHours = max(1, (int) Setting::valueFor('library_feedback_sla_reminder_hours', 24));
        ContactMessage::query()->whereNotIn('status', ['resolved', 'rejected', 'closed'])->whereNull('sla_paused_at')->whereNotNull('due_at')->orderBy('id')->chunkById(100, function ($messages) use (&$result, $reminderHours): void {
            foreach ($messages as $message) {
                if ($message->due_at->isPast()) {
                    if ($this->once($message, 'escalation', 'overdue')) {
                        $directors = User::query()->where('is_active', true)->role('director')->get();
                        $directors->each(fn (User $director) => $message->watchers()->syncWithoutDetaching([$director->getKey() => ['reason' => 'sla_escalation']]));
                        $this->notifications->staff($message->refresh(), 'message_sla_breached');
                        $this->audit->logRequired('message.sla_escalated', 'contact_message', $message->getKey(), newValues: ['due_at' => $message->due_at->toIso8601String()], scope: 'operational', metadata: ['ticket_number' => $message->ticket_number]);
                        $result['escalated']++;
                    }
                } elseif ($message->due_at->lte(now('UTC')->addHours($reminderHours)) && $this->once($message, 'reminder', 'due_'.$reminderHours.'h')) {
                    $this->notifications->staff($message, 'message_sla_reminder');
                    $result['reminded']++;
                }
            }
        });

        return $result;
    }

    private function once(ContactMessage $message, string $type, string $threshold): bool
    {
        return DB::transaction(function () use ($message, $type, $threshold): bool {
            $event = MessageSlaEvent::query()->firstOrCreate([
                'contact_message_id' => $message->getKey(), 'event_type' => $type, 'threshold_key' => $threshold,
            ], ['triggered_at' => now('UTC'), 'metadata' => ['due_at' => $message->due_at?->toIso8601String()]]);

            return $event->wasRecentlyCreated;
        });
    }
}
