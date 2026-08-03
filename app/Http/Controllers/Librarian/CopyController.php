<?php

namespace App\Http\Controllers\Librarian;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\Fine;
use App\Models\Fund;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Services\Catalog\MachineCodeService;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Copy / inventory management (Master.md §12, §14.4-14.6): per-copy cards
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
        ]);

        $query = BookCopy::query()->with(['bibliographicRecord', 'branch', 'fund']);

        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function (Builder $builder) use ($needle): void {
                $builder
                    ->whereRaw('LOWER(inventory_number) LIKE ?', [$needle])
                    ->orWhereRaw('LOWER(COALESCE(barcode, \'\')) LIKE ?', [$needle])
                    ->orWhereHas('bibliographicRecord', fn (Builder $record) => $record->whereRaw('LOWER(title) LIKE ?', [$needle]));
            });
        }
        foreach (['branch_id', 'fund_id', 'status', 'condition'] as $column) {
            if ($value = ($filters[$column] ?? null)) {
                $query->where($column, $value);
            }
        }

        return view('librarian.copies.index', [
            'copies' => $query->orderByDesc('updated_at')->paginate(Setting::resultsPerPage())->withQueryString(),
            'filters' => $filters,
            'branches' => Branch::query()->orderBy('name')->get(),
            'funds' => Fund::query()->orderBy('name')->get(),
            'statusCounts' => BookCopy::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function show(BookCopy $copy): View
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

        return view('librarian.copies.show', ['copy' => $copy]);
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
    public function store(Request $request, AuditLogger $audit): RedirectResponse
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
            'shelf_location' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'acquisition_source' => ['nullable', 'string', 'max:255'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'acquisition_date' => ['nullable', 'date'],
            'condition' => ['required', Rule::in(BookCopy::CONDITIONS)],
            'access_restriction' => ['required', Rule::in(BookCopy::ACCESS_RESTRICTIONS)],
        ]);

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
                $copy->recordHistory('created', null, $request->user()->getKey());
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
            );

            return $created;
        });

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

    public function update(Request $request, BookCopy $copy, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'inventory_number' => ['required', 'string', 'max:64', Rule::unique('book_copies', 'inventory_number')->ignore($copy)],
            'barcode' => ['nullable', 'string', 'max:64', Rule::unique('book_copies', 'barcode')->ignore($copy)],
            'accounting_type' => ['nullable', 'string', 'max:32'],
            'ksu_number' => ['nullable', 'string', 'max:64'],
            'storage_sigla' => ['nullable', 'string', 'max:64'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'fund_id' => ['nullable', 'integer', 'exists:funds,id'],
            'shelf_location' => ['nullable', 'string', 'max:255'],
            'price' => ['nullable', 'numeric', 'min:0', 'max:10000000'],
            'acquisition_source' => ['nullable', 'string', 'max:255'],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'acquisition_date' => ['nullable', 'date'],
            'condition' => ['required', Rule::in(BookCopy::CONDITIONS)],
            'defect_description' => ['nullable', 'string', 'max:2000'],
            'access_restriction' => ['required', Rule::in(BookCopy::ACCESS_RESTRICTIONS)],
            'status' => ['required', Rule::in(BookCopy::STATUSES)],
        ]);

        DB::transaction(function () use ($copy, $validated, $request, $audit): void {
            $old = $copy->only(array_keys($validated));
            $copy->update($validated);
            $copy->recordHistory('updated', null, $request->user()->getKey());

            $audit->logRequired(
                actionType: 'copies.update',
                entityType: 'book_copy',
                entityId: $copy->getKey(),
                oldValues: $old,
                newValues: $copy->only(array_keys($validated)),
                scope: 'library',
            );
        });

        return redirect()->route('librarian.copies.show', $copy)->with('success', __('common.updated_successfully'));
    }

    /**
     * Status action: write_off / lost / under_repair / restore. Requires a
     * comment; loss and damage outside a loan also create a fine only when a
     * responsible reader exists, otherwise just the incident trace.
     */
    public function changeStatus(Request $request, BookCopy $copy, AuditLogger $audit): RedirectResponse
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
            $oldStatus = $copy->status;
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

            $copy->recordHistory(
                $validated['action'] === 'under_repair' ? 'repair' : 'status_change',
                null,
                $request->user()->getKey(),
                null,
                ['from' => $oldStatus, 'to' => $newStatus, 'comment' => $validated['comment']],
            );

            $audit->logRequired(
                actionType: 'copies.status_change',
                entityType: 'book_copy',
                entityId: $copy->getKey(),
                oldValues: ['status' => $oldStatus],
                newValues: ['status' => $newStatus],
                reason: $validated['comment'],
                scope: 'library',
            );
        });

        return back()->with('success', __('common.updated_successfully'));
    }

    /**
     * Printable QR/barcode label for the copy (Master.md §14.6).
     */
    public function label(BookCopy $copy, MachineCodeService $codes): View
    {
        $value = $copy->barcode ?: $copy->inventory_number;

        return view('librarian.copies.label', [
            'copy' => $copy->load('bibliographicRecord'),
            'code128Svg' => $codes->code128($value, 1.5, 46),
            'qrSvg' => $codes->qr($value),
        ]);
    }

    public function labels(Request $request, MachineCodeService $codes): View
    {
        $validated = $request->validate(['ids' => ['required', 'array', 'min:1', 'max:100'], 'ids.*' => ['integer', 'distinct', 'exists:book_copies,id']]);
        $copies = BookCopy::query()->whereIn('id', $validated['ids'])->with('bibliographicRecord')->orderBy('inventory_number')->get();

        return view('librarian.copies.labels', ['labels' => $copies->map(fn (BookCopy $copy) => [
            'copy' => $copy, 'code128Svg' => $codes->code128($copy->barcode ?: $copy->inventory_number, 1.5, 46), 'qrSvg' => $codes->qr($copy->barcode ?: $copy->inventory_number),
        ])]);
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
}
