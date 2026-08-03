<?php

namespace App\Http\Controllers\Librarian;

use App\Exceptions\CirculationException;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Catalog\InventorySession;
use App\Models\Fund;
use App\Services\Catalog\InventoryService;
use App\Support\Csv;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class InventoryController extends Controller
{
    public function __construct(private readonly InventoryService $inventory) {}

    public function index(): View
    {
        return view('librarian.inventory.index', [
            'sessions' => InventorySession::query()->with(['branch', 'fund', 'responsible'])->latest()->paginate(20),
            'branches' => Branch::query()->active()->ordered()->get(),
            'funds' => Fund::query()->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'branch_id' => ['required', 'integer', 'exists:branches,id'],
            'fund_id' => ['nullable', 'integer', 'exists:funds,id'],
            'room' => ['nullable', 'string', 'max:100'], 'shelf_range' => ['nullable', 'string', 'max:100'],
            'inventory_date' => ['required', 'date'],
        ]);
        $session = $this->inventory->create($data, $request->user());

        return redirect()->route('librarian.inventory.show', $session)->with('success', __('librarian.inventory.created'));
    }

    public function show(InventorySession $inventory): View
    {
        return view('librarian.inventory.show', [
            'inventory' => $inventory->load(['branch', 'fund', 'items.copy.bibliographicRecord', 'scans.copy']),
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
        $inventory->load(['items.copy.bibliographicRecord', 'scans']);

        return response()->streamDownload(function () use ($inventory): void {
            $stream = fopen('php://output', 'wb');
            Csv::writeRow($stream, ['session', 'inventory_number', 'barcode', 'title', 'expected_status', 'result']);
            foreach ($inventory->items as $item) {
                Csv::writeRow($stream, [$inventory->session_number, $item->copy?->inventory_number, $item->copy?->barcode, $item->copy?->bibliographicRecord?->title, $item->expected_status, $item->result]);
            }
            foreach ($inventory->scans->whereIn('classification', ['unknown', 'misplaced']) as $scan) {
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
