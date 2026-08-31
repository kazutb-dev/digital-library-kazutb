<?php

namespace App\Http\Controllers\Librarian;

use App\Http\Controllers\Controller;
use App\Models\AcquisitionOrder;
use App\Models\Branch;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Fund;
use App\Models\Operations\AcquisitionBatch;
use App\Models\Operations\AcquisitionBatchItem;
use App\Models\Setting;
use App\Services\Operations\AcquisitionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AcquisitionBatchController extends Controller
{
    public function index(Request $request): View
    {
        $this->authorizeView($request);
        $validated = $request->validate([
            'status' => ['nullable', Rule::in(AcquisitionBatch::STATUSES)],
            'q' => ['nullable', 'string', 'max:100'],
            'record_q' => ['nullable', 'string', 'max:100'],
        ]);
        $query = trim((string) ($validated['q'] ?? ''));
        $recordQuery = trim((string) ($validated['record_q'] ?? ''));

        $batches = AcquisitionBatch::query()
            ->with(['creator:id,name', 'confirmer:id,name', 'branch:id,name', 'fund:id,name', 'ksuEntry:id,entry_number'])
            ->when($validated['status'] ?? null, fn ($builder, $status) => $builder->where('status', $status))
            ->when($query !== '', function ($builder) use ($query): void {
                $builder->where(function ($scope) use ($query): void {
                    $scope->where('batch_number', 'like', '%'.$query.'%')
                        ->orWhere('supplier_name', 'like', '%'.$query.'%');
                });
            })
            ->orderByDesc('received_at')
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $records = BibliographicRecord::query()
            ->when($recordQuery !== '', fn ($builder) => $builder->search($recordQuery))
            ->orderByDesc('id')
            ->limit(50)
            ->get(['id', 'title', 'primary_author', 'publication_year']);

        return view('librarian.acquisitions.index', [
            'batches' => $batches,
            'records' => $records,
            'branches' => Branch::query()->active()->ordered()->get(['id', 'name']),
            'funds' => Fund::query()->active()->ordered()->get(['id', 'branch_id', 'name']),
            'orders' => AcquisitionOrder::query()->whereNotIn('status', ['received', 'cancelled'])->latest('id')->limit(100)->get(['id', 'order_number']),
            'canManage' => $request->user()->canAny(['acquisitions.intake', 'acquisitions.manage']),
            'numberingDefaults' => $this->numberingDefaults(),
        ]);
    }

    public function store(Request $request, AcquisitionService $service): RedirectResponse
    {
        $this->authorizeManage($request);
        $batch = $service->createDraft($request->user(), $request->validate($this->rules(true)));

        return redirect()->route('librarian.acquisitions.show', $batch)
            ->with('success', __('operations.messages.draft_created'));
    }

    public function show(Request $request, AcquisitionBatch $batch): View
    {
        $this->authorizeView($request);

        return view('librarian.acquisitions.show', [
            'batch' => $batch->load([
                'items.bibliographicRecord:id,title,primary_author,publication_year',
                'items.copies:id,acquisition_batch_item_id,inventory_number,barcode,status,inventory_status,circulation_status',
                'creator:id,name',
                'confirmer:id,name',
                'branch:id,name',
                'fund:id,name',
                'order:id,order_number',
                'ksuEntry:id,entry_number,number,year,status',
            ]),
            'branches' => Branch::query()->active()->ordered()->get(['id', 'name']),
            'funds' => Fund::query()->active()->ordered()->get(['id', 'branch_id', 'name']),
            'canManage' => $request->user()->canAny(['acquisitions.intake', 'acquisitions.manage']),
            'canConfirm' => $request->user()->canAny(['acquisitions.confirm', 'acquisitions.manage']),
            'numberingDefaults' => $this->numberingDefaults(),
        ]);
    }

    public function update(
        Request $request,
        AcquisitionBatch $batch,
        AcquisitionService $service,
    ): RedirectResponse {
        $this->authorizeManage($request);
        $service->updateDraft($request->user(), $batch, $request->validate($this->rules(false)));

        return back()->with('success', __('operations.messages.draft_updated'));
    }

    public function confirm(
        Request $request,
        AcquisitionBatch $batch,
        AcquisitionService $service,
    ): RedirectResponse {
        abort_unless($request->user()->canAny(['acquisitions.confirm', 'acquisitions.manage']), 403);
        $service->confirm($request->user(), $batch);

        return back()->with('success', __('operations.messages.batch_confirmed'));
    }

    public function cancel(
        Request $request,
        AcquisitionBatch $batch,
        AcquisitionService $service,
    ): RedirectResponse {
        $this->authorizeManage($request);
        $service->cancelDraft($request->user(), $batch);

        return back()->with('success', __('operations.messages.batch_cancelled'));
    }

    /** @return array<string,mixed> */
    private function rules(bool $creating): array
    {
        $rules = [
            'received_at' => ['required', 'date'],
            'acquisition_source' => ['required', Rule::in(BookCopy::ACQUISITION_SOURCES)],
            'supplier_name' => ['nullable', 'string', 'max:255'],
            'currency' => ['required', Rule::in(['KZT', 'USD', 'EUR'])],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'fund_id' => ['nullable', 'integer', 'exists:funds,id'],
            'acquisition_order_id' => ['nullable', 'integer', 'exists:acquisition_orders,id'],
            'notes' => ['nullable', 'string', 'max:4000'],
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.bibliographic_record_id' => ['required', 'integer', 'distinct', 'exists:bibliographic_records,id'],
            'items.*.quantity' => ['required', 'integer', 'min:1', 'max:10000'],
            'items.*.unit_price' => ['required', 'numeric', 'min:0', 'max:999999999.99', 'decimal:0,2'],
            'items.*.accounting_type' => ['required', Rule::in(['inventory', 'individual', 'non_inventory'])],
            'items.*.condition' => ['required', Rule::in(BookCopy::CONDITIONS)],
            'items.*.access_restriction' => ['required', Rule::in(BookCopy::ACCESS_RESTRICTIONS)],
            'items.*.storage_sigla' => ['nullable', 'string', 'max:64'],
            'items.*.service_point_code' => ['nullable', 'string', 'max:64'],
            'items.*.room' => ['nullable', 'string', 'max:128'],
            'items.*.section' => ['nullable', 'string', 'max:128'],
            'items.*.shelf_location' => ['nullable', 'string', 'max:255'],
            'items.*.shelf_index' => ['nullable', 'string', 'max:255'],
            'items.*.inventory_number_mode' => ['nullable', Rule::in([...AcquisitionBatchItem::INVENTORY_NUMBER_MODES, 'manual-list'])],
            'items.*.manual_inventory_numbers' => ['nullable'],
            'items.*.inventory_range_start' => ['nullable', 'string', 'max:64'],
            'items.*.inventory_range_end' => ['nullable', 'string', 'max:64'],
            'items.*.barcode_mode' => ['nullable', Rule::in([...AcquisitionBatchItem::BARCODE_MODES, 'manual-list'])],
            'items.*.manual_barcodes' => ['nullable'],
            'items.*.inventory_prefix' => ['nullable', 'string', 'max:24', 'regex:/^[A-Za-z0-9_-]+$/'],
            'items.*.barcode_prefix' => ['nullable', 'string', 'max:24', 'regex:/^[A-Za-z0-9_-]+$/'],
            'items.*.notes' => ['nullable', 'string', 'max:2000'],
        ];
        if ($creating) {
            $rules['batch_number'] = ['nullable', 'string', 'max:64', Rule::unique('acquisition_batches', 'batch_number')];
        }

        return $rules;
    }

    /** @return array<string,mixed> */
    private function numberingDefaults(): array
    {
        $inventoryAutoEnabled = (bool) Setting::valueFor('inventory_numbering_enabled', true);
        $barcodeAutoEnabled = (bool) Setting::valueFor('barcode_generation_enabled', true);

        return [
            'inventory_auto_enabled' => $inventoryAutoEnabled,
            'barcode_auto_enabled' => $barcodeAutoEnabled,
            'inventory_number_mode' => $inventoryAutoEnabled ? 'auto' : 'manual_list',
            'barcode_mode' => $barcodeAutoEnabled ? 'auto' : 'none',
            'inventory_prefix' => trim((string) Setting::valueFor('inventory_number_prefix', 'INV')) ?: 'INV',
            'barcode_prefix' => trim((string) Setting::valueFor('barcode_prefix', 'KAZUTB')) ?: 'KAZUTB',
            'service_point_code' => trim((string) Setting::valueFor('default_service_point', '')),
            'storage_sigla' => trim((string) Setting::valueFor('default_sigla', '')),
        ];
    }

    private function authorizeView(Request $request): void
    {
        abort_unless($request->user()->canAny(['acquisitions.view', 'acquisitions.manage']), 403);
    }

    private function authorizeManage(Request $request): void
    {
        abort_unless($request->user()->canAny(['acquisitions.intake', 'acquisitions.manage']), 403);
    }
}
