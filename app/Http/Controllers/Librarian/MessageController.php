<?php

namespace App\Http\Controllers\Librarian;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\MessageAttachment;
use App\Models\MessageCategory;
use App\Models\Setting;
use App\Models\User;
use App\Services\Messages\MessageAttachmentService;
use App\Services\Messages\MessageWorkflowService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class MessageController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'], 'type' => ['nullable', Rule::in(ContactMessage::TYPES)],
            'category_id' => ['nullable', 'integer', 'exists:message_categories,id'], 'status' => ['nullable', Rule::in(ContactMessage::STATUSES)],
            'priority' => ['nullable', Rule::in(ContactMessage::PRIORITIES)], 'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'], 'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'], 'overdue' => ['nullable', 'boolean'],
            'unassigned' => ['nullable', 'boolean'], 'requires_director' => ['nullable', 'boolean'],
            'has_attachments' => ['nullable', 'boolean'], 'waiting_for_user' => ['nullable', 'boolean'], 'mine' => ['nullable', 'boolean'],
        ]);
        $query = $this->visibleQuery($request)->with(['sender', 'assignee', 'messageCategory', 'branch'])->withCount('messageAttachments');
        $this->applyFilters($query, $filters, $request);

        $summaryQuery = $this->visibleQuery($request);
        $summary = [
            'open' => (clone $summaryQuery)->where('status', 'open')->count(),
            'mine' => (clone $summaryQuery)->where('assigned_to', $request->user()->getKey())->whereNotIn('status', ['resolved', 'rejected', 'closed'])->count(),
            'unassigned' => (clone $summaryQuery)->whereNull('assigned_to')->whereNotIn('status', ['resolved', 'rejected', 'closed'])->count(),
            'overdue' => (clone $summaryQuery)->whereNotIn('status', ['resolved', 'rejected', 'closed'])->where('due_at', '<', now('UTC'))->count(),
            'critical' => (clone $summaryQuery)->whereIn('priority', ['high', 'critical'])->whereNotIn('status', ['resolved', 'rejected', 'closed'])->count(),
            'approval' => (clone $summaryQuery)->where('status', 'response_prepared')->count(),
        ];

        return view('librarian.messages.index', [
            'messages' => $query->orderByRaw("CASE priority WHEN 'critical' THEN 0 WHEN 'high' THEN 1 WHEN 'medium' THEN 2 ELSE 3 END")
                ->orderByRaw('CASE WHEN due_at IS NOT NULL AND due_at < CURRENT_TIMESTAMP THEN 0 ELSE 1 END')
                ->orderByDesc('updated_at')->paginate(Setting::resultsPerPage())->withQueryString(),
            'filters' => $filters, 'summary' => $summary,
            'categories' => MessageCategory::query()->active()->orderBy('sort_order')->get(),
            'staff' => $this->staff(),
        ]);
    }

    public function show(Request $request, ContactMessage $message): View
    {
        $this->authorizeView($request, $message);
        $message->load(['sender.readerProfile', 'assignee.roles', 'messageCategory', 'branch', 'complaintAgainst', 'messageAttachments', 'threadEntries.author.roles', 'threadEntries.attachments', 'auditLogs']);
        $thread = $message->threadEntries;
        if (! $request->user()->hasRole('director') && ! $request->user()->can('messages.view_sensitive_complaints')) {
            $thread = $thread->where('visibility', '!=', 'director_only');
        }

        return view('librarian.messages.show', ['message' => $message, 'thread' => $thread, 'staff' => $this->staff()]);
    }

    public function take(Request $request, ContactMessage $message, MessageWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeView($request, $message);
        $workflow->takeInReview($message, $request->user());

        return back()->with('success', __('messages.messages.taken_in_review'));
    }

    public function assign(Request $request, ContactMessage $message, MessageWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeView($request, $message);
        $data = $request->validate(['assigned_to' => ['nullable', 'integer', 'exists:users,id'], 'reason' => ['required', 'string', 'min:5', 'max:3000']]);
        $workflow->assign($message, filled($data['assigned_to'] ?? null) ? User::query()->findOrFail($data['assigned_to']) : null, $request->user(), $data['reason']);

        return back()->with('success', __('messages.messages.assignment_updated'));
    }

    public function priority(Request $request, ContactMessage $message, MessageWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeView($request, $message);
        $data = $request->validate(['priority' => ['required', Rule::in(ContactMessage::PRIORITIES)], 'reason' => ['required', 'string', 'min:5', 'max:3000']]);
        $workflow->changePriority($message, $data['priority'], $request->user(), $data['reason']);

        return back()->with('success', __('messages.messages.priority_updated'));
    }

    public function reply(Request $request, ContactMessage $message, MessageWorkflowService $workflow, MessageAttachmentService $attachments): RedirectResponse
    {
        $this->authorizeView($request, $message);
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:20000'], 'attachments' => ['nullable', 'array', 'max:10'], 'attachments.*' => ['file', 'max:51200']]);
        $entry = $workflow->addStaffReply($message, $request->user(), $data['body']);
        $attachments->store($message, $entry, $request->file('attachments', []), $request->user());

        return back()->with('success', __('messages.messages.reply_sent'));
    }

    public function clarification(Request $request, ContactMessage $message, MessageWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeView($request, $message);
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:20000']]);
        $workflow->requestClarification($message, $request->user(), $data['body']);

        return back()->with('success', __('messages.messages.clarification_sent'));
    }

    public function note(Request $request, ContactMessage $message, MessageWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeView($request, $message);
        $data = $request->validate(['body' => ['required', 'string', 'min:2', 'max:20000'], 'visibility' => ['required', Rule::in(['internal', 'director_only'])]]);
        $workflow->addInternalNote($message, $request->user(), $data['body'], $data['visibility']);

        return back()->with('success', __('messages.messages.note_added'));
    }

    public function prepare(Request $request, ContactMessage $message, MessageWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeView($request, $message);
        $data = $request->validate(['body' => ['required', 'string', 'min:5', 'max:20000']]);
        $workflow->prepareOfficialResponse($message, $request->user(), $data['body']);

        return back()->with('success', __('messages.messages.response_prepared'));
    }

    public function approve(Request $request, ContactMessage $message, MessageWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeView($request, $message);
        $workflow->approveOfficialResponse($message, $request->user());

        return back()->with('success', __('messages.messages.response_approved'));
    }

    public function returnResponse(Request $request, ContactMessage $message, MessageWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeView($request, $message);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:3000']]);
        $workflow->returnOfficialResponse($message, $request->user(), $data['reason']);

        return back()->with('success', __('messages.messages.response_returned'));
    }

    public function reject(Request $request, ContactMessage $message, MessageWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeView($request, $message);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:3000']]);
        $workflow->reject($message, $request->user(), $data['reason']);

        return back()->with('success', __('messages.messages.rejected'));
    }

    public function close(Request $request, ContactMessage $message, MessageWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeView($request, $message);
        $workflow->close($message, $request->user());

        return back()->with('success', __('messages.messages.closed'));
    }

    public function reopen(Request $request, ContactMessage $message, MessageWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeView($request, $message);
        $data = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:3000']]);
        $workflow->reopen($message, $request->user(), $data['reason']);

        return back()->with('success', __('messages.messages.reopened'));
    }

    public function attachment(Request $request, ContactMessage $message, MessageAttachment $attachment): StreamedResponse
    {
        $this->authorizeView($request, $message);
        abort_unless($request->user()->can('messages.download_attachments') && (int) $attachment->contact_message_id === (int) $message->getKey(), 404);
        if ($attachment->visibility === 'director_only') {
            abort_unless($request->user()->hasRole('director') || $request->user()->can('messages.view_sensitive_complaints'), 404);
        }
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name, ['Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex, nofollow']);
    }

    /** @param array<string, mixed> $filters */
    private function applyFilters(Builder $query, array $filters, Request $request): void
    {
        foreach (['type', 'category_id', 'status', 'priority', 'assigned_to', 'branch_id'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where($field, $filters[$field]);
            }
        }
        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->search($search);
        }
        if ($filters['date_from'] ?? null) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if ($filters['date_to'] ?? null) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
        if ($request->boolean('overdue')) {
            $query->whereNotIn('status', ['resolved', 'rejected', 'closed'])->where('due_at', '<', now('UTC'));
        }
        if ($request->boolean('unassigned')) {
            $query->whereNull('assigned_to');
        }
        if ($request->boolean('requires_director')) {
            $query->where('requires_director_review', true);
        }
        if ($request->boolean('has_attachments')) {
            $query->has('messageAttachments');
        }
        if ($request->boolean('waiting_for_user')) {
            $query->where('status', 'waiting_for_user');
        }
        if ($request->boolean('mine')) {
            $query->where('assigned_to', $request->user()->getKey());
        }
    }

    private function visibleQuery(Request $request): Builder
    {
        $user = $request->user();
        $query = ContactMessage::query();
        if (! $user->can('messages.view_all')) {
            $query->where(fn (Builder $builder) => $builder->where('assigned_to', $user->getKey())->orWhereHas('watchers', fn (Builder $watchers) => $watchers->whereKey($user->getKey())));
        }
        if (! $user->can('messages.view_sensitive_complaints')) {
            $query->where(fn (Builder $builder) => $builder->where('sensitive', false)->orWhere('assigned_to', $user->getKey()));
        }
        if ($user->hasRole('bibliographer') && ! $user->hasAnyRole(['admin', 'director', 'librarian', 'senior_librarian'])) {
            $query->whereIn('type', ['request', 'question']);
        }

        return $query;
    }

    private function authorizeView(Request $request, ContactMessage $message): void
    {
        abort_unless($this->visibleQuery($request)->whereKey($message->getKey())->exists(), 404);
    }

    private function staff()
    {
        return User::query()->where('is_active', true)->whereHas('roles', fn (Builder $query) => $query->where('name', '!=', 'member'))
            ->with('roles')->withCount(['assignedContactMessages as active_message_load' => fn (Builder $query) => $query->whereNotIn('status', ['resolved', 'rejected', 'closed'])])
            ->orderBy('name')->get();
    }
}
