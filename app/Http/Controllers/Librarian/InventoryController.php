<?php

namespace App\Http\Controllers\Librarian;

use App\Exceptions\CirculationException;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\InventorySession;
use App\Models\Fund;
use App\Services\Catalog\InventoryLocationProfileService;
use App\Services\Catalog\InventoryService;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Illuminate\Support\Facades\Schema;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryController extends Controller
{
    public function __construct(
        private readonly InventoryService $inventory,
        private readonly InventoryLocationProfileService $locations,
    ) {}

    public function index(): View
    {
        $hasRecoveredSigla = Schema::hasColumn('book_copies', 'sigla_code');
        $siglas = BookCopy::query()
            ->selectRaw($hasRecoveredSigla
                ? "COALESCE(NULLIF(storage_sigla, ''), NULLIF(sigla_code, '')) AS value"
                : "NULLIF(storage_sigla, '') AS value")
            ->where(function ($query) use ($hasRecoveredSigla): void {
                $query->whereNotNull('storage_sigla');
                if ($hasRecoveredSigla) {
                    $query->orWhereNotNull('sigla_code');
                }
            })
            ->distinct()->orderBy('value')->limit(500)->pluck('value')->filter()->values();

        return view('librarian.inventory.index', [
            'sessions' => InventorySession::query()->with(['branch', 'fund', 'responsible'])->latest()->paginate(20),
            'branches' => Branch::query()->active()->ordered()->get(),
            'funds' => Fund::query()->orderBy('name')->get(),
            'siglas' => $siglas,
            'servicePoints' => BookCopy::query()->whereNotNull('service_point_code')
                ->where('service_point_code', '!=', '')->distinct()->orderBy('service_point_code')
                ->limit(500)->pluck('service_point_code'),
            'locationSummary' => $this->locations->summary(),
            'locationZones' => $this->locations->zones(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'scope_type' => ['required', Rule::in(['all', 'branch', 'fund', 'sigla', 'service_point'])],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id', 'required_if:scope_type,branch'],
            'fund_id' => ['nullable', 'integer', 'exists:funds,id'],
            'storage_sigla' => ['nullable', 'string', 'max:64', 'required_if:scope_type,sigla'],
            'service_point_code' => ['nullable', 'string', 'max:64', 'required_if:scope_type,service_point'],
            'room' => ['nullable', 'string', 'max:100'],
            'section' => ['nullable', 'string', 'max:100'],
            'shelf_range' => ['nullable', 'string', 'max:100'],
            'pilot_limit' => ['nullable', 'integer', 'in:10,20,50,100,500'],
            'inventory_date' => ['required', 'date'],
        ]);
        if ($data['scope_type'] === 'fund' && empty($data['fund_id'])) {
            throw ValidationException::withMessages([
                'fund_id' => __('librarian.inventory.scope_value_required'),
            ]);
        }
        if (($data['fund_id'] ?? null) !== null && ! Fund::query()
            ->whereKey((int) $data['fund_id'])
            ->when(($data['branch_id'] ?? null) !== null, fn ($query) => $query->where('branch_id', (int) $data['branch_id']))
            ->exists()) {
            throw ValidationException::withMessages([
                'fund_id' => __('librarian.inventory.fund_branch_mismatch'),
            ]);
        }
        $session = $this->inventory->create($data, $request->user());

        return redirect()->route('librarian.inventory.show', $session)->with('success', __('librarian.inventory.created'));
    }

    public function show(InventorySession $inventory): View
    {
        $inventory->load(['branch', 'fund']);
        $itemsQuery = $inventory->items()->with([
            'copy.bibliographicRecord:id,title', 'copy.branch:id,name', 'copy.fund:id,name',
            'copy.history' => fn ($query) => $query->whereIn('event_type', ['barcode_label_printed', 'barcode_confirmed']),
        ]);
        $items = (clone $itemsQuery)->orderBy('id')->paginate(100)->withQueryString();
        $handling = (clone $itemsQuery)->whereNotNull('handling_seconds')
            ->orderBy('handling_seconds')->pluck('handling_seconds');

        return view('librarian.inventory.show', [
            'inventory' => $inventory,
            'items' => $items,
            'analytics' => [
                'checked' => $inventory->items()->where('inventory_condition', '!=', 'unverified')->count(),
                'location_confirmed' => $inventory->items()->whereNotNull('location_confirmed_at')->count(),
                'location_corrected' => $inventory->items()->whereNotNull('location_corrected_at')->count(),
                'barcodes_assigned' => $inventory->items()->whereHas('copy', fn ($query) => $query->whereNotNull('barcode')->where('barcode', '!=', ''))->count(),
                'labels_printed' => $inventory->items()->whereHas('copy.history', fn ($query) => $query->where('event_type', 'barcode_label_printed'))->count(),
                'scan_confirmed' => $inventory->items()->whereHas('copy.history', fn ($query) => $query->where('event_type', 'barcode_confirmed'))->count(),
                'requires_review' => $inventory->items()->whereIn('result', ['requires_review', 'misplaced', 'status_conflict', 'written_off'])->count(),
                'median_handling_seconds' => $handling->isEmpty() ? null : $handling[(int) floor(($handling->count() - 1) / 2)],
            ],
        ]);
    }

    public function start(Request $request, InventorySession $inventory): RedirectResponse
    {
        return $this->mutate(fn () => $this->inventory->start($inventory, $request->user()));
    }

    public function scan(Request $request, InventorySession $inventory): RedirectResponse
    {
        $data = $request->validate(['code' => ['required', 'string', 'max:128']]);
        try {
            $scan = $this->inventory->scan($inventory, $data['code'], $request->user());
        } catch (CirculationException $e) {
            return back()->withErrors(['code' => $e->getMessage()])->withInput();
        }

        return back()->with('success', __('librarian.inventory.scan_result', ['result' => __('librarian.inventory.results.'.$scan->classification)]));
    }

    public function verify(Request $request, InventorySession $inventory): RedirectResponse
    {
        $data = $request->validate([
            'inventory_number' => ['required', 'string', 'max:64'],
            'inventory_condition' => ['required', 'in:visible,db_only,unreadable,mismatch'],
            'observed_inventory_number' => ['nullable', 'string', 'max:128'],
        ]);
        try {
            $scan = $this->inventory->verifyPhysical(
                $inventory, $data['inventory_number'], $data['inventory_condition'],
                $request->user(), $data['observed_inventory_number'] ?? null,
            );
        } catch (CirculationException $e) {
            return back()->withErrors(['inventory_number' => $e->getMessage()])->withInput();
        }

        return back()->with('success', __('librarian.inventory.scan_result', ['result' => __('librarian.inventory.results.'.$scan->classification)]));
    }

    public function confirmLocation(Request $request, InventorySession $inventory, BookCopy $copy): RedirectResponse
    {
        $data = $request->validate(['apply_correction' => ['nullable', 'boolean']]);
        try {
            $result = $this->inventory->confirmLocation($inventory, $copy, $request->user(), (bool) ($data['apply_correction'] ?? false));
        } catch (CirculationException $e) {
            return back()->withErrors(['location' => $e->getMessage()]);
        }

        return back()->with('success', __('librarian.inventory.location_result', $result));
    }

    public function complete(Request $request, InventorySession $inventory): RedirectResponse
    {
        return $this->mutate(fn () => $this->inventory->complete($inventory, $request->user()));
    }

    public function approve(Request $request, InventorySession $inventory): RedirectResponse
    {
        return $this->mutate(fn () => $this->inventory->approve($inventory, $request->user()));
    }

    public function export(InventorySession $inventory): StreamedResponse
    {
        return response()->streamDownload(function () use ($inventory): void {
            $stream = fopen('php://output', 'wb');
            Csv::writeRow($stream, ['session', 'inventory_number', 'barcode', 'title', 'expected_status', 'result']);
            $inventory->items()->with('copy.bibliographicRecord')->orderBy('id')->chunkById(500, function ($items) use ($stream, $inventory): void {
                foreach ($items as $item) {
                    Csv::writeRow($stream, [$inventory->session_number, $item->copy?->inventory_number, $item->copy?->barcode, $item->copy?->bibliographicRecord?->title, $item->expected_status, $item->result]);
                }
            });
            foreach ($inventory->scans()->whereIn('classification', ['unknown', 'misplaced', 'written_off'])->cursor() as $scan) {
                Csv::writeRow($stream, [$inventory->session_number, $scan->code, null, null, null, $scan->classification]);
            }
            fclose($stream);
        }, $inventory->session_number.'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    private function mutate(callable $callback): RedirectResponse
    {
        try {
            $callback();
        } catch (CirculationException $e) {
            return back()->withErrors(['inventory' => $e->getMessage()]);
        }

        return back()->with('success', __('librarian.inventory.updated'));
    }
}
