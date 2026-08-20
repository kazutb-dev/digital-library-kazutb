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
use App\Services\Catalog\MachineCodeService;
use App\Services\DataQuality\DataQualityScanner;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
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
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'fund_id' => ['nullable', 'integer', 'exists:funds,id'],
            'status' => ['nullable', Rule::in(BookCopy::STATUSES)],
            'condition' => ['nullable', Rule::in(BookCopy::CONDITIONS)],
            'barcode_status' => ['nullable', Rule::in(['with', 'without'])],
            'storage_sigla' => ['nullable', 'string', 'max:64'],
            'shelf_location' => ['nullable', 'string', 'max:100'],
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
        if (($filters['barcode_status'] ?? null) === 'with') {
            $query->whereNotNull('barcode')->where('barcode', '!=', '');
        } elseif (($filters['barcode_status'] ?? null) === 'without') {
            $query->where(fn (Builder $builder) => $builder->whereNull('barcode')->orWhere('barcode', ''));
        }
        foreach (['storage_sigla', 'shelf_location'] as $column) {
            if ($value = trim((string) ($filters[$column] ?? ''))) {
                $query->whereRaw('LOWER(COALESCE('.$column.', \'\')) LIKE ?', ['%'.mb_strtolower($value).'%']);
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
            'statusCounts' => BookCopy::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'markingStats' => [
                'total' => BookCopy::query()->count(),
                'with' => BookCopy::query()->whereNotNull('barcode')->where('barcode', '!=', '')->count(),
                'without' => BookCopy::query()->where(fn (Builder $builder) => $builder->whereNull('barcode')->orWhere('barcode', ''))->count(),
            ],
            'qualityByCopy' => $qualityByCopy,
        ]);
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
            $locationFields = ['storage_sigla', 'branch_id', 'fund_id', 'room', 'section', 'shelf_location'];
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
    public function changeStatus(Request $request, BookCopy $copy, AuditLogger $audit, DataQualityScanner $scanner): RedirectResponse
    {
        $validated = $request->validate([
            'action' => ['required', Rule::in(['write_off', 'lost', 'under_repair', 'restore'])],
            'comment' => ['required', 'string', 'min:5', 'max:2000'],
            'fine_amount' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
        ]);

        if ($copy->activeLoan()->exists()) {
            return back()->withErrors(['action' => __('librarian.copies.status_blocked_by_loan')]);
        }

        DB::transaction(function () use ($copy, $validated, $request, $audit): void {
            $oldState = $copy->only(['status', 'condition', 'defect_description']);
            $oldStatus = $oldState['status'];
            $newStatus = match ($validated['action']) {
                'write_off' => 'written_off',
                'lost' => 'lost',
                'under_repair' => 'under_repair',
                'restore' => 'available',
            };

            $copy->update([
                'status' => $newStatus,
                'defect_description' => $validated['action'] === 'restore'
                    ? $copy->defect_description
                    : trim(($copy->defect_description ? $copy->defect_description."\n" : '').$validated['comment']),
                'condition' => $validated['action'] === 'restore' ? 'good' : $copy->condition,
            ]);

            $historyEvent = match ($validated['action']) {
                'under_repair' => 'repair',
                'restore' => $oldStatus === 'under_repair' ? 'repair_returned' : 'status_change',
                'write_off' => 'write_off',
                'lost' => 'lost',
            };
            $newState = $copy->fresh()->only(['status', 'condition', 'defect_description']);
            $copy->recordHistory(
                $historyEvent,
                null,
                $request->user()->getKey(),
                null,
                [
                    'from' => $oldStatus,
                    'to' => $newStatus,
                    'comment' => $validated['comment'],
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
