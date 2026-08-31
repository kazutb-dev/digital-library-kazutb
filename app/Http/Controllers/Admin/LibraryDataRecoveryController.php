<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Catalog\LegacyMarcField;
use App\Models\Catalog\LegacyMarcRecord;
use App\Models\Ksu\KsuBook;
use App\Models\Recovery\LegacyImportBatch;
use App\Models\Recovery\LegacyImportConflict;
use App\Models\Recovery\LegacyImportQuarantine;
use App\Support\DatabaseSchema;
use Illuminate\Database\Eloquent\Builder as EloquentBuilder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Read-only technical control plane for the completed MARC-SQL recovery.
 *
 * This controller deliberately has no import, load, apply, conflict-resolution,
 * quarantine-resolution, sequence-allocation or raw-record mutation endpoint.
 */
final class LibraryDataRecoveryController extends Controller
{
    /** @var array<string,string> */
    private const RECOVERY_TABLES = [
        'legacy_import_batches' => 'batches',
        'legacy_marc_records' => 'raw_records',
        'legacy_marc_fields' => 'raw_fields',
        'legacy_marc_copies' => 'raw_copies',
        'legacy_import_quarantine' => 'quarantine',
        'legacy_import_conflicts' => 'conflicts',
        'contributors' => 'contributors',
        'subjects' => 'subjects',
        'ksu_books' => 'ksu_books',
        'ksu_sequences' => 'ksu_sequences',
        'ksu_entries' => 'ksu_entries',
        'ksu_entry_items' => 'ksu_items',
        'ksu_conflicts' => 'ksu_conflicts',
    ];

    public function index(Request $request): View
    {
        $this->authorizeView($request);

        $tables = collect(self::RECOVERY_TABLES)->map(function (string $label, string $table): array {
            $available = DatabaseSchema::hasTable($table);

            return [
                'table' => $table,
                'label' => $label,
                'available' => $available,
                'count' => $available ? DB::table($table)->count() : null,
            ];
        })->values();

        $batchCounts = [
            'raw_records' => $this->countsByBatch('legacy_marc_records'),
            'raw_fields' => $this->countsByBatch('legacy_marc_fields'),
            'raw_copies' => $this->countsByBatch('legacy_marc_copies'),
            'quarantine' => $this->countsByBatch('legacy_import_quarantine'),
            'conflicts' => $this->countsByBatch('legacy_import_conflicts'),
        ];

        $batches = DatabaseSchema::hasTable('legacy_import_batches')
            ? LegacyImportBatch::query()->latest('id')->limit(20)->get()
                ->map(function (LegacyImportBatch $batch) use ($batchCounts): array {
                    $id = (int) $batch->getKey();

                    return [
                        'model' => $batch,
                        'counts' => collect($batchCounts)->map(
                            static fn (array $counts): int => (int) ($counts[$id] ?? 0),
                        )->all(),
                        'matches' => $this->batchMatches($batch),
                        'detail_url' => $this->destination(
                            'admin.library-recovery.batches.show',
                            '/admin/library-recovery/batches/'.$id,
                            [$id],
                        ),
                    ];
                })
            : collect();

        return view('admin.library-recovery.index', [
            'tables' => $tables,
            'batches' => $batches,
            'health' => $this->technicalHealth(),
            'mappingSummary' => $this->mappingSummary(),
            'sourceSummary' => $this->groupedCounts('legacy_import_batches', ['source_system', 'source_database']),
            'quarantineSummary' => $this->groupedCounts('legacy_import_quarantine', ['kind', 'status']),
            'conflictSummary' => $this->groupedCounts('legacy_import_conflicts', ['entity_type', 'field_name', 'status']),
            'ksuBooks' => $this->ksuBooks(),
            'links' => $this->links(),
            'canManage' => (bool) $request->user()?->can('legacy_recovery.manage'),
        ]);
    }

