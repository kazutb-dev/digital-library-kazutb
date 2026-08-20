<?php

namespace App\Services\Messages;

use App\Models\ContactMessage;
use App\Models\MessageRoutingRule;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;

class MessageRoutingService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function route(ContactMessage $message, ?User $actor = null): ContactMessage
    {
        if (! (bool) Setting::valueFor('library_feedback_auto_assignment', true)) {
            $this->auditRoute($message, null, 'automatic_assignment_disabled', $actor);

            return $message;
        }

        $rule = MessageRoutingRule::query()->where('active', true)
            ->where(fn (Builder $query) => $query->whereNull('message_type')->orWhere('message_type', $message->type))
            ->where(fn (Builder $query) => $query->whereNull('category_id')->orWhere('category_id', $message->category_id))
            ->where(fn (Builder $query) => $query->whereNull('branch_id')->orWhere('branch_id', $message->branch_id))
            ->where(fn (Builder $query) => $query->whereNull('priority')->orWhere('priority', $message->priority))
            ->orderByRaw('CASE WHEN category_id IS NOT NULL THEN 0 ELSE 1 END')
            ->orderByRaw('CASE WHEN branch_id IS NOT NULL THEN 0 ELSE 1 END')
            ->orderBy('sort_order')->first();

        $targetRole = $rule?->target_role ?: $message->messageCategory?->default_assignee_role ?: match ($message->type) {
            'complaint' => 'senior_librarian', 'suggestion', 'question' => 'director', default => 'librarian',
        };
        $assignee = User::query()->where('is_active', true)->role($targetRole)
            ->when($message->complaint_against_user_id, fn (Builder $query) => $query->whereKeyNot($message->complaint_against_user_id))
            ->withCount(['assignedContactMessages as active_message_load' => fn (Builder $query) => $query->whereNotIn('status', ['resolved', 'rejected', 'closed'])])
            ->orderBy('active_message_load')->orderBy('id')->first();

        if ($assignee) {
            $message->update(['assigned_to' => $assignee->getKey(), 'assigned_by' => $actor?->getKey(), 'assigned_at' => now('UTC')]);
        }
        $needsDirector = $message->requires_director_review || $message->type === 'question' || $rule?->director_visibility || $message->priority === 'critical';
        if ($needsDirector) {
            User::query()->where('is_active', true)->role('director')->get()->each(fn (User $director) => $message->watchers()->syncWithoutDetaching([$director->getKey() => ['added_by' => $actor?->getKey(), 'reason' => 'automatic_director_visibility']]));
        }

        $this->auditRoute($message->refresh(), $rule, $assignee ? 'assigned' : 'general_queue', $actor);

        return $message;
    }

    private function auditRoute(ContactMessage $message, ?MessageRoutingRule $rule, string $result, ?User $actor): void
    {
        $this->audit->logRequired('message.routed', 'contact_message', $message->getKey(), newValues: [
            'assigned_to' => $message->assigned_to, 'target_role' => $rule?->target_role ?: $message->messageCategory?->default_assignee_role,
            'routing_rule_id' => $rule?->getKey(), 'result' => $result,
        ], scope: 'operational', actor: $actor, metadata: ['ticket_number' => $message->ticket_number]);
    }
}
