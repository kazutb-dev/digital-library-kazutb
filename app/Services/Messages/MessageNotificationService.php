<?php

namespace App\Services\Messages;

use App\Models\ContactMessage;
use App\Models\User;
use App\Services\Catalog\LibraryNotificationService;

class MessageNotificationService
{
    public function __construct(private readonly LibraryNotificationService $notifications) {}

    public function reader(ContactMessage $message, string $event): void
    {
        $user = $message->user ?: $message->sender;
        if (! $user) {
            return;
        }
        $this->notifications->sendLocalized($user, $event, 'messages.notifications.'.$event.'.title', 'messages.notifications.'.$event.'.body', [
            'ticket' => $message->ticket_number, 'subject' => $message->subject,
            'status' => ['_translation' => 'messages.statuses.'.$message->status],
        ], ['message_public_id' => $message->public_id, 'ticket_number' => $message->ticket_number, 'status' => $message->status]);
    }

    public function staff(ContactMessage $message, string $event, ?User $specific = null): void
    {
        $recipients = collect([$specific ?: $message->assignee])->filter();
        $recipients = $recipients->merge($message->watchers()->get())->unique('id');
        $recipients->each(fn (User $user) => $this->notifications->sendLocalized($user, $event, 'messages.notifications.'.$event.'.title', 'messages.notifications.'.$event.'.body', [
            'ticket' => $message->ticket_number, 'subject' => $message->subject,
            'priority' => ['_translation' => 'messages.priorities.'.$message->priority],
        ], ['message_public_id' => $message->public_id, 'ticket_number' => $message->ticket_number, 'priority' => $message->priority]));
    }
}