    public function batch(Request $request, int $batchId): View
    {
        $this->authorizeView($request);
        $this->requireTable('legacy_import_batches');
        $batch = LegacyImportBatch::query()->findOrFail($batchId);

        $counts = collect([
            'raw_records' => 'legacy_marc_records',
            'raw_fields' => 'legacy_marc_fields',
            'raw_copies' => 'legacy_marc_copies',
            'quarantine' => 'legacy_import_quarantine',
            'conflicts' => 'legacy_import_conflicts',
        ])->map(fn (string $table): ?int => DatabaseSchema::hasTable($table)
            ? DB::table($table)->where('legacy_import_batch_id', $batchId)->count()
            : null);

        $rawRecords = DatabaseSchema::hasTable('legacy_marc_records')
            ? LegacyMarcRecord::query()->where('legacy_import_batch_id', $batchId)
                ->latest('id')->limit(25)->get()->each(function (LegacyMarcRecord $record): void {
                    $record->setAttribute('detail_url', $this->rawRecordUrl((int) $record->getKey()));
                })
            : collect();

        return view('admin.library-recovery.batch', [
            'batch' => $batch,
            'counts' => $counts,
            'matches' => $this->batchMatches($batch),
            'rawRecords' => $rawRecords,
            'quarantineSummary' => $this->groupedCounts(
                'legacy_import_quarantine',
                ['kind', 'status'],
                $batchId,
            ),
            'conflictSummary' => $this->groupedCounts(
                'legacy_import_conflicts',
                ['entity_type', 'field_name', 'status'],
                $batchId,
            ),
            'links' => $this->links(),
        ]);
    }

