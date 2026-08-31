<?php

namespace App\Http\Controllers\Librarian;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\Fine;
use App\Models\DataQualityIssue;
use App\Models\Fund;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Services\Catalog\BarcodeMarkingService;
use App\Services\Catalog\CopyWriteOffService;
use App\Services\Catalog\MachineCodeService;
use App\Services\Catalog\ReservationQueueService;
use App\Services\DataQuality\DataQualityScanner;
use App\Services\Operations\KsuOperationsService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Copy / inventory management (Master.md 12, 14.4-14.6): per-copy cards
 * with full history, bulk intake, and status actions (write-off, lost,
 * repair) that always leave a trace and, where required, a fine.
 */
class CopyController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'search' => ['nullable', 'string', 'max:200'],
            'inventory_number' => ['nullable', 'string', 'max:64'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'title' => ['nullable', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'max:64'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'fund_id' => ['nullable', 'integer', 'exists:funds,id'],
            'status' => ['nullable', Rule::in(BookCopy::STATUSES)],
            'inventory_status' => ['nullable', Rule::in(BookCopy::INVENTORY_STATUSES)],
            'circulation_status' => ['nullable', Rule::in(BookCopy::CIRCULATION_STATUSES)],
            'condition' => ['nullable', Rule::in(BookCopy::CONDITIONS)],
            'barcode_status' => ['nullable', Rule::in(['with', 'without'])],
            'ksu_status' => ['nullable', Rule::in(['with', 'without'])],
            'ksu_number' => ['nullable', 'string', 'max:64'],
            'storage_sigla' => ['nullable', 'string', 'max:64'],
            'service_point_code' => ['nullable', 'string', 'max:64'],
            'shelf_location' => ['nullable', 'string', 'max:100'],
            'shelf_index' => ['nullable', 'string', 'max:128'],
            'keywords' => ['nullable', 'string', 'max:255'],
            'publication_place' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'publication_year' => ['nullable', 'string', 'max:32'],
            'series' => ['nullable', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'max:32'],
            'faculty' => ['nullable', 'string', 'max:255'],
            'department' => ['nullable', 'string', 'max:255'],
            'discipline' => ['nullable', 'string', 'max:255'],
            'specialty' => ['nullable', 'string', 'max:255'],
            'received_from' => ['nullable', 'string', 'max:255'],
            'invoice' => ['nullable', 'string', 'max:255'],
            'registration_from' => ['nullable', 'date'],
            'registration_to' => ['nullable', 'date', 'after_or_equal:registration_from'],
            'price_min' => ['nullable', 'numeric', 'min:0', 'max:1000000000'],
            'price_max' => ['nullable', 'numeric', 'min:0', 'max:1000000000', 'gte:price_min'],
            'acquisition_source' => ['nullable', 'string', 'max:255'],
            'accounting_type' => ['nullable', 'string', 'max:32'],
            'written_off' => ['nullable', Rule::in(['yes', 'no'])],
            'record_id' => ['nullable', 'integer', 'exists:bibliographic_records,id'],
        ]);

        $query = BookCopy::query()->with(['bibliographicRecord', 'branch', 'fund']);

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($needle): void {
                $builder
                    ->whereRaw('LOWER(inventory_number) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(barcode, \'\')) LIKE ?', [$needle])
                    ->orWhereHas('bibliographicRecord', fn (Builder $record) => $record
                        ->whereRaw('LOWER(title) LIKE ?', [$needle])
                        ->orWhereRaw("LOWER(COALESCE(primary_author, '')) LIKE ?", [$needle])
                        ->orWhereRaw('LOWER(COALESCE(isbn, \'\')) LIKE ?', [$needle]));
            });
        }
        foreach (['branch_id', 'fund_id', 'status', 'condition'] as $column) {
            if ($value = ($filters[$column] ?? null)) {
                $query->where($column, $value);
            }
        }
        if ($recordId = ($filters['record_id'] ?? null)) {
            $query->where('bibliographic_record_id', $recordId);
        }
        foreach (['inventory_number', 'barcode', 'ksu_number', 'storage_sigla', 'service_point_code', 'shelf_location', 'shelf_index', 'accounting_type'] as $column) {
            if ($value = trim((string) ($filters[$column] ?? ''))) {
                $query->where(fn (Builder $builder) => $this->applyLike($builder, $column, $value));
            }
        }
        foreach (['title', 'author', 'isbn'] as $recordField) {
            if ($value = trim((string) ($filters[$recordField] ?? ''))) {
                $column = match ($recordField) {
                    'author' => 'primary_author',
                    default => $recordField,
                };
                $query->whereHas('bibliographicRecord', fn (Builder $record) => $record
                    ->where(fn (Builder $inner) => $this->applyLike($inner, $column, $value)));
            }
        }
        // MARC-style bibliographic attributes. Columns added by the recovery /
        // academic migrations are gated so a partly-migrated schema never errors.
        $recordColumns = [
            'publication_place' => 'publication_place',
            'publisher' => 'publisher',
            'series' => 'series_title',
            'faculty' => 'faculty',
            'department' => 'department',
            'discipline' => 'disciplines',
            'specialty' => 'specialty',
        ];
        foreach ($recordColumns as $field => $column) {
            if (! Schema::hasColumn('bibliographic_records', $column)) {
                continue;
            }
            if ($value = trim((string) ($filters[$field] ?? ''))) {
                $query->whereHas('bibliographicRecord', fn (Builder $record) => $record
                    ->where(fn (Builder $inner) => $this->applyLike($inner, $column, $value)));
            }
        }
        if ($keywords = trim((string) ($filters['keywords'] ?? ''))) {
            $query->whereHas('bibliographicRecord', fn (Builder $record) => $record
                ->where(fn (Builder $inner) => $inner
                    ->whereRaw('LOWER(CAST(keywords AS TEXT)) LIKE ?', ['%'.mb_strtolower($keywords).'%'])
                    ->orWhereRaw('CAST(keywords AS TEXT) LIKE ?', ['%'.$keywords.'%'])
                    ->orWhereJsonContains('keywords', $keywords)));
        }
        if ($year = trim((string) ($filters['publication_year'] ?? ''))) {
            $query->whereHas('bibliographicRecord', fn (Builder $record) => $record
                ->whereRaw("LOWER(COALESCE(CAST(publication_year AS TEXT), '')) LIKE ?", ['%'.mb_strtolower($year).'%']));
        }
        if ($language = trim((string) ($filters['language'] ?? ''))) {
            $query->whereHas('bibliographicRecord', fn (Builder $record) => $record
                ->where(fn (Builder $inner) => $this->applyLike($inner, 'language', $language)));
        }
        if ($receivedFrom = trim((string) ($filters['received_from'] ?? ''))) {
            $query->where(fn (Builder $builder) => $this->applyLike($builder, 'acquisition_source', $receivedFrom)
                ->orWhere(fn (Builder $supplier) => $this->applyLike($supplier, 'supplier_name', $receivedFrom)));
        }
        if ($invoice = trim((string) ($filters['invoice'] ?? ''))) {
            $query->where(function (Builder $builder) use ($invoice): void {
                $this->applyLike($builder, 'supplier_name', $invoice);
                if (Schema::hasColumn('book_copies', 'ksu_entry_id')) {
                    $builder->orWhereHas('ksuEntry', fn (Builder $entry) => $entry
                        ->where(fn (Builder $inner) => $this->applyLike($inner, 'act_number', $invoice)));
                }
            });
        }
        if (($filters['barcode_status'] ?? null) === 'with') {
            $query->whereNotNull('barcode')->where('barcode', '!=', '');
        } elseif (($filters['barcode_status'] ?? null) === 'without') {
            $query->where(fn (Builder $builder) => $builder->whereNull('barcode')->orWhere('barcode', ''));
        }
        if (($filters['ksu_status'] ?? null) === 'with') {
            $query->whereNotNull('ksu_number')->where('ksu_number', '!=', '');
        } elseif (($filters['ksu_status'] ?? null) === 'without') {
            $query->where(fn (Builder $builder) => $builder->whereNull('ksu_number')->orWhere('ksu_number', ''));
        }
        if ($from = ($filters['registration_from'] ?? null)) {
            $query->whereDate('registration_date', '>=', $from);
        }
        if ($to = ($filters['registration_to'] ?? null)) {
            $query->whereDate('registration_date', '<=', $to);
        }
        if (array_key_exists('price_min', $filters) && $filters['price_min'] !== null) {
            $query->where('price', '>=', $filters['price_min']);
        }
        if (array_key_exists('price_max', $filters) && $filters['price_max'] !== null) {
            $query->where('price', '<=', $filters['price_max']);
        }
        if ($source = ($filters['acquisition_source'] ?? null)) {
            $query->where('acquisition_source', $source);
        }
        if (($filters['written_off'] ?? null) === 'yes') {
            $query->where('status', 'written_off');
        } elseif (($filters['written_off'] ?? null) === 'no') {
            $query->where('status', '!=', 'written_off');
        }
        if (Schema::hasColumns('book_copies', ['inventory_status', 'circulation_status'])) {
            foreach (['inventory_status', 'circulation_status'] as $column) {
                if ($value = ($filters[$column] ?? null)) {
                    $query->where($column, $value);
                }
            }
        }

        $copies = $query->orderByDesc('updated_at')->paginate(Setting::resultsPerPage())->withQueryString();
        $qualityByCopy = DataQualityIssue::query()->actionable()->where('entity_type', 'book_copy')
            ->whereIn('entity_id', $copies->getCollection()->pluck('id')->map(fn ($id) => (string) $id))
            ->get()->groupBy('entity_id');

        return view('librarian.copies.index', [
            'copies' => $copies,
            'filters' => $filters,
            'branches' => Branch::query()->orderBy('name')->get(),
            'funds' => Fund::query()->orderBy('name')->get(),
            'accountingTypes' => BookCopy::query()->whereNotNull('accounting_type')->where('accounting_type', '!=', '')
                ->distinct()->orderBy('accounting_type')->pluck('accounting_type'),
            'sourceOptions' => BookCopy::query()->whereNotNull('acquisition_source')->where('acquisition_source', '!=', '')
                ->selectRaw('acquisition_source, count(*) as aggregate')->groupBy('acquisition_source')
                ->orderByDesc('aggregate')->limit(100)->pluck('acquisition_source'),
            'servicePoints' => Schema::hasColumn('book_copies', 'service_point_code')
                ? BookCopy::query()->whereNotNull('service_point_code')->where('service_point_code', '!=', '')
                    ->distinct()->orderBy('service_point_code')->limit(200)->pluck('service_point_code')
                : collect(),
            'statusCounts' => BookCopy::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'markingStats' => [
                'total' => BookCopy::query()->count(),
                'with' => BookCopy::query()->whereNotNull('barcode')->where('barcode', '!=', '')->count(),
                'without' => BookCopy::query()->where(fn (Builder $builder) => $builder->whereNull('barcode')->orWhere('barcode', ''))->count(),
            ],
            'qualityByCopy' => $qualityByCopy,
        ]);
    }

    /**
     * Case-insensitive on PostgreSQL through LOWER(), with a native LIKE
     * fallback so the SQLite test connection still matches Cyrillic input.
     */
    private function applyLike(Builder $builder, string $column, string $value): Builder
    {
        return $builder
            ->whereRaw("LOWER(COALESCE({$column}, '')) LIKE ?", ['%'.mb_strtolower($value).'%'])
            ->orWhere($column, 'like', '%'.$value.'%');
    }

    public function show(BookCopy $copy, BarcodeMarkingService $marking): View
    {
        $copy->load([
            'bibliographicRecord',
            'branch',
            'fund',
            'activeLoan.reader',
            'activeReservation.reader',
            'loans' => fn ($query) => $query->with('reader')->latest('issued_at'),
            'reservations' => fn ($query) => $query->with('reader')->latest(),
            'history.user',
            'history.actor',
            'history.loan',
        ]);

        return view('librarian.copies.show', [
            'copy' => $copy,
            'qualityIssues' => DataQualityIssue::query()->actionable()
                ->where('entity_type', 'book_copy')->where('entity_id', (string) $copy->getKey())
                ->orderByRaw("CASE severity WHEN 'critical' THEN 1 WHEN 'high' THEN 2 WHEN 'medium' THEN 3 ELSE 4 END")
                ->get(),
            'markingState' => $marking->state($copy),
        ]);
    }

    public function writeOffForm(): View
    {
        return view('librarian.copies.write-off');
    }

    public function batchWriteOff(Request $request, CopyWriteOffService $writeOffs): RedirectResponse
    {
        $validated = $request->validate([
            'copy_codes' => ['required', 'string', 'max:50000'],
            'writeoff_date' => ['required', 'date'],
            'writeoff_act' => ['required', 'string', 'max:128'],
            'writeoff_reason' => ['required', 'string', 'min:5', 'max:2000'],
            'confirmed' => ['accepted'],
        ]);
        $codes = preg_split('/[\s,;]+/u', trim($validated['copy_codes'])) ?: [];
        $result = $writeOffs->writeOffByCodes(
            $codes, $validated['writeoff_date'], $validated['writeoff_act'],
            $validated['writeoff_reason'], $request->user(),
        );

        return redirect()->route('librarian.ksu.show', $result['ksu_entry_id'])
            ->with('success', __('copy_writeoff.messages.completed', [
                'count' => $result['copies']->count(), 'act' => $validated['writeoff_act'],
            ]));
    }

    public function create(Request $request): View
    {
        $record = null;
        if ($recordId = $request->query('record')) {
            $record = BibliographicRecord::query()->find($recordId);
        }

        return view('librarian.copies.form', [
            'record' => $record,
            'records' => $record === null
                ? BibliographicRecord::query()->orderBy('title')->limit(200)->get(['id', 'title', 'publication_year'])
                : collect(),
            'branches' => Branch::query()->orderBy('name')->get(),
            'funds' => Fund::query()->orderBy('name')->get(),
        ]);
    }

    /**
     * Single or bulk intake: quantity N generates N copies with sequential
     * inventory numbers from the given base (Master.md ТЗ Этап 4).
     */
    public function store(Request $request, AuditLogger $audit, DataQualityScanner $scanner): RedirectResponse
    {
        $validated = $request->validate([
            'bibliographic_record_id' => ['required', 'integer', 'exists:bibliographic_records,id'],
            'quantity' => ['required', 'integer', 'min:1', 'max:100'],
            'inventory_number' => ['required', 'string', 'max:64'],
            'barcode' => ['nullable', 'string', 'max:64'],
            'accounting_type' => ['nullable', 'string', 'max:32'],
            'ksu_number' => ['nullable', 'string', 'max:64'],
            'storage_sigla' => ['nullable', 'string', 'max:64'],
            'service_point_code' => ['nullable', 'string', 'max:64'],
            'shelf_index' => ['nullable', 'string', 'max:128'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'fund_id' => ['nullable', 'integer', 'exists:funds,id'],
            'room' => ['nullable', 'string', 'max:100'],
            'section' => ['nullable', 'string', 'max:100'],
            'shelf_location' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'acquisition_source' => ['nullable', Rule::in(BookCopy::ACQUISITION_SOURCES)],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'acquisition_date' => ['nullable', 'date'],
            'condition' => ['required', Rule::in(BookCopy::CONDITIONS)],
            'access_restriction' => ['required', Rule::in(BookCopy::ACCESS_RESTRICTIONS)],
        ]);
        $this->assertFundBelongsToBranch(
            $validated['branch_id'] ?? null,
            $validated['fund_id'] ?? null,
        );

        $quantity = (int) $validated['quantity'];

        $created = DB::transaction(function () use ($validated, $quantity, $request, $audit): array {
            $created = [];
            for ($index = 0; $index < $quantity; $index++) {
                $inventoryNumber = $quantity === 1
                    ? $validated['inventory_number']
                    : $this->sequencedNumber($validated['inventory_number'], $index);
                $barcode = $validated['barcode'] ?? null;
                if ($barcode !== null && $quantity > 1) {
                    $barcode = $this->sequencedNumber($barcode, $index);
                }

                if (BookCopy::query()->where('inventory_number', $inventoryNumber)->exists()) {
                    throw ValidationException::withMessages([
                        'inventory_number' => __('librarian.copies.inventory_taken', ['number' => $inventoryNumber]),
                    ]);
                }

                $copy = BookCopy::query()->create([
                    ...collect($validated)->except(['quantity', 'inventory_number', 'barcode'])->all(),
                    'inventory_number' => $inventoryNumber,
                    'barcode' => $barcode,
                    'registration_date' => now()->toDateString(),
                    'status' => 'available',
                ]);
                $copy->recordHistory('created', null, $request->user()->getKey(), null, [
                    'bibliographic_record_id' => $copy->bibliographic_record_id,
                    'inventory_number' => $copy->inventory_number,
                    'barcode' => $copy->barcode,
                    'acquisition_source' => $copy->acquisition_source,
                    'supplier_name' => $copy->supplier_name,
                    'price' => $copy->price,
                    'branch_id' => $copy->branch_id,
                    'fund_id' => $copy->fund_id,
                    'room' => $copy->room,
                    'section' => $copy->section,
                    'shelf_location' => $copy->shelf_location,
                ]);
                $created[] = $copy;
            }

            $audit->logRequired(
                actionType: 'copies.create',
                entityType: 'book_copy',
                entityId: $created[0]->getKey(),
                newValues: [
                    'record_id' => $validated['bibliographic_record_id'],
                    'quantity' => $quantity,
                    'inventory_numbers' => array_map(fn (BookCopy $copy): string => $copy->inventory_number, $created),
                ],
                scope: 'library',
                actor: $request->user(),
            );

            return $created;
        });
        foreach ($created as $copy) {
            $scanner->scanModel($copy->fresh(), 'book_copy');
        }
        // A physical record is intentionally allowed to exist before intake,
        // but adding its first copy must immediately close the scoped
        // `bib.physical.no_copies` finding instead of waiting for a full scan.
        $scanner->scanModel(
            BibliographicRecord::query()->findOrFail($validated['bibliographic_record_id']),
            'bibliographic_record',
        );

        return redirect()
            ->route('librarian.catalog.edit', $validated['bibliographic_record_id'])
            ->with('success', __('librarian.copies.created_count', ['count' => count($created)]));
    }

    public function edit(BookCopy $copy): View
    {
        return view('librarian.copies.form', [
            'copy' => $copy->load('bibliographicRecord'),
            'record' => $copy->bibliographicRecord,
            'records' => collect(),
            'branches' => Branch::query()->orderBy('name')->get(),
            'funds' => Fund::query()->orderBy('name')->get(),
        ]);
    }

    public function update(Request $request, BookCopy $copy, AuditLogger $audit, DataQualityScanner $scanner): RedirectResponse
    {
        $validated = $request->validate([
            'inventory_number' => ['required', 'string', 'max:64', Rule::unique('book_copies', 'inventory_number')->ignore($copy)],
            'accounting_type' => ['nullable', 'string', 'max:32'],
            'ksu_number' => ['nullable', 'string', 'max:64'],
            'storage_sigla' => ['nullable', 'string', 'max:64'],
            'service_point_code' => ['nullable', 'string', 'max:64'],
            'shelf_index' => ['nullable', 'string', 'max:128'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'fund_id' => ['nullable', 'integer', 'exists:funds,id'],
            'room' => ['nullable', 'string', 'max:100'],
            'section' => ['nullable', 'string', 'max:100'],
            'shelf_location' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            // Recovered INV.SOURCE values are immutable evidence and include
            // historical free text. New intake uses canonical choices, but an
            // ordinary edit must be able to preserve the existing source.
            'acquisition_source' => ['nullable', 'string', 'max:255'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'acquisition_date' => ['nullable', 'date'],
            'condition' => ['required', Rule::in(BookCopy::CONDITIONS)],
            'defect_description' => ['nullable', 'string', 'max:2000'],
            'access_restriction' => ['required', Rule::in(BookCopy::ACCESS_RESTRICTIONS)],
            'status' => ['required', Rule::in(BookCopy::STATUSES)],
        ]);
        $this->assertFundBelongsToBranch(
            array_key_exists('branch_id', $validated) ? $validated['branch_id'] : $copy->branch_id,
            array_key_exists('fund_id', $validated) ? $validated['fund_id'] : $copy->fund_id,
        );

        if ($request->exists('barcode') && trim((string) $request->input('barcode')) !== trim((string) $copy->barcode)) {
            throw ValidationException::withMessages([
                'barcode' => __('librarian.copies.marking.controlled_change_only'),
            ]);
        }
        if ($validated['status'] !== $copy->status) {
            throw ValidationException::withMessages([
                'status' => __('librarian.copies.status_controlled_change_only'),
            ]);
        }
        unset($validated['status']);

        DB::transaction(function () use ($copy, $validated, $request, $audit): void {
            $old = $copy->only(array_keys($validated));
            $copy->update($validated);
            $new = $copy->only(array_keys($validated));
            $locationFields = [
                'storage_sigla', 'service_point_code', 'shelf_index',
                'branch_id', 'fund_id', 'room', 'section', 'shelf_location',
            ];
            $oldLocation = collect($old)->only($locationFields)->all();
            $newLocation = collect($new)->only($locationFields)->all();
            $copy->recordHistory(
                $oldLocation !== $newLocation ? 'location_changed' : 'updated',
                null,
                $request->user()->getKey(),
                null,
                ['old' => $old, 'new' => $new],
            );

            $audit->logRequired(
                actionType: 'copies.update',
                entityType: 'book_copy',
                entityId: $copy->getKey(),
                oldValues: $old,
                newValues: $new,
                scope: 'library',
                actor: $request->user(),
            );
        });
        $scanner->scanModel($copy->fresh(), 'book_copy');

        return redirect()->route('librarian.copies.show', $copy)->with('success', __('common.updated_successfully'));
    }

    /**
     * Status action: write_off / lost / under_repair / restore. Requires a
     * comment; loss and damage outside a loan also create a fine only when a
     * responsible reader exists, otherwise just the incident trace.
     */
    public function changeStatus(
        Request $request,
        BookCopy $copy,
        AuditLogger $audit,
        DataQualityScanner $scanner,
        ReservationQueueService $reservations,
        KsuOperationsService $ksu,
    ): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['write_off', 'lost', 'under_repair', 'restore'])],
            'comment' => ['required', 'string', 'min:5', 'max:2000'],
            'writeoff_date' => ['nullable', 'required_if:action,write_off', 'date'],
            'writeoff_act' => ['nullable', 'required_if:action,write_off', 'string', 'max:128'],
            'writeoff_reason' => ['nullable', 'required_if:action,write_off', 'string', 'min:5', 'max:2000'],
        ]);
        if ($validated['action'] === 'write_off') {
            abort_unless($request->user()->can('copies.write_off'), 403);
        }

        if ($copy->activeLoan()->exists()) {
            return back()->withErrors(['action' => __('librarian.copies.status_blocked_by_loan')]);
        }

        DB::transaction(function () use ($copy, $validated, $request, $audit, $reservations, $ksu): void {
            $copy = BookCopy::query()->whereKey($copy->getKey())->lockForUpdate()->firstOrFail();
            if ($copy->activeLoan()->exists()) {
                throw ValidationException::withMessages([
                    'action' => __('librarian.copies.status_blocked_by_loan'),
                ]);
            }
            if ($validated['action'] === 'restore' && $copy->status === 'written_off') {
                throw ValidationException::withMessages([
                    'action' => __('copy_lifecycle.validation.written_off_immutable'),
                ]);
            }
            $requestedStatus = match ($validated['action']) {
                'write_off' => 'written_off', 'lost' => 'lost',
                'under_repair' => 'under_repair', 'restore' => 'available',
            };
            if ($copy->status === $requestedStatus) {
                throw ValidationException::withMessages([
                    'action' => __('copy_lifecycle.validation.no_state_change'),
                ]);
            }

            $stateFields = [
                'status', 'inventory_status', 'circulation_status', 'condition', 'defect_description',
                'writeoff_date', 'writeoff_act', 'writeoff_reason',
            ];
            $oldState = $copy->only($stateFields);
            $oldStatus = $oldState['status'];
            $newStatus = $requestedStatus;

            $updates = [
                'status' => $newStatus,
                'defect_description' => $validated['action'] === 'restore'
                    ? $copy->defect_description
                    : trim(($copy->defect_description ? $copy->defect_description."\n" : '').$validated['comment']),
                'condition' => $validated['action'] === 'restore' ? 'good' : $copy->condition,
            ];
            if ($validated['action'] === 'write_off') {
                $updates += [
                    'writeoff_date' => $validated['writeoff_date'],
                    'writeoff_act' => trim($validated['writeoff_act']),
                    'writeoff_reason' => trim($validated['writeoff_reason']),
                ];
            }

            // Make the copy ineligible before cancelling assigned holds. The
            // reservation service therefore cannot immediately offer this
            // same physical copy to the next reader while the write-off/loss
            // transaction is still in progress.
            $copy->update($updates);
            if (in_array($validated['action'], ['write_off', 'lost', 'under_repair'], true)) {
                $assignedReservations = $copy->reservations()->active()->with('reader')->lockForUpdate()->get();
                foreach ($assignedReservations as $reservation) {
                    $reservations->cancel(
                        $reservation,
                        $request->user(),
                        __('copy_lifecycle.reservation_cancel_reason', ['copy' => $copy->inventory_number]),
                        true,
                    );
                }
            }
            $withdrawalEntry = null;
            if ($validated['action'] === 'write_off') {
                $withdrawalEntry = $ksu->recordWithdrawal(
                    [$copy],
                    $validated['writeoff_date'],
                    $validated['writeoff_act'],
                    $validated['writeoff_reason'],
                    $request->user(),
                );
            }

            $historyEvent = match ($validated['action']) {
                'under_repair' => 'repair',
                'restore' => $oldStatus === 'under_repair' ? 'repair_returned' : 'status_change',
                'write_off' => 'write_off',
                'lost' => 'lost',
            };
            $newState = $copy->fresh()->only($stateFields);
            $copy->recordHistory(
                $historyEvent,
                null,
                $request->user()->getKey(),
                null,
                [
                    'from' => $oldStatus,
                    'to' => $newStatus,
                    'comment' => $validated['comment'],
                    'writeoff_act' => $validated['writeoff_act'] ?? null,
                    'writeoff_date' => $validated['writeoff_date'] ?? null,
                    'ksu_withdrawal_entry_id' => $withdrawalEntry?->getKey(),
                    'old' => $oldState,
                    'new' => $newState,
                ],
            );

            $audit->logRequired(
                actionType: 'copies.status_change',
                entityType: 'book_copy',
                entityId: $copy->getKey(),
                oldValues: $oldState,
                newValues: $newState,
                reason: $validated['comment'],
                scope: 'library',
                actor: $request->user(),
            );
        });
        $scanner->scanModel($copy->fresh(), 'book_copy');

        return back()->with('success', __('common.updated_successfully'));
    }

    /**
     * Printable QR/barcode label for the copy (Master.md 14.6).
     */
    public function label(BookCopy $copy, MachineCodeService $codes): View
    {
        abort_if(blank($copy->barcode), 422, __('librarian.copies.marking.assign_before_print'));

        return view('librarian.copies.label', [
            'copy' => $copy->load('bibliographicRecord'),
            'code128Svg' => $codes->code128($copy->barcode, 1.5, 46),
        ]);
    }

    public function labels(Request $request, MachineCodeService $codes): View
    {
        $validated = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:100'], 'ids.*' => ['integer', 'distinct', 'exists:book_copies,id']]);
        $copies = BookCopy::query()->whereIn('id', $validated['ids'])->whereNotNull('barcode')->where('barcode', '!=', '')
            ->with('bibliographicRecord')->orderBy('branch_id')->orderBy('fund_id')
            ->orderBy('shelf_location')->orderBy('inventory_number')->get();
        abort_if($copies->isEmpty(), 422, __('librarian.copies.marking.assign_before_print'));

        return view('librarian.copies.labels', ['labels' => $copies->map(fn (BookCopy $copy) => [
            'copy' => $copy, 'code128Svg' => $codes->code128($copy->barcode, 1.5, 46),
        ]), 'copyIds' => $copies->pluck('id')->all()]);
    }

    public function assignBarcode(Request $request, BookCopy $copy, BarcodeMarkingService $marking): RedirectResponse
    {
        $validated = $request->validate([
            'mode' => ['required', Rule::in(['generate', 'existing'])],
            'barcode' => ['nullable', 'required_if:mode,existing', 'string', 'max:64'],
            'inventory_number_confirmation' => ['required', 'string', Rule::in([$copy->inventory_number])],
            'confirmed' => ['accepted'],
        ]);
        $assigned = $marking->assign($copy, $request->user(), $validated['mode'] === 'existing' ? $validated['barcode'] : null);

        return redirect()->route('librarian.copies.show', $assigned)->with('success', __('librarian.copies.marking.assigned'));
    }

    public function confirmBarcode(Request $request, BookCopy $copy, BarcodeMarkingService $marking): RedirectResponse
    {
        $validated = $request->validate(['scanned_barcode' => ['required', 'string', 'max:64']]);
        $marking->confirm($copy, $request->user(), $validated['scanned_barcode']);

        return back()->with('success', __('librarian.copies.marking.confirmed'));
    }

    public function markLabelPrinted(Request $request, BookCopy $copy, BarcodeMarkingService $marking): RedirectResponse
    {
        $marking->markPrinted(collect([$copy]), $request->user());

        return back()->with('success', __('librarian.copies.marking.print_recorded'));
    }

    public function batchPreview(Request $request): View
    {
        $validated = $request->validate([
            'copy_ids' => ['required', 'array', 'min:1', 'max:'.BarcodeMarkingService::BATCH_LIMIT],
            'copy_ids.*' => ['integer', 'distinct', 'exists:book_copies,id'],
        ]);
        $copies = BookCopy::query()->whereIn('id', $validated['copy_ids'])
            ->with(['bibliographicRecord', 'branch', 'fund'])->orderBy('branch_id')->orderBy('fund_id')
            ->orderBy('shelf_location')->orderBy('inventory_number')->get();

        return view('librarian.copies.barcode-batch-preview', [
            'copies' => $copies,
            'ready' => $copies->filter(fn (BookCopy $copy) => blank($copy->barcode) && filled($copy->inventory_number) && ! in_array($copy->status, ['lost', 'written_off'], true)),
        ]);
    }

    public function batchPrepare(Request $request, BarcodeMarkingService $marking): RedirectResponse
    {
        $validated = $request->validate([
            'copy_ids' => ['required', 'array', 'min:1', 'max:'.BarcodeMarkingService::BATCH_LIMIT],
            'copy_ids.*' => ['integer', 'distinct', 'exists:book_copies,id'],
            'confirmed' => ['accepted'],
        ]);
        $copies = BookCopy::query()->whereIn('id', $validated['copy_ids'])->get();
        $result = $marking->prepareBatch($copies, $request->user());
        if ($result['ready_ids'] === []) {
            return redirect()->route('librarian.copies.index', ['barcode_status' => 'without'])
                ->withErrors(['copy_ids' => __('librarian.copies.marking.batch_none_ready')]);
        }

        return redirect()->route('librarian.copies.labels', ['ids' => $result['ready_ids']])
            ->with('success', __('librarian.copies.marking.batch_prepared', [
                'ready' => count($result['ready_ids']), 'skipped' => count($result['skipped']),
            ]));
    }

    public function batchMarkPrinted(Request $request, BarcodeMarkingService $marking): RedirectResponse
    {
        $validated = $request->validate([
            'ids' => ['required', 'array', 'min:1', 'max:'.BarcodeMarkingService::BATCH_LIMIT],
            'ids.*' => ['integer', 'distinct', 'exists:book_copies,id'],
        ]);
        $copies = BookCopy::query()->whereIn('id', $validated['ids'])->get();
        $marking->markPrinted($copies, $request->user());

        return redirect()->route('librarian.copies.index', ['barcode_status' => 'without'])
            ->with('success', __('librarian.copies.marking.print_recorded'));
    }

    private function sequencedNumber(string $base, int $index): string
    {
        if ($index === 0) {
            return $base;
        }

        if (preg_match('/^(.*?)(\d+)$/', $base, $matches) === 1) {
            $number = (int) $matches[2] + $index;

            return $matches[1].str_pad((string) $number, strlen($matches[2]), '0', STR_PAD_LEFT);
        }

        return $base.'-'.($index + 1);
    }

    private function assertFundBelongsToBranch(mixed $branchId, mixed $fundId): void
    {
        if ($fundId === null || $fundId === '') {
            return;
        }

        if ($branchId === null || $branchId === '' || ! Fund::query()
            ->whereKey((int) $fundId)
            ->where('branch_id', (int) $branchId)
            ->exists()) {
            throw ValidationException::withMessages([
                'fund_id' => __('librarian.copies.fund_branch_mismatch'),
            ]);
        }
    }
}
