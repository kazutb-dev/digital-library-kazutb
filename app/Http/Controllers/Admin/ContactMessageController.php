<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\ContactMessage;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ContactMessageController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'category' => ['nullable', Rule::in($this->categories())],
            'status' => ['nullable', Rule::in(['open', 'in_review', 'resolved', 'archived'])],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort' => ['nullable', Rule::in(['created_at', 'updated_at', 'priority', 'status'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);

        $messages = $this->filteredQuery($filters)
            ->with(['sender', 'assignee'])
            ->orderBy($filters['sort'] ?? 'created_at', $filters['direction'] ?? 'desc')
            ->paginate(Setting::resultsPerPage())
            ->withQueryString();

        return view('admin.messages.index', [
            'messages' => $messages,
            'filters' => $filters,
            'staff' => User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn (Builder $query) => $query->where('name', '!=', 'member'))
                ->orderBy('name')
                ->get(),
            'categories' => $this->categories(),
        ]);
    }

    public function show(ContactMessage $message): View
    {
        $message->load(['sender', 'assignee']);

        return view('admin.messages.show', [
            'message' => $message,
            'staff' => User::query()
                ->where('is_active', true)
                ->whereHas('roles', fn (Builder $query) => $query->where('name', '!=', 'member'))
                ->orderBy('name')
                ->get(),
            'history' => ActivityLog::query()
                ->where('entity_type', 'contact_message')
                ->where('entity_id', (string) $message->getKey())
                ->latest('occurred_at')
                ->get(),
            'categories' => $this->categories(),
        ]);
    }

    public function update(Request $request, ContactMessage $message, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['open', 'in_review', 'resolved', 'archived'])],
            'assigned_to' => ['nullable', Rule::exists('users', 'id')],
            'priority' => ['required', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'resolution_comment' => ['nullable', 'required_if:status,resolved', 'string', 'max:5000'],
        ]);
        $validated['assigned_to'] = $validated['assigned_to'] ?? null;
        $validated['resolution_comment'] = $validated['resolution_comment'] ?? null;
        if (in_array($validated['status'], ['open', 'in_review'], true)) {
            $validated['resolution_comment'] = null;
        }

        DB::transaction(function () use ($message, $validated, $audit): void {
            ContactMessage::query()->whereKey($message->getKey())->lockForUpdate()->firstOrFail();
            $message->refresh();

            $allowedTransitions = [
                'open' => ['open', 'in_review', 'archived'],
                'in_review' => ['open', 'in_review', 'resolved', 'archived'],
                'resolved' => ['resolved', 'in_review', 'archived'],
                'archived' => ['archived', 'open'],
            ];
            if (! in_array($validated['status'], $allowedTransitions[$message->status] ?? [], true)) {
                throw ValidationException::withMessages([
                    'status' => __('messages.messages.invalid_transition'),
                ]);
            }

            if ($validated['assigned_to'] !== null) {
                $validAssignee = User::query()
                    ->whereKey($validated['assigned_to'])
                    ->where('is_active', true)
                    ->whereHas('roles', fn (Builder $query) => $query->where('name', '!=', 'member'))
                    ->lockForUpdate()
                    ->first(['users.id']) !== null;
                if (! $validAssignee) {
                    throw ValidationException::withMessages([
                        'assigned_to' => __('messages.messages.invalid_assignee'),
                    ]);
                }
            }

            $old = $this->snapshot($message);
            $validated['resolved_at'] = match ($validated['status']) {
                'resolved' => $message->resolved_at ?? now('UTC'),
                'archived' => $message->resolved_at,
                default => null,
            };
            $message->update($validated);
            $message->refresh();

            $audit->logRequired(
                actionType: $old['status'] !== $message->status ? 'status.update' : 'update',
                entityType: 'contact_message',
                entityId: $message->getKey(),
                oldValues: $old,
                newValues: $this->snapshot($message),
                scope: 'operational',
            );
        });

        return back()->with('success', __('common.updated_successfully'));
    }

    public function destroy(Request $request, ContactMessage $message, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);
        DB::transaction(function () use ($message, $validated, $audit): void {
            ContactMessage::query()->whereKey($message->getKey())->lockForUpdate()->firstOrFail();
            $message->refresh();
            $snapshot = $this->snapshot($message);
            $message->delete();

            $audit->logRequired(
                actionType: 'delete',
                entityType: 'contact_message',
                entityId: $message->getKey(),
                oldValues: $snapshot,
                reason: $validated['reason'],
                scope: 'operational',
            );
        });

        return redirect()->route('admin.messages.index')->with('success', __('common.deleted_successfully'));
    }

    public function attachment(ContactMessage $message, int $index): BinaryFileResponse|StreamedResponse
    {
        $attachments = $message->attachments ?? [];
        abort_unless(array_key_exists($index, $attachments), 404);

        $attachment = $attachments[$index];
        $path = is_array($attachment) ? ($attachment['path'] ?? null) : $attachment;
        $name = is_array($attachment) ? ($attachment['name'] ?? basename((string) $path)) : basename((string) $path);
        abort_unless(is_string($path) && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->download($path, $name);
    }

    public function export(Request $request, AuditLogger $audit): StreamedResponse
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:160'],
            'category' => ['nullable', Rule::in($this->categories())],
            'status' => ['nullable', Rule::in(['open', 'in_review', 'resolved', 'archived'])],
            'priority' => ['nullable', Rule::in(['low', 'normal', 'high', 'urgent'])],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $rows = $this->filteredQuery($filters)->with('assignee')->orderByDesc('created_at')->cursor();

        $audit->logRequired(
            actionType: 'export',
            entityType: 'report',
            entityId: 'contact_messages',
            newValues: ['format' => 'csv', 'filters' => $filters],
            scope: 'system',
        );

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            Csv::writeRow($output, [
                __('reports.columns.id'),
                __('reports.columns.received_utc'),
                __('reports.columns.category'),
                __('reports.columns.subject'),
                __('reports.columns.sender_email'),
                __('reports.columns.status'),
                __('reports.columns.priority'),
                __('reports.columns.assigned_to'),
                __('reports.columns.resolved_utc'),
            ]);

            foreach ($rows as $message) {
                Csv::writeRow($output, [
                    $message->getKey(),
                    $message->created_at?->utc()->toIso8601String(),
                    trans()->has('messages.categories.'.$message->category)
                        ? __('messages.categories.'.$message->category)
                        : $message->category,
                    $message->subject,
                    $message->sender_email,
                    __('messages.statuses.'.$message->status),
                    __('messages.priorities.'.$message->priority),
                    $message->assignee?->name,
                    $message->resolved_at?->utc()->toIso8601String(),
                ]);
            }

            fclose($output);
        }, 'contact-messages-'.now('UTC')->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(array $filters): Builder
    {
        $query = ContactMessage::query();

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($needle): void {
                $builder->whereRaw('LOWER(subject) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(body) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(sender_email) LIKE ?', [$needle]);
            });
        }

        foreach (['category', 'status', 'priority'] as $field) {
            if (! empty($filters[$field])) {
                $query->where($field, $filters[$field]);
            }
        }

        if (! empty($filters['date_from'])) {
            $query->where('created_at', '>=', Carbon::parse($filters['date_from'], 'UTC')->startOfDay());
        }
        if (! empty($filters['date_to'])) {
            $query->where('created_at', '<=', Carbon::parse($filters['date_to'], 'UTC')->endOfDay());
        }

        return $query;
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(ContactMessage $message): array
    {
        return [
            'status' => $message->status,
            'priority' => $message->priority,
            'assigned_to' => $message->assigned_to,
            'resolution_comment' => $message->resolution_comment,
            'resolved_at' => $message->resolved_at?->utc()->toIso8601String(),
        ];
    }

    /**
     * @return list<string>
     */
    private function categories(): array
    {
        $configured = Setting::valueFor(
            'message_categories',
            ['request', 'complaint', 'suggestion', 'question', 'other'],
        );

        return collect(is_array($configured) ? $configured : [])
            ->map(fn (mixed $value): string => mb_strtolower(trim((string) $value)))
            ->filter(fn (string $value): bool => $value !== '' && mb_strlen($value) <= 32)
            ->unique()
            ->values()
            ->all() ?: ['request', 'complaint', 'suggestion', 'question', 'other'];
    }
}