    public function rawRecords(Request $request): View
    {
        $this->authorizeView($request);
        $this->requireTable('legacy_marc_records');
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'batch_id' => ['nullable', 'integer', 'min:1'],
            'mapping_status' => ['nullable', 'string', 'max:64'],
            'apply_status' => ['nullable', 'string', 'max:32'],
            'tag' => ['nullable', 'regex:/^[0-9A-Za-z]{1,8}$/'],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));

        $query = LegacyMarcRecord::query()
            ->when(isset($filters['batch_id']), fn (EloquentBuilder $builder) => $builder
                ->where('legacy_import_batch_id', $filters['batch_id']))
            ->when(filled($filters['mapping_status'] ?? null), fn (EloquentBuilder $builder) => $builder
                ->where('mapping_status', $filters['mapping_status']))
            ->when(filled($filters['apply_status'] ?? null), fn (EloquentBuilder $builder) => $builder
                ->where('apply_status', $filters['apply_status']))
            ->when($search !== '', function (EloquentBuilder $builder) use ($search): void {
                $builder->where(function (EloquentBuilder $scope) use ($search): void {
                    $scope->where('control_number', 'like', '%'.$search.'%')
                        ->orWhere('source_hash', 'like', '%'.$search.'%');
                    if (ctype_digit($search)) {
                        $scope->orWhere('source_doc_id', (int) $search)
                            ->orWhere('bibliographic_record_id', (int) $search);
                    }
                });
            });

        if (filled($filters['tag'] ?? null) && DatabaseSchema::hasTable('legacy_marc_fields')) {
            $tag = strtoupper((string) $filters['tag']);
            $query->whereExists(function ($fields) use ($tag): void {
                $fields->selectRaw('1')->from('legacy_marc_fields')
                    ->whereColumn('legacy_marc_fields.legacy_import_batch_id', 'legacy_marc_records.legacy_import_batch_id')
                    ->whereColumn('legacy_marc_fields.source_doc_id', 'legacy_marc_records.source_doc_id')
                    ->where('legacy_marc_fields.tag', $tag);
            });
        }

        $records = $query->latest('id')->paginate(40)->withQueryString();
        $records->getCollection()->each(function (LegacyMarcRecord $record): void {
            $record->setAttribute('detail_url', $this->rawRecordUrl((int) $record->getKey()));
        });

        return view('admin.library-recovery.raw-records', [
            'records' => $records,
            'filters' => $filters,
            'links' => $this->links(),
            'mappingStatuses' => $this->distinctValues('legacy_marc_records', 'mapping_status'),
            'applyStatuses' => $this->distinctValues('legacy_marc_records', 'apply_status'),
        ]);
    }

    public function rawRecord(Request $request, int $recordId): View
    {
        $this->authorizeView($request);
        $this->requireTable('legacy_marc_records');
        $record = LegacyMarcRecord::query()->with('bibliographicRecord:id,title')->findOrFail($recordId);
        $fields = DatabaseSchema::hasTable('legacy_marc_fields')
            ? LegacyMarcField::query()
                ->where('legacy_import_batch_id', $record->legacy_import_batch_id)
                ->where('source_doc_id', $record->source_doc_id)
                ->orderBy('tag')->orderBy('occurrence')->orderBy('id')
                ->paginate(100)->withQueryString()
            : new LengthAwarePaginator([], 0, 100);

        return view('admin.library-recovery.raw-record', [
            'record' => $record,
            'fields' => $fields,
            'links' => $this->links(),
        ]);
    }

    public function quarantine(Request $request): View
    {
        $this->authorizeView($request);
        $this->requireTable('legacy_import_quarantine');
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'batch_id' => ['nullable', 'integer', 'min:1'],
            'kind' => ['nullable', 'string', 'max:64'],
            'status' => ['nullable', 'string', 'max:32'],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $rows = LegacyImportQuarantine::query()->with('batch:id,package_name')
            ->when(isset($filters['batch_id']), fn (EloquentBuilder $builder) => $builder
                ->where('legacy_import_batch_id', $filters['batch_id']))
            ->when(filled($filters['kind'] ?? null), fn (EloquentBuilder $builder) => $builder
                ->where('kind', $filters['kind']))
            ->when(filled($filters['status'] ?? null), fn (EloquentBuilder $builder) => $builder
                ->where('status', $filters['status']))
            ->when($search !== '', function (EloquentBuilder $builder) use ($search): void {
                $builder->where(function (EloquentBuilder $scope) use ($search): void {
                    $scope->where('reason', 'like', '%'.$search.'%');
                    if (ctype_digit($search)) {
                        $scope->orWhere('source_doc_id', (int) $search)
                            ->orWhere('source_inv_id', (int) $search);
                    }
                });
            })
            ->latest('id')->paginate(40)->withQueryString();
        $rows->getCollection()->each(function (LegacyImportQuarantine $row): void {
            $id = (int) $row->getKey();
            $row->setAttribute('detail_url', $this->destination(
                'admin.library-recovery.quarantine.show',
                '/admin/library-recovery/quarantine/'.$id,
                [$id],
            ));
        });

        return view('admin.library-recovery.quarantine', [
            'rows' => $rows,
            'filters' => $filters,
            'summary' => $this->groupedCounts('legacy_import_quarantine', ['kind', 'status']),
            'kinds' => $this->distinctValues('legacy_import_quarantine', 'kind'),
            'statuses' => $this->distinctValues('legacy_import_quarantine', 'status'),
            'links' => $this->links(),
        ]);
    }

    public function quarantineItem(Request $request, int $quarantineId): View
    {
        $this->authorizeView($request);
        $this->requireTable('legacy_import_quarantine');
        $row = LegacyImportQuarantine::query()->with('batch:id,package_name,package_sha256,status')
            ->findOrFail($quarantineId);

        return view('admin.library-recovery.quarantine-item', [
            'row' => $row,
            'links' => $this->links(),
            'canManage' => (bool) $request->user()?->can('legacy_recovery.manage'),
        ]);
    }

    public function conflicts(Request $request): View
    {
        $this->authorizeView($request);
        $this->requireTable('legacy_import_conflicts');
        $filters = $request->validate([
            'q' => ['nullable', 'string', 'max:200'],
            'batch_id' => ['nullable', 'integer', 'min:1'],
            'entity_type' => ['nullable', 'string', 'max:64'],
            'field_name' => ['nullable', 'string', 'max:128'],
            'status' => ['nullable', 'string', 'max:32'],
        ]);
        $search = trim((string) ($filters['q'] ?? ''));
        $rows = LegacyImportConflict::query()->with(['batch:id,package_name', 'resolver:id,name'])
            ->when(isset($filters['batch_id']), fn (EloquentBuilder $builder) => $builder
                ->where('legacy_import_batch_id', $filters['batch_id']))
            ->when(filled($filters['entity_type'] ?? null), fn (EloquentBuilder $builder) => $builder
                ->where('entity_type', $filters['entity_type']))
            ->when(filled($filters['field_name'] ?? null), fn (EloquentBuilder $builder) => $builder
                ->where('field_name', $filters['field_name']))
            ->when(filled($filters['status'] ?? null), fn (EloquentBuilder $builder) => $builder
                ->where('status', $filters['status']))
            ->when($search !== '', function (EloquentBuilder $builder) use ($search): void {
                $builder->where(function (EloquentBuilder $scope) use ($search): void {
                    $scope->where('reason', 'like', '%'.$search.'%')
                        ->orWhere('current_value', 'like', '%'.$search.'%')
                        ->orWhere('incoming_value', 'like', '%'.$search.'%');
                    if (ctype_digit($search)) {
                        $scope->orWhere('source_id', (int) $search)
                            ->orWhere('entity_id', (int) $search);
                    }
                });
            })
            ->latest('id')->paginate(40)->withQueryString();
        $rows->getCollection()->each(function (LegacyImportConflict $row): void {
            $id = (int) $row->getKey();
            $row->setAttribute('detail_url', $this->destination(
                'admin.library-recovery.conflicts.show',
                '/admin/library-recovery/conflicts/'.$id,
                [$id],
            ));
        });

        return view('admin.library-recovery.conflicts', [
            'rows' => $rows,
            'filters' => $filters,
            'summary' => $this->groupedCounts('legacy_import_conflicts', ['entity_type', 'field_name', 'status']),
            'entityTypes' => $this->distinctValues('legacy_import_conflicts', 'entity_type'),
            'fieldNames' => $this->distinctValues('legacy_import_conflicts', 'field_name'),
            'statuses' => $this->distinctValues('legacy_import_conflicts', 'status'),
            'links' => $this->links(),
        ]);
    }

    public function conflict(Request $request, int $conflictId): View
    {
        $this->authorizeView($request);
        $this->requireTable('legacy_import_conflicts');
        $conflict = LegacyImportConflict::query()->with([
            'batch:id,package_name,package_sha256,status',
            'resolver:id,name',
        ])->findOrFail($conflictId);

        return view('admin.library-recovery.conflict', [
            'conflict' => $conflict,
            'links' => $this->links(),
            'canManage' => (bool) $request->user()?->can('legacy_recovery.manage'),
        ]);
    }

    /**
     * No decision is performed here. The existing librarian workflow owns all
     * audited resolution services; this endpoint only enforces admin manage
     * permission before handing the user over to that workflow.
     */
    public function librarianReview(Request $request): RedirectResponse
    {
        abort_unless($request->user()?->can('legacy_recovery.manage'), 403);
        $queue = $request->validate([
            'queue' => ['nullable', Rule::in(['fund_raw', 'quarantine', 'conflicts', 'without_ksu'])],
        ])['queue'] ?? null;
        $parameters = $queue === null ? [] : ['queue' => $queue];

        return redirect()->to($this->destination(
            'librarian.recovery',
            '/librarian/recovery'.($queue === null ? '' : '?'.http_build_query($parameters)),
            $parameters,
        ));
    }

    private function authorizeView(Request $request): void
    {
        abort_unless($request->user()?->can('legacy_recovery.view'), 403);
    }

    private function requireTable(string $table): void
    {
        abort_unless(DatabaseSchema::hasTable($table), 503, __('library_recovery.table_unavailable'));
    }

    /** @return array<int,int> */
    private function countsByBatch(string $table): array
    {
        if (! DatabaseSchema::hasTable($table)) {
            return [];
        }

        return DB::table($table)->select('legacy_import_batch_id')
            ->selectRaw('COUNT(*) AS total')
            ->groupBy('legacy_import_batch_id')
            ->pluck('total', 'legacy_import_batch_id')
            ->map(static fn ($count): int => (int) $count)
            ->all();
    }

    /** @return array{documents:bool,copies:bool,fields:bool,all:bool} */
    private function batchMatches(LegacyImportBatch $batch): array
    {
        $matches = [
            'documents' => (int) $batch->documents_expected === (int) $batch->documents_loaded,
            'copies' => (int) $batch->copies_expected === (int) $batch->copies_loaded,
            'fields' => (int) $batch->fields_expected === (int) $batch->fields_loaded,
        ];

        return $matches + ['all' => ! in_array(false, $matches, true)];
    }

    /** @return array<string,int|null> */
    private function technicalHealth(): array
    {
        return [
            'batch_count_mismatch' => $this->conditionalCount('legacy_import_batches', fn ($query) => $query
                ->whereColumn('documents_expected', '!=', 'documents_loaded')
                ->orWhereColumn('copies_expected', '!=', 'copies_loaded')
                ->orWhereColumn('fields_expected', '!=', 'fields_loaded')),
            'open_quarantine' => $this->conditionalCount('legacy_import_quarantine', fn ($query) => $query->where('status', 'open')),
            'open_conflicts' => $this->conditionalCount('legacy_import_conflicts', fn ($query) => $query->where('status', 'open')),
            'unlinked_records' => $this->conditionalCount('legacy_marc_records', fn ($query) => $query->whereNull('bibliographic_record_id')),
            'unlinked_copies' => $this->conditionalCount('legacy_marc_copies', fn ($query) => $query->whereNull('book_copy_id')),
            'unknown_marc_fields' => $this->conditionalCount('legacy_marc_fields', fn ($query) => $query->where('is_known_tag', false)),
            'pending_raw_records' => $this->conditionalCount('legacy_marc_records', fn ($query) => $query->where('apply_status', 'pending')),
            'pending_raw_copies' => $this->conditionalCount('legacy_marc_copies', fn ($query) => $query->where('apply_status', 'pending')),
            'open_ksu_conflicts' => $this->conditionalCount('ksu_conflicts', fn ($query) => $query->where('status', 'open')),
            'unsafe_ksu_sequences' => $this->conditionalCount('ksu_sequences', fn ($query) => $query->where('allocation_enabled', false)),
        ];
    }

    private function conditionalCount(string $table, callable $scope): ?int
    {
        if (! DatabaseSchema::hasTable($table)) {
            return null;
        }

        return (int) $scope(DB::table($table))->count();
    }

    /** @return array<string,Collection<int,object>> */
    private function mappingSummary(): array
    {
        return [
            'records' => $this->groupedCounts('legacy_marc_records', ['mapping_status', 'apply_status']),
            'copies' => $this->groupedCounts('legacy_marc_copies', ['relation_status', 'apply_status']),
        ];
    }

    /** @param list<string> $columns @return Collection<int,object> */
    private function groupedCounts(string $table, array $columns, ?int $batchId = null): Collection
    {
        if (! DatabaseSchema::hasTable($table)) {
            return collect();
        }

        return DB::table($table)->select($columns)->selectRaw('COUNT(*) AS total')
            ->when($batchId !== null, fn ($query) => $query->where('legacy_import_batch_id', $batchId))
            ->groupBy($columns)->orderByDesc('total')->get();
    }

    /** @return Collection<int,string> */
    private function distinctValues(string $table, string $column): Collection
    {
        if (! DatabaseSchema::hasTable($table)) {
            return collect();
        }

        return DB::table($table)->whereNotNull($column)->where($column, '!=', '')
            ->distinct()->orderBy($column)->pluck($column);
    }

    /** @return Collection<int,KsuBook> */
    private function ksuBooks(): Collection
    {
        if (! DatabaseSchema::hasTable('ksu_books') || ! DatabaseSchema::hasTable('ksu_sequences')) {
            return collect();
        }

        return KsuBook::query()
            ->with(['sequences' => fn ($query) => $query->orderByDesc('year')])
            ->when(DatabaseSchema::hasTable('ksu_entries'), fn (EloquentBuilder $query) => $query->withCount('entries'))
            ->when(DatabaseSchema::hasTable('ksu_conflicts'), fn (EloquentBuilder $query) => $query
                ->withCount('conflicts')
                ->withCount(['conflicts as open_conflicts_count' => fn ($conflicts) => $conflicts->where('status', 'open')]))
            ->orderBy('sort_order')->orderBy('code')->get();
    }

    /** @return array<string,string|null> */
    private function links(): array
    {
        return [
            'index' => $this->destination('admin.library-recovery.index', '/admin/library-recovery'),
            'raw_records' => $this->destination('admin.library-recovery.raw.index', '/admin/library-recovery/raw'),
            'quarantine' => $this->destination('admin.library-recovery.quarantine.index', '/admin/library-recovery/quarantine'),
            'conflicts' => $this->destination('admin.library-recovery.conflicts.index', '/admin/library-recovery/conflicts'),
            'review' => Route::has('admin.library-recovery.review')
                ? route('admin.library-recovery.review')
                : null,
        ];
    }

    private function rawRecordUrl(int $id): string
    {
        return $this->destination(
            'admin.library-recovery.raw.show',
            '/admin/library-recovery/raw/'.$id,
            [$id],
        );
    }

    /** @param array<int|string,mixed> $parameters */
    private function destination(string $route, string $fallback, array $parameters = []): string
    {
        return Route::has($route) ? route($route, $parameters) : url($fallback);
    }
}
