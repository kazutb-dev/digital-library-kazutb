<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ContactMessage;
use App\Models\MessageAttachment;
use App\Models\MessageCategory;
use App\Models\MessageRoutingRule;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Messages\MessageWorkflowService;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactMessageController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $this->filters($request);
        $query = $this->visibleQuery($request)->with(['sender', 'assignee']);
        if (Schema::hasTable('message_categories')) {
            $query->with('messageCategory');
        }
        if (Schema::hasTable('branches')) {
            $query->with('branch');
        }
        if (Schema::hasTable('message_attachments')) {
            $query->withCount('messageAttachments');
        }
        $this->apply($query, $filters);

        return view('admin.messages.index', [
            'messages' => $query->orderByDesc('updated_at')->paginate(Setting::resultsPerPage())->withQueryString(),
            'filters' => $filters, 'categories' => Schema::hasTable('message_categories') ? MessageCategory::query()->orderBy('sort_order')->get() : collect(),
            'staff' => $this->technicalStaff(),
        ]);
    }

    public function show(Request $request, ContactMessage $message): View
    {
        $this->authorizeView($request, $message);
        $message->load(['sender.readerProfile', 'assignee.roles', 'messageCategory', 'branch', 'messageAttachments', 'threadEntries.author.roles', 'threadEntries.attachments']);

        return view('admin.messages.show', [
            'message' => $message, 'staff' => $this->technicalStaff(),
            'thread' => $message->threadEntries->where('visibility', '!=', 'director_only'),
            'history' => ActivityLog::query()->forEntity('contact_message', $message->getKey())->latest('occurred_at')->get(),
        ]);
    }

    public function update(Request $request, ContactMessage $message, MessageWorkflowService $workflow): RedirectResponse
    {
        $this->authorizeView($request, $message);
        $data = $request->validate([
            'assigned_to' => ['nullable', 'integer', 'exists:users,id'], 'assignment_reason' => ['nullable', 'required_with:assigned_to', 'string', 'min:5', 'max:3000'],
            'priority' => ['nullable', Rule::in(ContactMessage::PRIORITIES)], 'priority_reason' => ['nullable', 'required_with:priority', 'string', 'min:5', 'max:3000'],
        ]);
        if (array_key_exists('assigned_to', $data)) {
            $workflow->assign($message, $data['assigned_to'] ? User::query()->findOrFail($data['assigned_to']) : null, $request->user(), (string) ($data['assignment_reason'] ?? __('messages.system.admin_assignment')));
        }
        if (filled($data['priority'] ?? null) && $data['priority'] !== $message->priority) {
            $workflow->changePriority($message->refresh(), $data['priority'], $request->user(), (string) $data['priority_reason']);
        }

        return back()->with('success', __('common.updated_successfully'));
    }

    public function attachment(Request $request, ContactMessage $message, MessageAttachment $attachment): StreamedResponse
    {
        $this->authorizeView($request, $message);
        abort_unless($request->user()->can('messages.download_attachments') && (int) $attachment->contact_message_id === (int) $message->getKey() && $attachment->visibility !== 'director_only', 404);
        abort_unless(Storage::disk($attachment->disk)->exists($attachment->path), 404);

        return Storage::disk($attachment->disk)->download($attachment->path, $attachment->original_name, ['Cache-Control' => 'private, no-store', 'X-Robots-Tag' => 'noindex, nofollow']);
    }

    public function categories(): View
    {
        return view('admin.messages.categories', ['categories' => MessageCategory::query()->orderBy('message_type')->orderBy('sort_order')->get()]);
    }

    public function storeCategory(Request $request, AuditLogger $audit): RedirectResponse
    {
        $data = $this->categoryData($request);
        $category = MessageCategory::query()->create($data);
        $audit->logRequired('message.category_created', 'message_category', $category->getKey(), newValues: $category->toArray(), scope: 'operational');

        return back()->with('success', __('common.created_successfully'));
    }

    public function updateCategory(Request $request, MessageCategory $category, AuditLogger $audit): RedirectResponse
    {
        $old = $category->toArray();
        $category->update($this->categoryData($request, $category));
        $audit->logRequired('message.category_updated', 'message_category', $category->getKey(), $old, $category->fresh()->toArray(), scope: 'operational');

        return back()->with('success', __('common.updated_successfully'));
    }

    public function destroyCategory(MessageCategory $category): RedirectResponse
    {
        if ($category->messages()->exists()) {
            throw ValidationException::withMessages(['category' => __('messages.validation.category_in_use')]);
        }
        $category->delete();

        return back()->with('success', __('common.deleted_successfully'));
    }

    public function routing(): View
    {
        return view('admin.messages.routing', [
            'rules' => MessageRoutingRule::query()->with(['category'])->orderBy('sort_order')->get(),
            'categories' => MessageCategory::query()->active()->orderBy('sort_order')->get(),
            'roles' => ['librarian', 'senior_librarian', 'director', 'admin', 'bibliographer'],
        ]);
    }

    public function storeRouting(Request $request, AuditLogger $audit): RedirectResponse
    {
        $rule = MessageRoutingRule::query()->create($this->routingData($request));
        $audit->logRequired('message.routing_created', 'message_routing_rule', $rule->getKey(), newValues: $rule->toArray(), scope: 'operational');

        return back()->with('success', __('common.created_successfully'));
    }

    public function updateRouting(Request $request, MessageRoutingRule $rule, AuditLogger $audit): RedirectResponse
    {
        $old = $rule->toArray();
        $rule->update($this->routingData($request));
        $audit->logRequired('message.routing_updated', 'message_routing_rule', $rule->getKey(), $old, $rule->fresh()->toArray(), scope: 'operational');

        return back()->with('success', __('common.updated_successfully'));
    }

    public function analytics(Request $request): View
    {
        $filters = $this->filters($request);
        $query = $this->visibleQuery($request);
        $this->apply($query, $filters);
        $base = clone $query;
        $resolved = (clone $base)->whereNotNull('resolved_at');
        $driver = DB::connection()->getDriverName();
        $firstResponseExpression = $driver === 'pgsql'
            ? 'AVG(EXTRACT(EPOCH FROM (first_response_at - created_at))/60) value'
            : 'AVG((julianday(first_response_at) - julianday(created_at)) * 1440) value';
        $resolutionExpression = $driver === 'pgsql'
            ? 'AVG(EXTRACT(EPOCH FROM (resolved_at - created_at))/60) value'
            : 'AVG((julianday(resolved_at) - julianday(created_at)) * 1440) value';

        return view('admin.messages.analytics', [
            'filters' => $filters, 'categories' => MessageCategory::query()->active()->orderBy('sort_order')->get(),
            'total' => (clone $base)->count(), 'overdue' => (clone $base)->whereNotIn('status', ['resolved', 'rejected', 'closed'])->where('due_at', '<', now('UTC'))->count(),
            'byType' => (clone $base)->selectRaw('type, count(*) total')->groupBy('type')->pluck('total', 'type'),
            'byStatus' => (clone $base)->selectRaw('status, count(*) total')->groupBy('status')->pluck('total', 'status'),
            'byPriority' => (clone $base)->selectRaw('priority, count(*) total')->groupBy('priority')->pluck('total', 'priority'),
            'averageFirstResponseMinutes' => (int) ((clone $resolved)->whereNotNull('first_response_at')->selectRaw($firstResponseExpression)->value('value') ?? 0),
            'averageResolutionMinutes' => (int) ((clone $resolved)->selectRaw($resolutionExpression)->value('value') ?? 0),
            'satisfaction' => round((float) ((clone $base)->whereNotNull('satisfaction_score')->avg('satisfaction_score') ?? 0), 2),
        ]);
    }

    public function export(Request $request, AuditLogger $audit): StreamedResponse
    {
        $filters = $this->filters($request);
        $query = $this->visibleQuery($request)->with(['messageCategory', 'assignee', 'branch']);
        $this->apply($query, $filters);
        $audit->logRequired('message.exported', 'contact_message_export', now('UTC')->format('YmdHis'), newValues: ['filters' => $filters], scope: 'operational');

        return response()->streamDownload(function () use ($query): void {
            $stream = fopen('php://output', 'wb');
            fwrite($stream, "\xEF\xBB\xBF");
            Csv::writeRow($stream, ['ticket_number', 'type', 'category', 'subject', 'sender', 'email', 'ticket', 'status', 'priority', 'assignee', 'branch', 'created_utc', 'due_utc', 'first_response_utc', 'resolved_utc', 'satisfaction']);
            $query->orderBy('id')->chunk(250, function ($messages) use ($stream): void {
                foreach ($messages as $message) {
                    Csv::writeRow($stream, [$message->ticket_number, $message->type, $message->messageCategory?->localizedName('ru'), $message->subject, $message->sender_name_snapshot, $message->sender_email_snapshot, $message->reader_ticket_snapshot, $message->status, $message->priority, $message->assignee?->name, $message->branch?->name, $message->created_at?->utc()->toIso8601String(), $message->due_at?->utc()->toIso8601String(), $message->first_response_at?->utc()->toIso8601String(), $message->resolved_at?->utc()->toIso8601String(), $message->satisfaction_score]);
                }
            });
            fclose($stream);
        }, 'library-messages-'.now('UTC')->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8', 'Cache-Control' => 'private, no-store']);
    }

    /** @return array<string,mixed> */
    private function filters(Request $request): array
    {
        return $request->validate([
            'search' => ['nullable', 'string', 'max:200'], 'type' => ['nullable', Rule::in(ContactMessage::TYPES)],
            'category_id' => ['nullable', 'integer', 'exists:message_categories,id'], 'status' => ['nullable', Rule::in(ContactMessage::STATUSES)],
            'priority' => ['nullable', Rule::in(ContactMessage::PRIORITIES)], 'assigned_to' => ['nullable', 'integer', 'exists:users,id'],
            'date_from' => ['nullable', 'date'], 'date_to' => ['nullable', 'date', 'after_or_equal:date_from'], 'overdue' => ['nullable', 'boolean'],
        ]);
    }

    /** @param array<string,mixed> $filters */
    private function apply(Builder $query, array $filters): void
    {
        foreach (['type', 'category_id', 'status', 'priority', 'assigned_to'] as $field) {
            if (filled($filters[$field] ?? null)) {
                $query->where($field, $filters[$field]);
            }
        }
        if ($term = trim((string) ($filters['search'] ?? ''))) {
            $query->search($term);
        }
        if ($filters['date_from'] ?? null) {
            $query->whereDate('created_at', '>=', $filters['date_from']);
        }
        if ($filters['date_to'] ?? null) {
            $query->whereDate('created_at', '<=', $filters['date_to']);
        }
        if ((bool) ($filters['overdue'] ?? false)) {
            $query->whereNotIn('status', ['resolved', 'rejected', 'closed'])->where('due_at', '<', now('UTC'));
        }
    }

    private function visibleQuery(Request $request): Builder
    {
        return ContactMessage::query()->where(function (Builder $query) use ($request): void {
            $query->where('assigned_to', $request->user()->getKey());
            if (Schema::hasTable('message_watchers')) {
                $query->orWhereHas('watchers', fn (Builder $watchers) => $watchers->whereKey($request->user()->getKey()));
            }
            if (Schema::hasTable('message_categories')) {
                $query->orWhereHas('messageCategory', fn (Builder $category) => $category->where('default_assignee_role', 'admin'));
            }
        })->where(fn (Builder $query) => $query->where('sensitive', false)->orWhere('assigned_to', $request->user()->getKey()));
    }

    private function authorizeView(Request $request, ContactMessage $message): void
    {
        abort_unless($this->visibleQuery($request)->whereKey($message->getKey())->exists(), 404);
    }

    private function technicalStaff()
    {
        return User::query()->where('is_active', true)->whereHas('roles', fn (Builder $query) => $query->whereIn('name', ['admin', 'librarian', 'senior_librarian']))->with('roles')->orderBy('name')->get();
    }

    /** @return array<string,mixed> */
    private function categoryData(Request $request, ?MessageCategory $category = null): array
    {
        return $request->validate([
            'slug' => ['required', 'alpha_dash', 'max:96', Rule::unique('message_categories', 'slug')->ignore($category)],
            'message_type' => ['required', Rule::in(ContactMessage::TYPES)], 'name_kk' => ['required', 'string', 'max:255'],
            'name_ru' => ['required', 'string', 'max:255'], 'name_en' => ['required', 'string', 'max:255'],
            'active' => ['required', 'boolean'], 'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
            'default_priority' => ['required', Rule::in(ContactMessage::PRIORITIES)],
            'default_assignee_role' => ['nullable', Rule::in(['librarian', 'senior_librarian', 'director', 'admin', 'bibliographer'])],
            'requires_director_review' => ['required', 'boolean'], 'sla_hours' => ['required', 'integer', 'min:1', 'max:2160'],
            'allowed_attachment_types' => ['nullable', 'array'], 'allowed_attachment_types.*' => [Rule::in(['jpg', 'jpeg', 'png', 'webp', 'pdf', 'docx'])],
        ]);
    }

    /** @return array<string,mixed> */
    private function routingData(Request $request): array
    {
        return $request->validate([
            'name' => ['required', 'string', 'max:255'], 'message_type' => ['nullable', Rule::in(ContactMessage::TYPES)],
            'category_id' => ['nullable', 'integer', 'exists:message_categories,id'], 'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'priority' => ['nullable', Rule::in(ContactMessage::PRIORITIES)], 'target_role' => ['required', Rule::in(['librarian', 'senior_librarian', 'director', 'admin', 'bibliographer'])],
            'director_visibility' => ['required', 'boolean'], 'active' => ['required', 'boolean'], 'sort_order' => ['required', 'integer', 'min:0', 'max:10000'],
        ]);
    }
}
