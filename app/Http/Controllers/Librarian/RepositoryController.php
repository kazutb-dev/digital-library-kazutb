<?php

namespace App\Http\Controllers\Librarian;

use App\Http\Controllers\Controller;
use App\Models\Catalog\RepositoryItem;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Services\Catalog\LibraryNotificationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Scientific repository moderation (Master.md §20): the librarian uploads on
 * behalf of the author, moderates metadata, and sends work up the approval
 * chain. Final publication stays with repository.publish (admin/director).
 */
class RepositoryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(RepositoryItem::STATUSES)],
            'work_type' => ['nullable', Rule::in(RepositoryItem::WORK_TYPES)],
            'search' => ['nullable', 'string', 'max:200'],
        ]);

        $query = RepositoryItem::query()->with(['uploadedBy', 'approvedBy']);

        if ($status = ($filters['status'] ?? null)) {
            $query->where('status', $status);
        }
        if ($type = ($filters['work_type'] ?? null)) {
            $query->where('work_type', $type);
        }
        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->search($search);
        }

        return view('librarian.repository.index', [
            'items' => $query->orderByRaw("CASE status WHEN 'under_review' THEN 0 WHEN 'draft' THEN 1 ELSE 2 END")
                ->orderByDesc('updated_at')
                ->paginate(Setting::resultsPerPage())
                ->withQueryString(),
            'filters' => $filters,
            'statusCounts' => RepositoryItem::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function create(): View
    {
        return view('librarian.repository.form', ['item' => new RepositoryItem(['language' => 'ru', 'work_type' => 'thesis'])]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $this->validated($request);

        $item = DB::transaction(function () use ($validated, $request, $audit): RepositoryItem {
            $item = new RepositoryItem([...$validated, 'status' => 'draft', 'uploaded_by' => $request->user()->getKey()]);

            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $item->file_path = $file->store('repository', 'local');
                $item->file_name = $file->getClientOriginalName();
                $item->file_size = $file->getSize();
            }

            $item->save();

            $audit->logRequired(
                actionType: 'repository.upload',
                entityType: 'repository_item',
                entityId: $item->getKey(),
                newValues: ['title' => $item->title, 'work_type' => $item->work_type, 'file' => $item->file_name],
                scope: 'library',
            );

            return $item;
        });

        return redirect()->route('librarian.repository.edit', $item)->with('success', __('common.created_successfully'));
    }

    public function edit(RepositoryItem $item): View
    {
        return view('librarian.repository.form', ['item' => $item]);
    }

    public function update(Request $request, RepositoryItem $item, AuditLogger $audit): RedirectResponse
    {
        if (in_array($item->status, ['published', 'archived'], true)) {
            return back()->withErrors(['title' => __('librarian.repository.locked_after_publish')]);
        }

        $validated = $this->validated($request);

        DB::transaction(function () use ($item, $validated, $request, $audit): void {
            $old = $item->only(array_keys($validated));
            $item->fill($validated);

            if ($request->hasFile('file')) {
                $file = $request->file('file');
                $item->file_path = $file->store('repository', 'local');
                $item->file_name = $file->getClientOriginalName();
                $item->file_size = $file->getSize();
            }

            $item->save();

            $audit->logRequired(
                actionType: 'metadata.update',
                entityType: 'repository_item',
                entityId: $item->getKey(),
                oldValues: $old,
                newValues: $item->only(array_keys($validated)),
                scope: 'library',
            );
        });

        return redirect()->route('librarian.repository.edit', $item)->with('success', __('common.updated_successfully'));
    }

    /**
     * Workflow transitions the librarian may perform: submit for review,
     * approve/reject the metadata review. Publication itself needs
     * repository.publish and lives in a separate action below.
     */
    public function transition(Request $request, RepositoryItem $item, AuditLogger $audit, LibraryNotificationService $notifications): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['submit', 'approve', 'reject', 'publish', 'archive'])],
            'comment' => ['nullable', 'string', 'max:2000'],
        ]);

        $action = $validated['action'];
        $user = $request->user();

        $allowed = match ($action) {
            'submit' => $item->status === 'draft' && $user->can('repository.upload'),
            'approve' => $item->status === 'under_review' && $user->can('repository.approve'),
            'reject' => $item->status === 'under_review' && $user->can('repository.approve'),
            'publish' => in_array($item->status, ['approved'], true) && $user->can('repository.publish'),
            'archive' => $item->status === 'published' && $user->can('repository.remove'),
        };

        if (! $allowed) {
            abort(403);
        }

        if ($action === 'reject' && trim((string) ($validated['comment'] ?? '')) === '') {
            return back()->withErrors(['comment' => __('librarian.repository.reject_needs_comment')]);
        }

        DB::transaction(function () use ($item, $action, $validated, $user, $audit, $notifications): void {
            $oldStatus = $item->status;
            $item->status = match ($action) {
                'submit' => 'under_review',
                'approve' => 'approved',
                'reject' => 'rejected',
                'publish' => 'published',
                'archive' => 'archived',
            };
            if ($action === 'approve' || $action === 'reject') {
                $item->reviewed_by = $user->getKey();
            }
            if ($action === 'publish') {
                $item->approved_by = $user->getKey();
                $item->published_at = now();
            }
            if ($validated['comment'] ?? null) {
                $item->review_notes = trim(($item->review_notes ? $item->review_notes."\n" : '').$validated['comment']);
            }
            $item->save();

            $audit->logRequired(
                actionType: 'repository.'.$action,
                entityType: 'repository_item',
                entityId: $item->getKey(),
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => $item->status],
                reason: $validated['comment'] ?? null,
                scope: 'library',
            );

            $uploader = $item->uploadedBy;
            if ($uploader !== null && $uploader->isNot($user)) {
                $notifications->sendLocalized(
                    $uploader,
                    $action === 'publish' ? 'repository_published' : 'repository_status_changed',
                    'librarian.notifications.repository_status_title',
                    'librarian.notifications.repository_status_body',
                    [
                        'title' => $item->title,
                        'status' => ['_translation' => 'librarian.repository.statuses.'.$item->status],
                    ],
                    ['repository_item_id' => $item->getKey()],
                );
            }
        });

        return back()->with('success', __('common.updated_successfully'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:1000'],
            'authors' => ['required', 'string', 'max:2000'],
            'work_type' => ['required', Rule::in(RepositoryItem::WORK_TYPES)],
            'year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'department' => ['nullable', 'string', 'max:255'],
            'udc_code' => ['nullable', 'string', 'max:64'],
            'abstract' => ['nullable', 'string', 'max:10000'],
            'keywords' => ['nullable', 'string', 'max:2000'],
            'language' => ['required', Rule::in(['ru', 'kk', 'en'])],
            'file' => ['nullable', 'file', 'mimes:pdf', 'max:51200'],
        ]);

        $validated['authors'] = collect(preg_split('/[\r\n;,]+/', (string) $validated['authors']) ?: [])
            ->map(fn (string $author): string => trim($author))->filter()->values()->all();
        $validated['keywords'] = collect(preg_split('/[\r\n;,]+/', (string) ($validated['keywords'] ?? '')) ?: [])
            ->map(fn (string $keyword): string => trim($keyword))->filter()->values()->all();
        unset($validated['file']);

        return $validated;
    }
}
