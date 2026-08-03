<?php

namespace App\Http\Controllers\Librarian;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\UdcCode;
use App\Models\Fund;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Services\Catalog\DuplicateRecordFinder;
use App\Services\DataQuality\DataQualityScanner;
use App\Services\DataQuality\DuplicateDetectionService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Cataloguing workspace (Master.md §6-§11, scenario §31.3). Records saved
 * with required fields missing become drafts and surface in Data Cleanup
 * instead of being rejected.
 */
class CatalogController extends Controller
{
    public function __construct(private readonly DuplicateRecordFinder $duplicates) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'resource_type' => ['nullable', Rule::in(BibliographicRecord::RESOURCE_TYPES)],
            'language' => ['nullable', Rule::in(BibliographicRecord::LANGUAGES)],
            'udc' => ['nullable', 'string', 'max:64'],
            'year_from' => ['nullable', 'integer', 'min:1500', 'max:2100'],
            'year_to' => ['nullable', 'integer', 'min:1500', 'max:2100'],
            'state' => ['nullable', Rule::in(['complete', 'draft'])],
            // Bridges the Data Cleanup queues into this list so the bulk
            // editor can work them a page at a time (ДИР §6.3).
            'review' => ['nullable', Rule::in(['manual_review', 'language_mismatch'])],
            'tier' => ['nullable', Rule::in(['high', 'medium', 'low'])],
        ]);

        $query = BibliographicRecord::query()->withCount([
            'copies',
            'copies as available_copies_count' => fn (Builder $builder) => $builder->where('status', 'available'),
        ]);

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $query->search($search);
        }
        if ($type = ($filters['resource_type'] ?? null)) {
            $query->where('resource_type', $type);
        }
        if ($language = ($filters['language'] ?? null)) {
            $query->where('language', $language);
        }
        if ($udc = trim((string) ($filters['udc'] ?? ''))) {
            $query->where('udc_code', 'like', $udc.'%');
        }
        if ($from = ($filters['year_from'] ?? null)) {
            $query->where('publication_year', '>=', (int) $from);
        }
        if ($to = ($filters['year_to'] ?? null)) {
            $query->where('publication_year', '<=', (int) $to);
        }
        if (($filters['review'] ?? null) === 'manual_review') {
            $query->where('needs_manual_review', true);
        } elseif (($filters['review'] ?? null) === 'language_mismatch') {
            $query->where('language', '!=', 'kk')->titleHasKazakhLetters();
        }
        if (($filters['state'] ?? null) === 'draft') {
            $query->where('is_draft', true);
        } elseif (($filters['state'] ?? null) === 'complete') {
            $query->where('is_draft', false);
        }

        // Tier is a PHP-side word-ratio judgement, so it narrows the id set
        // before pagination rather than becoming a WHERE clause.
        if (($filters['tier'] ?? null) !== null && ($filters['review'] ?? null) === 'language_mismatch') {
            $ids = [];
            (clone $query)->select(['id', 'title'])->orderBy('id')
                ->chunkById(1000, function ($chunk) use (&$ids, $filters): void {
                    foreach ($chunk as $record) {
                        if ($record->kazakhTitleConfidence()['tier'] === $filters['tier']) {
                            $ids[] = $record->getKey();
                        }
                    }
                });
            $query->whereIn('id', $ids);
        }

        return view('librarian.catalog.index', [
            'records' => $query->orderByDesc('updated_at')->paginate(Setting::resultsPerPage())->withQueryString(),
            'filters' => $filters,
            'draftCount' => BibliographicRecord::query()->where('is_draft', true)->count(),
        ]);
    }

    public function create(): View
    {
        return view('librarian.catalog.form', [
            'record' => new BibliographicRecord(['language' => 'ru', 'resource_type' => 'book']),
            'branches' => Branch::query()->orderBy('name')->get(),
            'funds' => Fund::query()->orderBy('name')->get(),
            'history' => collect(),
        ]);
    }

    public function store(
        Request $request,
        AuditLogger $audit,
        DataQualityScanner $scanner,
        DuplicateDetectionService $persistentDuplicates,
    ): RedirectResponse
    {
        $validated = $this->validated($request);

        // §11.3: warn about likely duplicates before the final save, without
        // hard-blocking the librarian.
        if (! $request->boolean('confirmed_duplicate')) {
            $duplicate = $this->duplicates->find($validated);
            if ($duplicate !== null) {
                return back()
                    ->withInput()
                    ->with('duplicate_warning', $this->duplicates->describe($duplicate));
            }
        }

        $record = DB::transaction(function () use ($validated, $request, $audit): BibliographicRecord {
            $record = new BibliographicRecord($validated);
            $record->is_draft = $record->missingRequiredFields() !== [];
            $record->responsible_librarian_id = $request->user()->getKey();
            $record->save();

            $audit->logRequired(
                actionType: 'metadata.create',
                entityType: 'bibliographic_record',
                entityId: $record->getKey(),
                newValues: $this->snapshot($record),
                scope: 'library',
                metadata: ['duplicate_override_confirmed' => $request->boolean('confirmed_duplicate')],
            );

            if ($request->boolean('confirmed_duplicate')) {
                $audit->logRequired(
                    actionType: 'data_quality.duplicate_reviewed',
                    entityType: 'bibliographic_record',
                    entityId: $record->getKey(),
                    newValues: ['confirmed_separate_edition' => true],
                    reason: 'The cataloguer explicitly confirmed this is a separate edition.',
                    scope: 'operational',
                );
            }

            return $record;
        });
        $scanner->scanModel($record, 'bibliographic_record');
        $persistentDuplicates->detectAndStore($record);

        return redirect()
            ->route('librarian.catalog.edit', $record)
            ->with('success', $record->is_draft
                ? __('librarian.catalog.saved_as_draft', ['fields' => implode(', ', $record->missingRequiredFields())])
                : __('common.created_successfully'));
    }

    public function edit(BibliographicRecord $record): View
    {
        $record->load(['copies.branch', 'copies.fund', 'electronicMaterials.uploadedBy', 'relatedRecords']);

        return view('librarian.catalog.form', [
            'record' => $record,
            'branches' => Branch::query()->orderBy('name')->get(),
            'funds' => Fund::query()->orderBy('name')->get(),
            // ДИР §6.3 "история исправлений" — scoped to this record, so a
            // librarian without /admin/logs access can still audit their own
            // corrections (§31.3).
            'history' => ActivityLog::query()
                ->where('entity_type', 'bibliographic_record')
                ->where('entity_id', (string) $record->getKey())
                ->orderByDesc('occurred_at')
                ->limit(25)
                ->get(),
        ]);
    }

    public function update(
        Request $request,
        BibliographicRecord $record,
        AuditLogger $audit,
        DataQualityScanner $scanner,
        DuplicateDetectionService $persistentDuplicates,
    ): RedirectResponse
    {
        $validated = $this->validated($request);

        DB::transaction(function () use ($validated, $record, $audit): void {
            $old = $this->snapshot($record);
            $record->fill($validated);
            $record->is_draft = $record->missingRequiredFields() !== [];
            $record->save();

            // §31.3: every correction keeps its before/after history.
            $audit->logRequired(
                actionType: 'metadata.update',
                entityType: 'bibliographic_record',
                entityId: $record->getKey(),
                oldValues: $old,
                newValues: $this->snapshot($record),
                scope: 'library',
            );
        });
        $scanner->scanModel($record->fresh(), 'bibliographic_record');
        $persistentDuplicates->detectAndStore($record->fresh());

        return redirect()
            ->route('librarian.catalog.edit', $record)
            ->with('success', __('common.updated_successfully'));
    }

    public function destroy(Request $request, BibliographicRecord $record, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        if ($record->copies()->whereNotIn('status', ['written_off', 'lost'])->exists()) {
            return back()->withErrors(['reason' => __('librarian.catalog.delete_has_copies')]);
        }

        DB::transaction(function () use ($record, $validated, $audit): void {
            $old = $this->snapshot($record);
            $record->delete();

            $audit->logRequired(
                actionType: 'delete',
                entityType: 'bibliographic_record',
                entityId: $record->getKey(),
                oldValues: $old,
                reason: $validated['reason'],
                scope: 'library',
            );
        });

        return redirect()->route('librarian.catalog.index')->with('success', __('common.deleted_successfully'));
    }

    /**
     * ДИР §6.3 — undo one recorded change. Restores only the fields that entry
     * actually altered, so reverting an old edit does not silently roll back
     * everything done since. The revert is itself audited, never a deletion of
     * history (§31.3).
     */
    public function revert(
        BibliographicRecord $record,
        ActivityLog $log,
        AuditLogger $audit,
    ): RedirectResponse {
        abort_unless(
            $log->entity_type === 'bibliographic_record' && (string) $log->entity_id === (string) $record->getKey(),
            404,
        );

        $old = (array) ($log->old_values ?? []);
        $new = (array) ($log->new_values ?? []);
        $restorable = array_intersect_key($old, array_flip($record->getFillable()));

        // Only fields this entry changed; untouched ones stay as they are now.
        $changed = array_filter(
            $restorable,
            static fn ($value, string $field): bool => json_encode($value) !== json_encode($new[$field] ?? null),
            ARRAY_FILTER_USE_BOTH,
        );

        if ($changed === []) {
            return back()->with('error', __('librarian.catalog.history_extra.nothing_to_revert'));
        }

        DB::transaction(function () use ($record, $changed, $audit): void {
            $before = $this->snapshot($record);
            $record->fill($changed);
            $record->is_draft = $record->missingRequiredFields() !== [];
            $record->save();

            $audit->logRequired(
                actionType: 'metadata.update',
                entityType: 'bibliographic_record',
                entityId: $record->getKey(),
                oldValues: $before,
                newValues: $this->snapshot($record),
                reason: __('librarian.catalog.history_extra.revert_reason'),
                scope: 'library',
            );
        });

        return back()->with('success', __('librarian.catalog.history_extra.reverted'));
    }

    /**
     * ДИР §6.3 "массовое редактирование". Restricted on purpose to shared,
     * non-identifying attributes: title/author/ISBN are unique per record and
     * must never be set from a bulk form. Every touched record gets its own
     * audit entry so §31.3 history stays per-record.
     */
    public function bulkUpdate(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:500'],
            'ids.*' => ['integer'],
            'language' => ['nullable', Rule::in(BibliographicRecord::LANGUAGES)],
            'resource_type' => ['nullable', Rule::in(BibliographicRecord::RESOURCE_TYPES)],
            'category' => ['nullable', 'string', 'max:128'],
            'udc_code' => ['nullable', 'string', 'max:64'],
            'needs_manual_review' => ['nullable', Rule::in(['', 'yes', 'no'])],
        ]);

        $changes = array_filter(
            [
                'language' => $validated['language'] ?? null,
                'resource_type' => $validated['resource_type'] ?? null,
                'category' => trim((string) ($validated['category'] ?? '')) ?: null,
                'udc_code' => trim((string) ($validated['udc_code'] ?? '')) ?: null,
            ],
            static fn ($value): bool => $value !== null,
        );

        $reviewChoice = $validated['needs_manual_review'] ?? '';
        if ($reviewChoice !== '') {
            $changes['needs_manual_review'] = $reviewChoice === 'yes';
        }

        if ($changes === []) {
            return back()->with('error', __('librarian.catalog.bulk.nothing_selected'));
        }

        $records = BibliographicRecord::query()->whereIn('id', $validated['ids'])->get();

        DB::transaction(function () use ($records, $changes, $audit): void {
            foreach ($records as $record) {
                $old = $this->snapshot($record);
                $record->fill($changes);
                // Completeness is derived, so re-evaluate it after a bulk fill
                // that may have supplied a missing UDC.
                $record->is_draft = $record->missingRequiredFields() !== [];
                $record->save();

                $audit->logRequired(
                    actionType: 'metadata.update',
                    entityType: 'bibliographic_record',
                    entityId: $record->getKey(),
                    oldValues: $old,
                    newValues: $this->snapshot($record),
                    scope: 'library',
                );
            }
        });

        return back()->with('success', __('librarian.catalog.bulk.updated', ['count' => $records->count()]));
    }

    /**
     * ДИР §6.2 — the librarian asks "is this already in the catalogue?" before
     * submitting, instead of finding out only after a rejected save. Same rule
     * as the store() guard, exposed as JSON.
     */
    public function duplicateCheck(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:1000'],
            'primary_author' => ['nullable', 'string', 'max:255'],
            'publication_year' => ['nullable', 'integer', 'min:1500', 'max:2100'],
            'isbn' => ['nullable', 'string', 'max:32'],
            'ignore' => ['nullable', 'integer'],
        ]);

        $match = $this->duplicates->find($validated, $validated['ignore'] ?? null);

        return response()->json([
            'duplicate' => $this->duplicates->describe($match),
            'editUrl' => $match !== null ? route('librarian.catalog.edit', $match) : null,
        ]);
    }

    /**
     * UDC reference autocomplete for the record form.
     */
    public function udcSearch(Request $request): JsonResponse
    {
        $term = trim((string) $request->query('q', ''));

        $codes = UdcCode::query()
            ->when($term !== '', fn (Builder $builder) => $builder->search($term))
            ->orderBy('code')
            ->limit(15)
            ->get()
            ->map(fn (UdcCode $code): array => [
                'code' => $code->code,
                'description' => $code->localizedDescription(),
            ]);

        return response()->json(['data' => $codes]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        $validated = $request->validate([
            'title' => ['required', 'string', 'max:1000'],
            'subtitle' => ['nullable', 'string', 'max:1000'],
            'primary_author' => ['nullable', 'string', 'max:255'],
            'additional_authors' => ['nullable', 'string', 'max:2000'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'publication_year' => ['nullable', 'integer', 'min:1500', 'max:2100'],
            'language' => ['required', Rule::in(BibliographicRecord::LANGUAGES)],
            'udc_code' => ['nullable', 'string', 'max:64'],
            'author_mark' => ['nullable', 'string', 'max:16'],
            'category' => ['nullable', 'string', 'max:128'],
            'annotation' => ['nullable', 'string', 'max:10000'],
            'keywords' => ['nullable', 'string', 'max:2000'],
            'isbn' => ['nullable', 'string', 'max:32'],
            'resource_type' => ['required', Rule::in(BibliographicRecord::RESOURCE_TYPES)],
            'cover_path' => ['nullable', 'string', 'max:2048'],
            'notes' => ['nullable', 'string', 'max:5000'],
            // ДИР §6.3 — a human may queue a technically complete record for
            // review, so this is deliberately independent of is_draft.
            'needs_manual_review' => ['nullable', 'boolean'],
            'review_note' => ['nullable', 'string', 'max:500'],
        ]);

        $validated['additional_authors'] = $this->listFromText($validated['additional_authors'] ?? null);
        $validated['keywords'] = $this->listFromText($validated['keywords'] ?? null);
        $validated['needs_manual_review'] = $request->boolean('needs_manual_review');

        return $validated;
    }

    /**
     * @return list<string>
     */
    private function listFromText(?string $value): array
    {
        return collect(preg_split('/[\r\n;,]+/', (string) $value) ?: [])
            ->map(fn (string $item): string => trim($item))
            ->filter()
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function snapshot(BibliographicRecord $record): array
    {
        return $record->only([
            'title', 'subtitle', 'primary_author', 'additional_authors', 'publisher',
            'publication_year', 'language', 'udc_code', 'author_mark', 'category',
            'annotation', 'keywords', 'isbn', 'resource_type', 'cover_path', 'notes', 'is_draft',
            'needs_manual_review', 'review_note',
        ]);
    }
}
