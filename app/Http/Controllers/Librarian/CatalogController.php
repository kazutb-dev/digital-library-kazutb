<?php

namespace App\Http\Controllers\Librarian;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BibliographicRecordTranslation;
use App\Models\Catalog\Contributor;
use App\Models\Catalog\LegacyMarcRecord;
use App\Models\Catalog\Subject;
use App\Models\Catalog\UdcCode;
use App\Models\DataQualityIssue;
use App\Models\Fund;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Services\Catalog\DuplicateRecordFinder;
use App\Services\DataQuality\DataQualityScanner;
use App\Services\DataQuality\DuplicateDetectionService;
use App\Support\DatabaseSchema;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Cataloguing workspace (Master.md 6-11, scenario 31.3). Records saved
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
            // editor can work them a page at a time (ДИР 6.3).
            'review' => ['nullable', Rule::in(['manual_review', 'language_mismatch'])],
            'tier' => ['nullable', Rule::in(['high', 'medium', 'low'])],
        ]);

        $query = BibliographicRecord::query()->withCount([
            'copies',
            'copies as available_copies_count' => fn (Builder $builder) => $builder->availableForCirculation(),
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

        $records = $query->orderByDesc('updated_at')->paginate(Setting::resultsPerPage())->withQueryString();
        $qualityByRecord = DataQualityIssue::query()->actionable()
            ->where('entity_type', 'bibliographic_record')
            ->whereIn('entity_id', $records->getCollection()->pluck('id')->map(fn ($id) => (string) $id))
            ->get()->groupBy('entity_id');

        return view('librarian.catalog.index', [
            'records' => $records,
            'filters' => $filters,
            'draftCount' => BibliographicRecord::query()->where('is_draft', true)->count(),
            'qualityByRecord' => $qualityByRecord,
        ]);
    }

    public function create(Request $request): View
    {
        return view('librarian.catalog.form', [
            'record' => new BibliographicRecord(['language' => 'ru', 'resource_type' => 'book']),
            'branches' => Branch::query()->orderBy('name')->get(),
            'funds' => Fund::query()->orderBy('name')->get(),
            'contributors' => collect(),
            'subjects' => collect(),
            'rawMarcRecords' => collect(),
            'history' => collect(),
            'qualityIssues' => collect(),
            'returnTo' => $request->query('return_to') === 'acquisitions' ? 'acquisitions' : null,
        ]);
    }

    public function store(
        Request $request,
        AuditLogger $audit,
        DataQualityScanner $scanner,
        DuplicateDetectionService $persistentDuplicates,
    ): RedirectResponse {
        $validated = $this->validated($request);

        // 11.3: warn about likely duplicates before the final save, without
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
            $record = new BibliographicRecord(collect($validated)->except(['translations', 'contributors', 'subjects'])->all());
            $record->is_draft = $record->missingRequiredFields() !== [];
            $record->responsible_librarian_id = $request->user()->getKey();
            $record->save();
            if (array_key_exists('contributors', $validated)) {
                $this->syncContributors($record, (array) $validated['contributors']);
            }
            if (array_key_exists('subjects', $validated)) {
                $this->syncSubjects($record, (array) $validated['subjects']);
            }
            $this->syncTranslations($record, (array) ($validated['translations'] ?? []), $request, $audit);

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

        $success = $record->is_draft
            ? __('librarian.catalog.saved_as_draft', ['fields' => implode(', ', $record->missingRequiredFields())])
            : __('common.created_successfully');

        if ($request->input('return_to') === 'acquisitions'
            && $request->user()?->canAny(['acquisitions.intake', 'acquisitions.manage'])) {
            return redirect()
                ->route('librarian.acquisitions.index', ['record_q' => $record->title])
                ->with('success', $success);
        }

        return redirect()
            ->route('librarian.catalog.edit', $record)
            ->with('success', $success);
    }

    public function edit(Request $request, BibliographicRecord $record): View
    {
        $relations = ['copies.branch', 'copies.fund', 'electronicMaterials.uploadedBy', 'relatedRecords', 'translations.translator', 'translations.reviewer'];
        if (DatabaseSchema::hasTable('contributors') && DatabaseSchema::hasTable('bibliographic_record_contributor')) {
            $relations[] = 'contributors';
        }
        if (DatabaseSchema::hasTable('subjects') && DatabaseSchema::hasTable('bibliographic_record_subject')) {
            $relations[] = 'subjects';
        }
        $record->load($relations);

        $rawMarcRecords = collect();
        if ($request->user()?->can('catalog.view_raw_marc')
            && DatabaseSchema::hasTable('legacy_marc_records')
            && DatabaseSchema::hasTable('legacy_marc_fields')) {
            $rawMarcRecords = $record->rawMarcRecords()->get()
                ->each(function (LegacyMarcRecord $legacyRecord): void {
                    // `legacy_marc_fields` is keyed by batch + source document,
                    // so load it on the concrete record to preserve that pair.
                    $legacyRecord->setRelation('fields', $legacyRecord->fields()->get());
                });
        }

        $sourceIssue = $request->query('from') === 'data-quality'
            ? DataQualityIssue::query()->whereKey($request->integer('issue'))
                ->where('entity_type', 'bibliographic_record')->where('entity_id', (string) $record->getKey())->first()
            : null;

        return view('librarian.catalog.form', [
            'record' => $record,
            'branches' => Branch::query()->orderBy('name')->get(),
            'funds' => Fund::query()->orderBy('name')->get(),
            'contributors' => $record->relationLoaded('contributors') ? $record->contributors : collect(),
            'subjects' => $record->relationLoaded('subjects') ? $record->subjects : collect(),
            'rawMarcRecords' => $rawMarcRecords,
            // ДИР 6.3 "история исправлений" — scoped to this record, so a
            // librarian without /admin/logs access can still audit their own
            // corrections (31.3).
            'history' => ActivityLog::query()
                ->where('entity_type', 'bibliographic_record')
                ->where('entity_id', (string) $record->getKey())
                ->orderByDesc('occurred_at')
                ->limit(25)
                ->get(),
            'qualityIssues' => DataQualityIssue::query()->actionable()
                ->where(function (Builder $query) use ($record): void {
                    $query->where(fn (Builder $recordIssue) => $recordIssue
                        ->where('entity_type', 'bibliographic_record')->where('entity_id', (string) $record->getKey()))
                        ->orWhere(fn (Builder $copyIssue) => $copyIssue
                            ->where('entity_type', 'book_copy')->whereIn('entity_id', $record->copies->pluck('id')->map(fn ($id) => (string) $id)));
                })
                ->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
                ->limit(20)->get(),
            'fromDataQuality' => $sourceIssue !== null,
            'sourceIssue' => $sourceIssue,
            'nextQualityIssue' => $sourceIssue ? DataQualityIssue::query()->actionable()
                ->where('entity_type', 'bibliographic_record')->where('entity_id', '>', (string) $record->getKey())
                ->orderBy('entity_id')->first() : null,
        ]);
    }

    public function update(
        Request $request,
        BibliographicRecord $record,
        AuditLogger $audit,
        DataQualityScanner $scanner,
        DuplicateDetectionService $persistentDuplicates,
    ): RedirectResponse {
        $validated = $this->validated($request);
        $issuesBefore = DataQualityIssue::query()->actionable()
            ->where('entity_type', 'bibliographic_record')->where('entity_id', (string) $record->getKey())->count();

        DB::transaction(function () use ($validated, $record, $request, $audit): void {
            $old = $this->snapshot($record);
            $record->fill(collect($validated)->except(['translations', 'contributors', 'subjects'])->all());
            $record->is_draft = $record->missingRequiredFields() !== [];
            $record->save();
            if (array_key_exists('contributors', $validated)) {
                $this->syncContributors($record, (array) $validated['contributors']);
            }
            if (array_key_exists('subjects', $validated)) {
                $this->syncSubjects($record, (array) $validated['subjects']);
            }
            $this->syncTranslations($record, (array) ($validated['translations'] ?? []), $request, $audit);

            // 31.3: every correction keeps its before/after history.
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

        $issuesAfter = DataQualityIssue::query()->actionable()
            ->where('entity_type', 'bibliographic_record')->where('entity_id', (string) $record->getKey())->count();
        $fromDataQuality = $request->input('from') === 'data-quality';

        return redirect()
            ->route('librarian.catalog.edit', $fromDataQuality
                ? [$record, 'from' => 'data-quality', 'issue' => $request->integer('issue')]
                : $record)
            ->with('success', $fromDataQuality && $request->boolean('save_and_revalidate')
                ? __('data_quality.messages.revalidation_result', ['resolved' => max(0, $issuesBefore - $issuesAfter), 'remaining' => $issuesAfter])
                : __('common.updated_successfully'));
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
     * ДИР 6.3 — undo one recorded change. Restores only the fields that entry
     * actually altered, so reverting an old edit does not silently roll back
     * everything done since. The revert is itself audited, never a deletion of
     * history (31.3).
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
     * ДИР 6.3 "массовое редактирование". Restricted on purpose to shared,
     * non-identifying attributes: title/author/ISBN are unique per record and
     * must never be set from a bulk form. Every touched record gets its own
     * audit entry so 31.3 history stays per-record.
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
     * ДИР 6.2 — the librarian asks "is this already in the catalogue?" before
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
            'publication_place' => ['nullable', 'string', 'max:255'],
            'statement_of_responsibility' => ['nullable', 'string', 'max:1000'],
            'edition_statement' => ['nullable', 'string', 'max:255'],
            'publication_year' => ['nullable', 'integer', 'min:1500', 'max:2100'],
            'language' => ['required', Rule::in(BibliographicRecord::LANGUAGES)],
            'udc_code' => ['nullable', 'string', 'max:64'],
            'bbk_code' => ['nullable', 'string', 'max:64'],
            'local_classification' => ['nullable', 'string', 'max:128'],
            'author_mark' => ['nullable', 'string', 'max:16'],
            'category' => ['nullable', 'string', 'max:128'],
            'annotation' => ['nullable', 'string', 'max:10000'],
            'keywords' => ['nullable', 'string', 'max:2000'],
            'isbn' => ['nullable', 'string', 'max:32'],
            'issn' => ['nullable', 'string', 'max:32'],
            'physical_extent' => ['nullable', 'string', 'max:255'],
            'physical_details' => ['nullable', 'string', 'max:255'],
            'dimensions' => ['nullable', 'string', 'max:64'],
            'accompanying_material' => ['nullable', 'string', 'max:255'],
            'series_title' => ['nullable', 'string', 'max:500'],
            'series_number' => ['nullable', 'string', 'max:64'],
            'volume' => ['nullable', 'string', 'max:64'],
            'issue' => ['nullable', 'string', 'max:64'],
            'part_number' => ['nullable', 'string', 'max:64'],
            'part_title' => ['nullable', 'string', 'max:500'],
            'control_number' => ['nullable', 'string', 'max:128'],
            'country_code' => ['nullable', 'string', 'max:8'],
            'cataloging_language' => ['nullable', 'string', 'max:16'],
            'source_agency' => ['nullable', 'string', 'max:128'],
            'material_designation' => ['nullable', 'string', 'max:128'],
            'ksu_literature_type' => ['nullable', 'string', 'max:128'],
            'faculty' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'disciplines' => ['nullable', 'string', 'max:500'],
            'specialty' => ['nullable', 'string', 'max:1000'],
            'record_created_on' => ['nullable', 'date'],
            'resource_type' => ['required', Rule::in(BibliographicRecord::RESOURCE_TYPES)],
            'cover_path' => ['nullable', 'string', 'max:2048'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'contributors' => ['nullable', 'array', 'max:100'],
            'contributors.*' => ['array'],
            'contributors.*.name' => ['nullable', 'string', 'max:500'],
            'contributors.*.role' => ['nullable', Rule::in(Contributor::ROLES)],
            'contributors.*.kind' => ['nullable', Rule::in(Contributor::KINDS)],
            'contributors.*.marc_tag' => ['nullable', 'string', 'max:8'],
            'subjects' => ['nullable', 'array', 'max:100'],
            'subjects.*' => ['array'],
            'subjects.*.term' => ['nullable', 'string', 'max:500'],
            'subjects.*.scheme' => ['nullable', Rule::in(Subject::SCHEMES)],
            'subjects.*.marc_tag' => ['nullable', 'string', 'max:8'],
            // ДИР 6.3 — a human may queue a technically complete record for
            // review, so this is deliberately independent of is_draft.
            'needs_manual_review' => ['nullable', 'boolean'],
            'review_note' => ['nullable', 'string', 'max:500'],
            'translations' => ['nullable', 'array'],
            'translations.*' => ['array'],
            'translations.*.title' => ['nullable', 'string', 'max:1000'],
            'translations.*.annotation' => ['nullable', 'string', 'max:10000'],
            'translations.*.keywords' => ['nullable', 'string', 'max:2000'],
            'translations.*.translation_status' => ['nullable', Rule::in(BibliographicRecordTranslation::STATUSES)],
            'translations.*.source' => ['nullable', Rule::in(BibliographicRecordTranslation::SOURCES)],
        ]);

        $validated['additional_authors'] = $this->listFromText($validated['additional_authors'] ?? null);
        $validated['keywords'] = $this->listFromText($validated['keywords'] ?? null);
        if ($request->exists('contributors')) {
            $validated['contributors'] = collect((array) ($validated['contributors'] ?? []))
                ->map(static fn (array $contributor): array => [
                    'name' => trim((string) ($contributor['name'] ?? '')),
                    'role' => (string) ($contributor['role'] ?? 'author'),
                    'kind' => (string) ($contributor['kind'] ?? 'person'),
                    'marc_tag' => trim((string) ($contributor['marc_tag'] ?? '')) ?: null,
                ])
                ->filter(static fn (array $contributor): bool => $contributor['name'] !== '')
                ->values()
                ->all();
        } else {
            unset($validated['contributors']);
        }
        if ($request->exists('subjects')) {
            $validated['subjects'] = collect((array) ($validated['subjects'] ?? []))
                ->map(static fn (array $subject): array => [
                    'term' => trim((string) ($subject['term'] ?? '')),
                    'scheme' => (string) ($subject['scheme'] ?? 'topical'),
                    'marc_tag' => trim((string) ($subject['marc_tag'] ?? '')) ?: null,
                ])
                ->filter(static fn (array $subject): bool => $subject['term'] !== '')
                ->values()
                ->all();
        } else {
            unset($validated['subjects']);
        }
        $validated['needs_manual_review'] = $request->boolean('needs_manual_review');
        $validated['translations'] = collect((array) ($validated['translations'] ?? []))
            ->only(BibliographicRecordTranslation::LOCALES)
            ->map(function (array $translation): array {
                $translation['title'] = trim((string) ($translation['title'] ?? ''));
                $translation['annotation'] = trim(strip_tags((string) ($translation['annotation'] ?? '')));
                $translation['keywords'] = $this->listFromText($translation['keywords'] ?? null);
                $translation['translation_status'] = $translation['translation_status'] ?? 'draft';
                $translation['source'] = $translation['source'] ?? 'manual_translation';

                return $translation;
            })
            ->all();

        return $validated;
    }

    /**
     * @param  array<string, array<string, mixed>>  $translations
     */
    private function syncTranslations(
        BibliographicRecord $record,
        array $translations,
        Request $request,
        AuditLogger $audit,
    ): void {
        foreach (BibliographicRecordTranslation::LOCALES as $locale) {
            $payload = (array) ($translations[$locale] ?? []);
            $title = trim((string) ($payload['title'] ?? ''));
            $existing = $record->translations()->where('locale', $locale)->first();

            if ($title === '') {
                if ($existing !== null && $request->boolean("translations.{$locale}.remove")) {
                    $before = $existing->only(['locale', 'title', 'annotation', 'keywords', 'translation_status', 'source']);
                    $existing->delete();
                    $audit->logRequired(
                        actionType: 'metadata.translation.delete',
                        entityType: 'bibliographic_record',
                        entityId: $record->getKey(),
                        oldValues: $before,
                        scope: 'operational',
                        metadata: ['locale' => $locale],
                    );
                }

                continue;
            }

            $status = (string) ($payload['translation_status'] ?? 'draft');
            $values = [
                'title' => $title,
                'annotation' => trim((string) ($payload['annotation'] ?? '')) ?: null,
                'keywords' => array_values((array) ($payload['keywords'] ?? [])),
                'translation_status' => $status,
                'source' => (string) ($payload['source'] ?? 'manual_translation'),
                'translated_by' => $request->user()->getKey(),
                'reviewed_by' => in_array($status, BibliographicRecordTranslation::PUBLIC_STATUSES, true)
                    ? $request->user()->getKey()
                    : null,
                'reviewed_at' => in_array($status, BibliographicRecordTranslation::PUBLIC_STATUSES, true)
                    ? now('UTC')
                    : null,
            ];
            $before = $existing?->only(['locale', 'title', 'annotation', 'keywords', 'translation_status', 'source']);
            $translation = $record->translations()->updateOrCreate(['locale' => $locale], $values);
            $after = $translation->only(['locale', 'title', 'annotation', 'keywords', 'translation_status', 'source']);

            if ($before !== $after) {
                $audit->logRequired(
                    actionType: $existing === null ? 'metadata.translation.create' : 'metadata.translation.update',
                    entityType: 'bibliographic_record',
                    entityId: $record->getKey(),
                    oldValues: $before,
                    newValues: $after,
                    scope: 'operational',
                    metadata: ['locale' => $locale],
                );
            }
        }
    }

    /** @param list<array{name:string,role:string,kind:string,marc_tag:?string}> $contributors */
    private function syncContributors(BibliographicRecord $record, array $contributors): void
    {
        if (! DatabaseSchema::hasTable('contributors') || ! DatabaseSchema::hasTable('bibliographic_record_contributor')) {
            return;
        }

        $rows = [];
        $seen = [];
        $now = now('UTC');

        foreach ($contributors as $payload) {
            $name = trim((string) ($payload['name'] ?? ''));
            if ($name === '') {
                continue;
            }

            $normalized = Contributor::normalizeName($name);
            $contributor = Contributor::query()->firstOrCreate(
                ['normalized_name' => $normalized],
                ['name' => $name, 'kind' => (string) ($payload['kind'] ?? 'person')],
            );
            $role = (string) ($payload['role'] ?? 'author');
            $deduplicationKey = $contributor->getKey().'|'.$role;
            if (isset($seen[$deduplicationKey])) {
                continue;
            }
            $seen[$deduplicationKey] = true;

            $rows[] = [
                'bibliographic_record_id' => $record->getKey(),
                'contributor_id' => $contributor->getKey(),
                'role' => $role,
                'position' => count($rows),
                'marc_tag' => $payload['marc_tag'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('bibliographic_record_contributor')
            ->where('bibliographic_record_id', $record->getKey())
            ->delete();
        if ($rows !== []) {
            DB::table('bibliographic_record_contributor')->insert($rows);
        }
        $record->unsetRelation('contributors');
    }

    /** @param list<array{term:string,scheme:string,marc_tag:?string}> $subjects */
    private function syncSubjects(BibliographicRecord $record, array $subjects): void
    {
        if (! DatabaseSchema::hasTable('subjects') || ! DatabaseSchema::hasTable('bibliographic_record_subject')) {
            return;
        }

        $rows = [];
        $seen = [];
        $now = now('UTC');

        foreach ($subjects as $payload) {
            $term = trim((string) ($payload['term'] ?? ''));
            if ($term === '') {
                continue;
            }

            $scheme = (string) ($payload['scheme'] ?? 'topical');
            $subject = Subject::query()->firstOrCreate(
                ['normalized_term' => Subject::normalizeTerm($term), 'scheme' => $scheme],
                ['term' => $term],
            );
            if (isset($seen[$subject->getKey()])) {
                continue;
            }
            $seen[$subject->getKey()] = true;

            $rows[] = [
                'bibliographic_record_id' => $record->getKey(),
                'subject_id' => $subject->getKey(),
                'position' => count($rows),
                'marc_tag' => $payload['marc_tag'] ?? null,
                'created_at' => $now,
                'updated_at' => $now,
            ];
        }

        DB::table('bibliographic_record_subject')
            ->where('bibliographic_record_id', $record->getKey())
            ->delete();
        if ($rows !== []) {
            DB::table('bibliographic_record_subject')->insert($rows);
        }
        $record->unsetRelation('subjects');
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
            'publication_place', 'statement_of_responsibility', 'edition_statement',
            'publication_year', 'language', 'udc_code', 'author_mark', 'category',
            'bbk_code', 'local_classification', 'annotation', 'keywords', 'isbn', 'issn',
            'physical_extent', 'physical_details', 'dimensions', 'accompanying_material',
            'series_title', 'series_number', 'volume', 'issue', 'part_number', 'part_title',
            'control_number', 'country_code', 'cataloging_language', 'source_agency',
            'material_designation', 'ksu_literature_type', 'faculty', 'department',
            'disciplines', 'specialty', 'record_created_on',
            'resource_type', 'cover_path', 'notes', 'is_draft',
            'needs_manual_review', 'review_note',
        ]) + [
            'contributors' => DatabaseSchema::hasTable('bibliographic_record_contributor')
                ? $record->contributors()->get()->map(fn (Contributor $contributor): array => [
                    'name' => $contributor->name,
                    'role' => $contributor->pivot?->role,
                    'kind' => $contributor->kind,
                ])->values()->all()
                : [],
            'subjects' => DatabaseSchema::hasTable('bibliographic_record_subject')
                ? $record->subjects()->get()->map(fn (Subject $subject): array => [
                    'term' => $subject->term,
                    'scheme' => $subject->scheme,
                ])->values()->all()
                : [],
        ];
    }
}
