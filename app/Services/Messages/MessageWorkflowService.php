<?php

namespace App\Services\Messages;

use App\Models\ContactMessage;
use App\Models\MessageThreadEntry;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MessageWorkflowService
{
    private const TRANSITIONS = [
        'open' => ['in_review', 'rejected'],
        'in_review' => ['waiting_for_user', 'response_prepared', 'resolved', 'rejected'],
        'waiting_for_user' => ['in_review'],
        'response_prepared' => ['in_review', 'resolved'],
        'resolved' => ['closed', 'in_review'],
        'rejected' => ['in_review'],
        'closed' => [],
        'reopened' => ['in_review'],
    ];

    public function __construct(private readonly AuditLogger $audit, private readonly MessageNotificationService $notifications) {}

    public function assign(ContactMessage $message, ?User $assignee, User $actor, string $reason): ContactMessage
    {
        $this->authorize($actor, $message->assigned_to ? 'messages.reassign' : 'messages.assign');
        $reason = $this->reason($reason);
        if ($assignee && (! $assignee->is_active || $assignee->hasRole('member') || (int) $message->complaint_against_user_id === (int) $assignee->getKey())) {
            throw ValidationException::withMessages(['assigned_to' => __('messages.validation.assignee_forbidden')]);
        }

        return DB::transaction(function () use ($message, $assignee, $actor, $reason): ContactMessage {
            $locked = $this->lock($message);
            $old = $locked->assigned_to;
            $locked->update(['assigned_to' => $assignee?->getKey(), 'assigned_by' => $actor->getKey(), 'assigned_at' => $assignee ? now('UTC') : null, 'lock_version' => $locked->lock_version + 1]);
            $this->entry($locked, $actor, 'assignment', $reason, 'internal', ['old' => $old, 'new' => $assignee?->getKey()]);
            $this->auditEvent($old ? 'message.reassigned' : 'message.assigned', $locked, $actor, ['assigned_to' => $old], ['assigned_to' => $assignee?->getKey()], $reason);
            if ($assignee) {
                $this->notifications->staff($locked, 'message_assigned', $assignee);
            }

            return $locked;
        });
    }

    public function changePriority(ContactMessage $message, string $priority, User $actor, string $reason): ContactMessage
    {
        $this->authorize($actor, 'messages.change_priority');
        abort_unless(in_array($priority, ContactMessage::PRIORITIES, true), 422);

        return DB::transaction(function () use ($message, $priority, $actor, $reason): ContactMessage {
            $locked = $this->lock($message);
            $old = $locked->priority;
            $locked->update(['priority' => $priority, 'lock_version' => $locked->lock_version + 1]);
            $this->auditEvent('message.priority_changed', $locked, $actor, ['priority' => $old], ['priority' => $priority], $this->reason($reason));
            if (in_array($priority, ['high', 'critical'], true)) {
                $this->notifications->staff($locked, 'message_priority_raised');
            }

            return $locked;
        });
    }

    public function takeInReview(ContactMessage $message, User $actor): ContactMessage
    {
        $this->authorize($actor, 'messages.resolve');

        return $this->transition($message, 'in_review', $actor, __('messages.system.taken_in_review'), 'message.status_changed');
    }

    public function requestClarification(ContactMessage $message, User $actor, string $body): ContactMessage
    {
        $this->authorize($actor, 'messages.request_clarification');

        return DB::transaction(function () use ($message, $actor, $body): ContactMessage {
            $locked = $this->transitionLocked($message, 'waiting_for_user', $actor, null, 'message.clarification_requested');
            $this->entry($locked, $actor, 'clarification_request', $this->body($body), 'public');
            $locked->update(['last_staff_message_at' => now('UTC'), 'last_response_at' => now('UTC'), 'first_response_at' => $locked->first_response_at ?: now('UTC'), 'sla_paused_at' => (bool) Setting::valueFor('library_feedback_pause_sla_waiting_user', true) ? now('UTC') : null]);
            $this->notifications->reader($locked, 'message_clarification_requested');

            return $locked;
        });
    }

    public function addStaffReply(ContactMessage $message, User $actor, string $body): MessageThreadEntry
    {
        $this->authorize($actor, 'messages.prepare_response');

        return DB::transaction(function () use ($message, $actor, $body): MessageThreadEntry {
            $locked = $this->lock($message);
            abort_if(in_array($locked->status, ['resolved', 'rejected', 'closed'], true), 409);
            $entry = $this->entry($locked, $actor, 'staff_reply', $this->body($body), 'public');
            $locked->update(['first_response_at' => $locked->first_response_at ?: now('UTC'), 'last_response_at' => now('UTC'), 'last_staff_message_at' => now('UTC'), 'lock_version' => $locked->lock_version + 1]);
            $this->auditEvent('message.staff_replied', $locked, $actor, null, ['entry_id' => $entry->getKey()]);
            $this->notifications->reader($locked, 'message_staff_replied');

            return $entry;
        });
    }

    public function addInternalNote(ContactMessage $message, User $actor, string $body, string $visibility = 'internal'): MessageThreadEntry
    {
        $this->authorize($actor, 'messages.add_internal_note');
        abort_unless(in_array($visibility, ['internal', 'director_only'], true), 422);

        return DB::transaction(function () use ($message, $actor, $body, $visibility): MessageThreadEntry {
            $locked = $this->lock($message);
            $entry = $this->entry($locked, $actor, 'internal_note', $this->body($body), $visibility);
            $this->auditEvent('message.internal_note_added', $locked, $actor, null, ['entry_id' => $entry->getKey(), 'visibility' => $visibility]);
            $this->notifications->staff($locked, 'message_internal_note');

            return $entry;
        });
    }

    public function prepareOfficialResponse(ContactMessage $message, User $actor, string $body): ContactMessage
    {
        $this->authorize($actor, 'messages.prepare_response');

        return DB::transaction(function () use ($message, $actor, $body): ContactMessage {
            $locked = $this->lock($message);
            $requiresApproval = $locked->requires_director_review || in_array($locked->type, ['complaint', 'question'], true);
            if ($requiresApproval) {
                $locked = $this->transitionLocked($locked, 'response_prepared', $actor, null, 'message.official_response_prepared');
                $this->entry($locked, $actor, 'official_resolution', $this->body($body), 'director_only', ['draft' => true], true);
                $this->notifications->staff($locked, 'message_response_prepared');

                return $locked;
            }
            $this->entry($locked, $actor, 'official_resolution', $this->body($body), 'public', ['approved_by' => $actor->getKey()], true);
            $locked = $this->transitionLocked($locked, 'resolved', $actor, null, 'message.resolved');
            $this->notifications->reader($locked, 'message_resolved');

            return $locked;
        });
    }

    public function approveOfficialResponse(ContactMessage $message, User $actor): ContactMessage
    {
        $this->authorize($actor, 'messages.approve_response');
        abort_unless($actor->hasRole('director'), 403);

        return DB::transaction(function () use ($message, $actor): ContactMessage {
            $locked = $this->lock($message);
            abort_unless($locked->status === 'response_prepared', 409);
            $draft = $locked->threadEntries()->where('entry_type', 'official_resolution')->where('visibility', 'director_only')->latest('id')->firstOrFail();
            $this->entry($locked, $actor, 'official_resolution', (string) $draft->body, 'public', ['approved_by' => $actor->getKey(), 'draft_entry_id' => $draft->getKey()], true, $draft);
            $locked = $this->transitionLocked($locked, 'resolved', $actor, null, 'message.official_response_approved');
            $this->notifications->reader($locked, 'message_resolved');

            return $locked;
        });
    }

    public function returnOfficialResponse(ContactMessage $message, User $actor, string $reason): ContactMessage
    {
        $this->authorize($actor, 'messages.approve_response');

        return DB::transaction(function () use ($message, $actor, $reason): ContactMessage {
            $locked = $this->transitionLocked($message, 'in_review', $actor, $this->reason($reason), 'message.official_response_rejected');
            $this->entry($locked, $actor, 'internal_note', $reason, 'director_only');
            $this->notifications->staff($locked, 'message_response_returned');

            return $locked;
        });
    }

    public function reject(ContactMessage $message, User $actor, string $reason): ContactMessage
    {
        $this->authorize($actor, 'messages.reject');

        return DB::transaction(function () use ($message, $actor, $reason): ContactMessage {
            $locked = $this->transitionLocked($message, 'rejected', $actor, $this->reason($reason), 'message.rejected');
            $locked->update(['rejected_by' => $actor->getKey(), 'rejected_at' => now('UTC'), 'rejection_reason' => $reason]);
            $this->entry($locked, $actor, 'official_resolution', $reason, 'public', ['rejected' => true], true);
            $this->notifications->reader($locked, 'message_rejected');

            return $locked;
        });
    }

    public function close(ContactMessage $message, User $actor): ContactMessage
    {
        $this->authorize($actor, 'messages.close');

        return $this->transition($message, 'closed', $actor, null, 'message.closed');
    }

    public function reopen(ContactMessage $message, User $actor, string $reason): ContactMessage
    {
        $this->authorize($actor, 'messages.reopen');

        return $this->transition($message, 'in_review', $actor, $this->reason($reason), 'message.reopened');
    }

    public function readerReopen(ContactMessage $message, User $actor, string $reason): ContactMessage
    {
        abort_unless((int) ($message->user_id ?: $message->sender_id) === (int) $actor->getKey(), 404);
        abort_unless($actor->can('messages.reply_own'), 403);

        return DB::transaction(function () use ($message, $actor, $reason): ContactMessage {
            $locked = $this->transitionLocked($message, 'in_review', $actor, $this->reason($reason), 'message.reopened');
            $this->entry($locked, $actor, 'user_message', $reason, 'public', ['reopen_request' => true]);
            $this->notifications->staff($locked, 'message_reopened');

            return $locked;
        });
    }

    public function userReply(ContactMessage $message, User $actor, string $body): MessageThreadEntry
    {
        abort_unless((int) $message->user_id === (int) $actor->getKey() || (int) $message->sender_id === (int) $actor->getKey(), 404);

        return DB::transaction(function () use ($message, $actor, $body): MessageThreadEntry {
            $locked = $this->lock($message);
            abort_if(in_array($locked->status, ['resolved', 'rejected', 'closed'], true), 409);
            $entry = $this->entry($locked, $actor, 'user_message', $this->body($body), 'public');
            $updates = ['last_user_message_at' => now('UTC'), 'lock_version' => $locked->lock_version + 1];
            if ($locked->status === 'waiting_for_user') {
                if ($locked->sla_paused_at) {
                    $minutes = $locked->sla_paused_at->diffInMinutes(now('UTC'));
                    $updates['sla_paused_minutes'] = $locked->sla_paused_minutes + $minutes;
                    $updates['due_at'] = $locked->due_at?->addMinutes($minutes);
                    $updates['sla_paused_at'] = null;
                }
                $updates['status'] = 'in_review';
            }
            $locked->update($updates);
            $this->auditEvent('message.user_replied', $locked, $actor, null, ['entry_id' => $entry->getKey()]);
            $this->notifications->staff($locked, 'message_user_replied');

            return $entry;
        });
    }

    public function feedback(ContactMessage $message, User $actor, int $score, ?string $comment): ContactMessage
    {
        abort_unless((int) $message->user_id === (int) $actor->getKey(), 404);
        abort_unless($message->status === 'resolved' && $message->satisfaction_score === null && (bool) Setting::valueFor('library_feedback_satisfaction_enabled', true), 409);
        abort_unless($score >= 1 && $score <= 5, 422);
        $message->update(['satisfaction_score' => $score, 'satisfaction_comment' => $comment ? trim(strip_tags($comment)) : null]);
        $this->auditEvent('message.feedback_submitted', $message, $actor, null, ['score' => $score]);

        return $message;
    }

    private function transition(ContactMessage $message, string $status, User $actor, ?string $reason, string $event): ContactMessage
    {
        return DB::transaction(fn () => $this->transitionLocked($message, $status, $actor, $reason, $event));
    }

    private function transitionLocked(ContactMessage $message, string $status, User $actor, ?string $reason, string $event): ContactMessage
    {
        $locked = $message->exists ? $this->lock($message) : $message;
        $from = $locked->status;
        if (! in_array($status, self::TRANSITIONS[$from] ?? [], true)) {
            throw ValidationException::withMessages(['status' => __('messages.validation.invalid_transition')]);
        }
        if ($status === 'resolved' && ($locked->requires_director_review || in_array($locked->type, ['complaint', 'question'], true)) && ! $actor->hasRole('director')) {
            throw ValidationException::withMessages(['status' => __('messages.validation.director_approval_required')]);
        }
        $updates = ['status' => $status, 'lock_version' => $locked->lock_version + 1];
        if ($status === 'in_review') {
            $updates['reviewed_by'] = $locked->reviewed_by ?: $actor->getKey();
            $updates['reviewed_at'] = $locked->reviewed_at ?: now('UTC');
            $updates['assigned_to'] = $locked->assigned_to ?: $actor->getKey();
            $updates['assigned_at'] = $locked->assigned_at ?: now('UTC');
        }
        if ($status === 'resolved') {
            $updates += ['resolved_by' => $actor->getKey(), 'resolved_at' => now('UTC'), 'first_response_at' => $locked->first_response_at ?: now('UTC'), 'last_response_at' => now('UTC'), 'last_staff_message_at' => now('UTC')];
        }
        if ($status === 'closed') {
            $updates['closed_at'] = now('UTC');
        }
        if ($status === 'in_review' && in_array($from, ['resolved', 'rejected'], true)) {
            $updates += ['resolved_by' => null, 'resolved_at' => null, 'rejected_by' => null, 'rejected_at' => null, 'rejection_reason' => null, 'closed_at' => null];
        }
        $locked->update($updates);
        $this->entry($locked, $actor, 'status_change', $reason, 'system', ['old' => $from, 'new' => $status]);
        $this->auditEvent($event, $locked, $actor, ['status' => $from], ['status' => $status], $reason);

        return $locked;
    }

    /** @param array<string, mixed> $metadata */
    private function entry(ContactMessage $message, User $actor, string $type, ?string $body, string $visibility, array $metadata = [], bool $official = false, ?MessageThreadEntry $supersedes = null): MessageThreadEntry
    {
        return MessageThreadEntry::query()->create([
            'contact_message_id' => $message->getKey(), 'author_id' => $actor->getKey(),
            'author_type' => $actor->hasRole('member') ? 'user' : 'staff', 'entry_type' => $type,
            'body' => $body, 'visibility' => $visibility, 'is_official_response' => $official,
            'version' => $supersedes ? $supersedes->version + 1 : 1, 'supersedes_id' => $supersedes?->getKey(),
            'metadata' => array_merge($metadata, ['actor_role' => $actor->getRoleNames()->first(), 'request_id' => request()->attributes->get('request_id')]),
        ]);
    }

    private function lock(ContactMessage $message): ContactMessage
    {
        return ContactMessage::query()->whereKey($message->getKey())->lockForUpdate()->firstOrFail();
    }

    private function authorize(User $actor, string $permission): void
    {
        abort_unless($actor->can($permission), 403);
    }

    private function reason(string $reason): string
    {
        $reason = trim(strip_tags($reason));
        if (mb_strlen($reason) < 5) {
            throw ValidationException::withMessages(['reason' => __('messages.validation.reason_required')]);
        }

        return mb_substr($reason, 0, 3000);
    }

    private function body(string $body): string
    {
        $body = trim(strip_tags($body));
        if (mb_strlen($body) < 2 || mb_strlen($body) > 20000) {
            throw ValidationException::withMessages(['body' => __('messages.validation.body_length')]);
        }

        return $body;
    }

    /** @param array<string, mixed>|null $old @param array<string, mixed>|null $new */
    private function auditEvent(string $event, ContactMessage $message, User $actor, ?array $old = null, ?array $new = null, ?string $reason = null): void
    {
        $this->audit->logRequired($event, 'contact_message', $message->getKey(), $old, $new, $reason, 'operational', ['ticket_number' => $message->ticket_number, 'public_id' => $message->public_id], $actor);
    }
}
