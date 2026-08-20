<?php

namespace App\Services\Messages;

use App\Models\Catalog\ReaderProfile;
use App\Models\ContactMessage;
use App\Models\MessageCategory;
use App\Models\MessageThreadEntry;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class MessageSubmissionService
{
    public function __construct(
        private readonly MessageAttachmentService $attachments,
        private readonly MessageRoutingService $routing,
        private readonly MessageNotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    /** @param array<string, mixed> $data */
    public function submit(User $user, array $data, Request $request): ContactMessage
    {
        abort_unless($user->is_active, 403);
        $category = MessageCategory::query()->active()->whereKey($data['category_id'])->firstOrFail();
        if ($category->message_type !== $data['type']) {
            throw ValidationException::withMessages(['category_id' => __('messages.validation.category_type')]);
        }
        $this->assertRelatedEntityOwnership($user, $data['related_entity_type'] ?? null, $data['related_entity_id'] ?? null);
        $token = trim((string) ($data['submission_token'] ?? ''));
        if ($token !== '') {
            $existing = ContactMessage::query()->where('user_id', $user->getKey())->where('idempotency_key', hash('sha256', $user->getKey().':'.$token))->first();
            if ($existing) {
                return $existing;
            }
        }

        $profile = ReaderProfile::forUser($user);
        $priority = match ($data['type']) {
            'complaint' => 'high', 'suggestion' => 'low', default => 'medium'
        };
        $requested = $data['requested_priority'] ?? null;
        if ($requested === 'high' && $data['type'] !== 'suggestion') {
            $priority = 'high';
        }
        $slaHours = $this->slaHours($category, $priority);
        $firstResponseHours = min($slaHours, max(1, (int) Setting::valueFor('library_feedback_first_response_hours', 24)));
        $stored = [];

        try {
            return DB::transaction(function () use ($user, $data, $request, $category, $profile, $priority, $slaHours, $firstResponseHours, $token, &$stored): ContactMessage {
                $message = ContactMessage::query()->create([
                    'public_id' => (string) Str::uuid(), 'user_id' => $user->getKey(), 'sender_id' => $user->getKey(),
                    'reader_profile_id' => $profile->getKey(), 'type' => $data['type'], 'category' => $data['type'],
                    'category_id' => $category->getKey(), 'subject' => trim(strip_tags($data['subject'])),
                    'body' => trim(strip_tags($data['body'])), 'source' => 'cabinet',
                    'preferred_locale' => in_array($user->locale, ['kk', 'ru', 'en'], true) ? $user->locale : app()->getLocale(),
                    'preferred_contact_channel' => $data['preferred_contact_channel'] ?? 'in_app',
                    'sender_email' => $user->email, 'sender_name_snapshot' => $user->name,
                    'sender_email_snapshot' => $user->email, 'sender_phone_snapshot' => $profile->phone,
                    'reader_ticket_snapshot' => $profile->ticket_number, 'branch_id' => $data['branch_id'] ?? $profile->preferred_branch_id,
                    'related_entity_type' => $data['related_entity_type'] ?? null, 'related_entity_id' => $data['related_entity_id'] ?? null,
                    'complaint_against_user_id' => $data['complaint_against_user_id'] ?? null,
                    'status' => 'open', 'priority' => $priority, 'requires_director_review' => (bool) $category->requires_director_review,
                    'sensitive' => $data['type'] === 'complaint', 'due_at' => now('UTC')->addHours($slaHours),
                    'first_response_due_at' => now('UTC')->addHours($firstResponseHours), 'last_user_message_at' => now('UTC'),
                    'idempotency_key' => $token !== '' ? hash('sha256', $user->getKey().':'.$token) : null,
                ]);
                $message->update(['ticket_number' => sprintf('LIB-%s-%06d', now('UTC')->format('Y'), $message->getKey())]);
                $entry = MessageThreadEntry::query()->create([
                    'contact_message_id' => $message->getKey(), 'author_id' => $user->getKey(), 'author_type' => 'user',
                    'entry_type' => 'user_message', 'body' => $message->body, 'visibility' => 'public',
                    'metadata' => ['requested_priority' => $data['requested_priority'] ?? null, 'request_id' => $request->attributes->get('request_id')],
                ]);
                $stored = $this->attachments->store($message, $entry, $request->file('attachments', []), $user);
                $this->routing->route($message->load('messageCategory'), $user);
                $this->audit->logRequired('message.created', 'contact_message', $message->getKey(), newValues: [
                    'ticket_number' => $message->ticket_number, 'type' => $message->type, 'category_id' => $message->category_id,
                    'status' => $message->status, 'priority' => $message->priority, 'assigned_to' => $message->assigned_to,
                    'due_at' => $message->due_at?->toIso8601String(), 'attachment_count' => count($stored),
                ], scope: 'operational', actor: $user, request: $request, metadata: ['public_id' => $message->public_id]);
                $this->notifications->reader($message, 'message_registered');
                $this->notifications->staff($message, $priority === 'critical' ? 'message_critical' : 'message_assigned');

                return $message->refresh();
            });
        } catch (\Throwable $exception) {
            foreach ($stored as $attachment) {
                Storage::disk($attachment->disk)->delete($attachment->path);
            }
            throw $exception;
        }
    }

    private function slaHours(MessageCategory $category, string $priority): int
    {
        $typeHours = max(1, (int) Setting::valueFor('library_feedback_sla_'.$category->message_type.'_hours', $category->sla_hours));
        $priorityHours = match ($priority) {
            'critical' => (int) Setting::valueFor('library_feedback_sla_critical_hours', 8),
            'high' => (int) Setting::valueFor('library_feedback_sla_high_hours', 24),
            default => $typeHours,
        };

        return min(2160, max(1, min($typeHours, $priorityHours)));
    }

    private function assertRelatedEntityOwnership(User $user, mixed $type, mixed $id): void
    {
        if ($type === null && $id === null) {
            return;
        }
        if (! is_string($type) || ! in_array($type, ['book', 'copy', 'loan', 'reservation', 'fine', 'incident', 'electronic_material', 'news', 'branch'], true) || ! is_numeric($id)) {
            throw ValidationException::withMessages(['related_entity_id' => __('messages.validation.related_invalid')]);
        }
        $id = (int) $id;
        $exists = match ($type) {
            'book' => DB::table('bibliographic_records')->where('id', $id)->exists(),
            'copy' => DB::table('loans')->where('user_id', $user->getKey())->where('copy_id', $id)->whereNull('returned_at')->exists(),
            'loan' => DB::table('loans')->where('id', $id)->where('user_id', $user->getKey())->exists(),
            'reservation' => DB::table('reservations')->where('id', $id)->where('user_id', $user->getKey())->exists(),
            'fine' => DB::table('fines')->where('id', $id)->where('user_id', $user->getKey())->exists(),
            'incident' => DB::table('circulation_incident_cases')->where('id', $id)->where('reader_id', $user->getKey())->exists(),
            'electronic_material' => DB::table('electronic_materials')->where('id', $id)->exists(),
            'news' => DB::table('news')->where('id', $id)->where('status', 'published')->exists(),
            'branch' => DB::table('branches')->where('id', $id)->exists(),
        };
        if (! $exists) {
            throw ValidationException::withMessages(['related_entity_id' => __('messages.validation.related_forbidden')]);
        }
    }
}
