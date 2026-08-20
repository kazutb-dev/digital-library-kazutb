<?php

namespace App\Http\Controllers\Librarian;

use App\Http\Controllers\Controller;
use App\Models\Catalog\RepositoryAuthor;
use App\Models\Catalog\RepositoryItem;
use App\Models\Catalog\RepositoryItemVersion;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Catalog\LibraryNotificationService;
use App\Services\Repository\RepositoryWorkflow;
use App\Support\UploadedFileSecurity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Scientific repository moderation (Master.md 20): the librarian uploads on
 * behalf of the author, moderates metadata, and sends work up the approval
 * chain. Final approval and publication belong to the library director.
 */
class RepositoryController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(RepositoryItem::STATUSES)],
            'work_type' => ['nullable', Rule::in(RepositoryItem::acceptedWorkTypes())],
            'search' => ['nullable', 'string', 'max:200'],
        ]);

        $query = RepositoryItem::query()->with(['uploadedBy', 'approvedBy']);

        if ($status = ($filters['status'] ?? null)) {
            $query->where('status', $status);
        }
        if ($type = ($filters['work_type'] ?? null)) {
            $query->whereIn('work_type', RepositoryItem::equivalentWorkTypes($type));
        }
        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->search($search);
        }

        return view('librarian.repository.index', [
            'items' => $query->orderByRaw("CASE status WHEN 'pending_approval' THEN 0 WHEN 'quality_review' THEN 1 WHEN 'draft' THEN 2 ELSE 3 END")
                ->orderByDesc('updated_at')
                ->paginate(Setting::resultsPerPage())
                ->withQueryString(),
            'filters' => $filters,
            'statusCounts' => RepositoryItem::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', RepositoryItem::class);

        return view('librarian.repository.form', ['item' => new RepositoryItem([
            'language' => 'kk',
            'work_type' => 'bachelor_thesis',
            // Intake is closed until rights review. Full public is an explicit
            // choice subsequently covered by director approval.
            'access_policy' => 'metadata_only',
            'copyright_status' => 'unknown',
        ])]);
    }

    public function store(Request $request, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('create', RepositoryItem::class);

        $validated = $this->validated($request);

        $item = DB::transaction(function () use ($validated, $request, $audit): RepositoryItem {
            $item = new RepositoryItem([...$validated, 'status' => 'draft', 'uploaded_by' => $request->user()->getKey()]);

            $item->save();
            $this->syncAuthors($item, $validated['authors'], $request->input('author_orcid'));
            if ($request->hasFile('file')) {
                $this->storeVersion($item, $request->file('file'), $request->user(), __('common.created_successfully'));
            }

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
        // Leadership approves publication in read-only mode; metadata remains
        // the responsibility of the authorised library employee.
        Gate::authorize('edit', $item);

        if (in_array($item->status, ['published', 'embargoed', 'withdrawn', 'archived'], true)) {
            return back()->withErrors(['title' => __('librarian.repository.locked_after_publish')]);
        }

        $validated = $this->validated($request);

        DB::transaction(function () use ($item, $validated, $request, $audit): void {
            $locked = RepositoryItem::query()->whereKey($item)->lockForUpdate()->firstOrFail();
            Gate::authorize('edit', $locked);

            if (in_array($locked->status, ['published', 'embargoed', 'withdrawn', 'archived'], true)) {
                throw ValidationException::withMessages(['title' => __('librarian.repository.locked_after_publish')]);
            }

            $old = $locked->only(array_keys($validated));
            $locked->fill($validated);

            if ($request->hasFile('file')) {
                $reason = trim((string) $request->input('version_reason'));
                if ($locked->file_path && $reason === '') {
                    throw ValidationException::withMessages(['version_reason' => __('repository.validation.reason_required')]);
                }
                $this->storeVersion($locked, $request->file('file'), $request->user(), $reason ?: __('common.updated_successfully'));
            }

            if ($locked->isDirty() || $request->hasFile('file')) {
                $locked->forceFill([
                    'status' => 'metadata_review',
                    'approved_by' => null,
                    'active_approval_id' => null,
                    'reviewed_by' => null,
                    'scheduled_for' => null,
                    'published_at' => null,
                ]);
            }

            $locked->save();
            $this->syncAuthors($locked, $validated['authors'], $request->input('author_orcid'));

            $audit->logRequired(
                actionType: 'metadata.update',
                entityType: 'repository_item',
                entityId: $locked->getKey(),
                oldValues: $old,
                newValues: $locked->only(array_keys($validated)),
                scope: 'library',
            );
        });

        return redirect()->route('librarian.repository.edit', $item)->with('success', __('common.updated_successfully'));
    }

    /**
     * Start a new review cycle from an immutable published record. The old PDF,
     * approval and review history are retained; only the newly uploaded PDF is
     * active, and it cannot become public until quality review + fresh director
     * approval have completed again.
     */
    public function revise(Request $request, RepositoryItem $item, AuditLogger $audit): RedirectResponse
    {
        Gate::authorize('manageVersions', $item);
        abort_unless(in_array($item->status, ['published', 'embargoed'], true), 409);

        $validated = $request->validate([
            'file' => ['required', 'file', 'mimes:pdf', 'max:51200'],
            'version_reason' => ['required', 'string', 'min:5', 'max:2000'],
        ]);
        $newPath = null;

        try {
            DB::transaction(function () use ($item, $request, $validated, $audit, &$newPath): void {
                $locked = RepositoryItem::query()->whereKey($item)->lockForUpdate()->firstOrFail();
                Gate::authorize('manageVersions', $locked);
                if (! in_array($locked->status, ['published', 'embargoed'], true)) {
                    throw ValidationException::withMessages(['file' => __('repository.validation.invalid_transition')]);
                }

                $from = $locked->status;
                $this->storeVersion(
                    $locked,
                    $request->file('file'),
                    $request->user(),
                    $validated['version_reason'],
                    fn (string $stored): string => $newPath = $stored,
                );
                $locked->forceFill([
                    'status' => 'metadata_review',
                    'approved_by' => null,
                    'active_approval_id' => null,
                    'reviewed_by' => null,
                    'scheduled_for' => null,
                    'published_at' => null,
                ])->save();

                $audit->logRequired(
                    actionType: 'repository.new_version',
                    entityType: 'repository_item',
                    entityId: $locked->getKey(),
                    oldValues: ['status' => $from, 'version_number' => $locked->version_number - 1],
                    newValues: ['status' => 'metadata_review', 'version_number' => $locked->version_number],
                    reason: $validated['version_reason'],
                    scope: 'library',
                );
            });
        } catch (\Throwable $exception) {
            if (is_string($newPath) && $newPath !== '') {
                Storage::disk('local')->delete($newPath);
            }

            throw $exception;
        }

        return redirect()->route('librarian.repository.edit', $item)->with('success', __('common.updated_successfully'));
    }

    /** Stream an intake PDF from private storage for responsible reviewers. */
    public function file(Request $request, RepositoryItem $item, AuditLogger $audit): Response
    {
        abort_unless($request->user()->can('readFull', $item), 403);

        $path = trim((string) $item->file_path);
        abort_if($path === '' || ! $item->hasStoredPublishablePdf(), 404);

        $audit->log(
            actionType: 'repository.review_file',
            entityType: 'repository_item',
            entityId: $item->getKey(),
            scope: 'operational',
            metadata: ['status' => $item->status],
            actor: $request->user(),
            request: $request,
        );

        return Storage::disk('local')->download($path, $item->file_name ?: basename($path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * Move a work through the four-step review chain. Responsible library
     * staff perform quality review; only a director may approve or publish.
     */
    public function transition(Request $request, RepositoryItem $item, RepositoryWorkflow $workflow, LibraryNotificationService $notifications): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', 'string'],
            'status' => ['nullable', Rule::in(RepositoryItem::STATUSES)],
            'comment' => ['nullable', 'string', 'max:2000'],
            'scheduled_for' => ['nullable', 'date', 'after:now'],
        ]);

        $action = $validated['action'];
        $user = $request->user();

        $target = $validated['status'] ?? match ($action) {
            'submit' => 'metadata_review', 'approve' => 'approved', 'reject' => 'rejected',
            'publish' => 'published', 'archive' => 'archived', 'withdraw' => 'withdrawn',
            default => $action,
        };
        $allowed = match ($target) {
            'approved' => $user->can('approve', $item),
            'scheduled', 'published', 'embargoed' => $user->can('publish', $item),
            'metadata_review', 'author_verification', 'quality_review', 'pending_approval', 'rejected' => $user->can('reviewMetadata', $item),
            'rights_review' => $user->can('reviewRights', $item),
            'changes_requested' => $user->can('requestChanges', $item),
            'withdrawn', 'archived' => $user->can('withdraw', $item),
            default => $user->can('edit', $item),
        };

        if (! $allowed) {
            abort(403);
        }

        $workflow->transition($item, $target, $user, $validated['comment'] ?? null, $validated['scheduled_for'] ?? null);
        DB::transaction(function () use ($item, $target, $user, $notifications): void {
            $uploader = $item->uploadedBy;
            if ($uploader !== null && $uploader->isNot($user)) {
                $notifications->sendLocalized(
                    $uploader,
                    $target === 'published' ? 'repository_published' : 'repository_status_changed',
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
        $request->merge([
            'copyright_status' => $request->input('copyright_status', 'unknown'),
            'access_policy' => $request->input('access_policy', 'metadata_only'),
        ]);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:1000'],
            'authors' => ['required', 'string', 'max:2000'],
            'work_type' => ['required', Rule::in(RepositoryItem::acceptedWorkTypes())],
            'year' => ['nullable', 'integer', 'min:1950', 'max:2100'],
            'department' => ['nullable', 'string', 'max:255'],
            'udc_code' => ['nullable', 'string', 'max:64'],
            'abstract' => ['nullable', 'string', 'max:10000'],
            'keywords' => ['nullable', 'string', 'max:2000'],
            'language' => ['required', Rule::in(['ru', 'kk', 'en'])],
            'file' => ['nullable', 'file', 'mimes:pdf', 'max:51200'],
            'supervisor' => ['nullable', 'string', 'max:255'],
            'reviewer' => ['nullable', 'string', 'max:255'],
            'university' => ['nullable', 'string', 'max:255'],
            'faculty' => ['nullable', 'string', 'max:255'],
            'educational_programme' => ['nullable', 'string', 'max:255'],
            'degree_level' => ['nullable', 'string', 'max:64'],
            'defence_date' => ['nullable', 'date'],
            'publication_date' => ['nullable', 'date'],
            'page_count' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'doi' => ['nullable', 'string', 'max:255'],
            'isbn_issn' => ['nullable', 'string', 'max:64'],
            'source' => ['nullable', 'string', 'max:5000'],
            'rights_holder' => ['nullable', 'string', 'max:255'],
            'copyright_status' => ['required', Rule::in(['public_domain', 'permission_granted', 'university_owned', 'licensed', 'restricted', 'unknown'])],
            'licence_type' => ['nullable', 'string', 'max:64'],
            'licence_text' => ['nullable', 'string', 'max:5000'],
            'permission_date' => ['nullable', 'date'],
            'access_policy' => ['required', Rule::in(RepositoryItem::acceptedAccessPolicies())],
            'embargo_until' => ['nullable', 'date'],
            'post_embargo_access_policy' => ['nullable', Rule::in(RepositoryItem::POST_EMBARGO_ACCESS_POLICIES)],
            'version_reason' => ['nullable', 'string', 'max:2000'],
            'author_orcid' => ['nullable', 'regex:/^\d{4}-\d{4}-\d{4}-\d{3}[\dX]$/'],
        ]);

        $validated['authors'] = collect(preg_split('/[\r\n;,]+/', (string) $validated['authors']) ?: [])
            ->map(fn (string $author): string => trim($author))->filter()->values()->all();
        $validated['keywords'] = collect(preg_split('/[\r\n;,]+/', (string) ($validated['keywords'] ?? '')) ?: [])
            ->map(fn (string $keyword): string => trim($keyword))->filter()->values()->all();
        unset($validated['file']);
        unset($validated['version_reason']);
        unset($validated['author_orcid']);

        return $validated;
    }

    private function storeVersion(RepositoryItem $item, UploadedFile $file, User $actor, string $reason, ?callable $storedPath = null): void
    {
        abort_unless($file->getMimeType() === 'application/pdf', 422);
        UploadedFileSecurity::assertSafe($file);
        $checksum = hash_file('sha256', $file->getRealPath());
        if (RepositoryItemVersion::query()->where('checksum_sha256', $checksum)->exists()) {
            throw ValidationException::withMessages(['file' => __('digital.validation.duplicate_checksum')]);
        }
        $version = max(1, (int) $item->version_number + ($item->file_path ? 1 : 0));
        $safeName = Str::uuid().'.pdf';
        $path = $file->storeAs("repository/{$item->public_id}/v{$version}", $safeName, 'local');
        if (! is_string($path) || $path === '') {
            throw ValidationException::withMessages(['file' => __('digital.validation.storage_failed')]);
        }
        $storedPath?->__invoke($path);
        RepositoryItemVersion::query()->where('repository_item_id', $item->getKey())->update(['is_active' => false]);
        RepositoryItemVersion::create(['repository_item_id' => $item->getKey(), 'version_number' => $version, 'storage_disk' => 'local', 'file_path' => $path, 'file_name' => $file->getClientOriginalName(), 'file_size' => $file->getSize(), 'mime_type' => 'application/pdf', 'checksum_sha256' => $checksum, 'change_reason' => $reason, 'created_by' => $actor->getKey(), 'is_active' => true]);
        $item->forceFill(['file_path' => $path, 'file_name' => $file->getClientOriginalName(), 'file_size' => $file->getSize(), 'checksum_sha256' => $checksum, 'version_number' => $version])->save();
    }

    /** @param list<string> $authors */
    private function syncAuthors(RepositoryItem $item, array $authors, ?string $primaryOrcid): void
    {
        $item->authorsList()->delete();
        foreach ($authors as $position => $name) {
            RepositoryAuthor::create(['repository_item_id' => $item->getKey(), 'display_name' => $name, 'normalised_name' => mb_strtolower(trim($name)), 'orcid' => $position === 0 ? $primaryOrcid : null, 'is_primary' => $position === 0, 'sort_order' => $position]);
        }
    }
}
