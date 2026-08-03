<?php

namespace App\Http\Controllers\Librarian;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Services\Catalog\LibraryNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Reader inquiries at the librarian desk (Historical §5.11): view and
 * resolve, never delete — deletion stays an admin capability.
 */
class MessageController extends Controller
{
    private const BIBLIOGRAPHIC_CATEGORIES = ['request', 'question'];

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['open', 'in_review', 'resolved', 'archived'])],
            'search' => ['nullable', 'string', 'max:200'],
        ]);

        $query = ContactMessage::query()->with(['sender', 'assignee']);
        $bibliographicScope = $this->usesBibliographicScope($request);

        if ($bibliographicScope) {
            $query->whereIn('category', self::BIBLIOGRAPHIC_CATEGORIES);
        }

        if ($status = ($filters['status'] ?? null)) {
            $query->where('status', $status);
        }
        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->search($search);
        }

        $statusCountsQuery = ContactMessage::query();
        if ($bibliographicScope) {
            $statusCountsQuery->whereIn('category', self::BIBLIOGRAPHIC_CATEGORIES);
        }

        return view('librarian.messages.index', [
            'messages' => $query->orderByRaw("CASE status WHEN 'open' THEN 0 WHEN 'in_review' THEN 1 ELSE 2 END")
                ->orderByDesc('created_at')
                ->paginate(Setting::resultsPerPage())
                ->withQueryString(),
            'filters' => $filters,
            'statusCounts' => $statusCountsQuery->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'bibliographicScope' => $bibliographicScope,
        ]);
    }

    public function show(Request $request, ContactMessage $message): View
    {
        if ($this->usesBibliographicScope($request)
            && ! in_array($message->category, self::BIBLIOGRAPHIC_CATEGORIES, true)) {
            abort(403);
        }

        return view('librarian.messages.show', ['message' => $message->load(['sender', 'assignee'])]);
    }

    public function update(Request $request, ContactMessage $message, AuditLogger $audit, LibraryNotificationService $notifications): RedirectResponse
    {
        $validated = $request->validate([
            'status' => ['required', Rule::in(['open', 'in_review', 'resolved'])],
            'resolution_comment' => ['nullable', 'required_if:status,resolved', 'string', 'max:5000'],
        ]);

        DB::transaction(function () use ($message, $validated, $request, $audit, $notifications): void {
            $old = $message->only(['status', 'assigned_to', 'resolution_comment']);

            $message->update([
                'status' => $validated['status'],
                'assigned_to' => $request->user()->getKey(),
                'resolution_comment' => $validated['status'] === 'resolved' ? ($validated['resolution_comment'] ?? null) : null,
                'resolved_at' => $validated['status'] === 'resolved' ? now() : null,
            ]);

            $audit->logRequired(
                actionType: 'status.update',
                entityType: 'contact_message',
                entityId: $message->getKey(),
                oldValues: $old,
                newValues: $message->only(['status', 'assigned_to', 'resolution_comment']),
                scope: 'operational',
            );

            if ($message->sender !== null && $old['status'] !== $message->status) {
                $notifications->sendLocalized(
                    $message->sender,
                    'message_status_changed',
                    'librarian.notifications.message_status_title',
                    'librarian.notifications.message_status_body',
                    [
                        'subject' => $message->subject,
                        'status' => ['_translation' => 'messages.statuses.'.$message->status],
                    ],
                    ['message_id' => $message->getKey()],
                );
            }
        });

        return back()->with('success', __('common.updated_successfully'));
    }

    private function usesBibliographicScope(Request $request): bool
    {
        $user = $request->user();

        return $user?->hasRole('bibliographer') === true
            && ! $user->hasAnyRole(['admin', 'director', 'librarian', 'senior_librarian']);
    }
}
