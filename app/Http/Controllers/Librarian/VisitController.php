<?php

namespace App\Http\Controllers\Librarian;

use App\Exceptions\CirculationException;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Catalog\LibraryVisit;
use App\Models\Setting;
use App\Services\Catalog\LibraryVisitService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Attendance desk (ДИР §9.4): scan a reader card to record that they came in.
 * Nothing here touches loans — a visit stands on its own.
 */
class VisitController extends Controller
{
    public function __construct(private readonly LibraryVisitService $visits) {}

    public function index(Request $request): View
    {
        $today = LibraryVisit::query()->between(now()->startOfDay(), now()->endOfDay());

        return view('librarian.visits.index', [
            'recent' => LibraryVisit::query()
                ->with(['reader.readerProfile', 'branch', 'scannedBy'])
                ->orderByDesc('scanned_at')
                ->paginate(Setting::resultsPerPage())
                ->withQueryString(),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'todayVisits' => (clone $today)->count(),
            'todayReaders' => (clone $today)->distinct()->count('user_id'),
            'weekVisits' => LibraryVisit::query()->between(now()->subDays(6)->startOfDay(), now()->endOfDay())->count(),
            'dedupeMinutes' => LibraryVisitService::DEDUPE_MINUTES,
        ]);
    }

    /**
     * Card lookup for the scan field — confirms who the code belongs to before
     * the visit is written.
     */
    public function lookup(Request $request): JsonResponse
    {
        $reader = $this->visits->findReaderByCode((string) $request->query('code', ''));

        if ($reader === null) {
            return response()->json(['data' => null]);
        }

        return response()->json(['data' => [
            'id' => $reader->getKey(),
            'name' => $reader->name,
            'ticket' => $reader->readerProfile?->ticket_number,
            'barcode' => $reader->readerProfile?->barcode,
            'status' => $reader->readerProfile?->status,
            'status_label' => $reader->readerProfile
                ? __('librarian.circulation.reader_statuses.'.$reader->readerProfile->status)
                : null,
        ]]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:64'],
            'branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'source' => ['nullable', Rule::in(LibraryVisit::SOURCES)],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        $reader = $this->visits->findReaderByCode($validated['code']);
        if ($reader === null) {
            return back()->withErrors(['code' => __('librarian.visits.reader_not_found')])->withInput();
        }

        try {
            $result = $this->visits->record(
                $reader,
                $validated['branch_id'] ?? null,
                $request->user(),
                $validated['source'] ?? 'desk',
                $validated['notes'] ?? null,
            );
        } catch (CirculationException $exception) {
            return back()->withErrors(['code' => $exception->getMessage()])->withInput();
        }

        if ($result['duplicate']) {
            return back()->with('success', __('librarian.visits.duplicate_success', [
                'name' => $reader->name,
                'minutes' => LibraryVisitService::DEDUPE_MINUTES,
            ]));
        }

        return back()->with('success', __('librarian.visits.recorded_success', [
            'name' => $reader->name,
            'time' => $result['visit']->scanned_at?->format('H:i') ?? '',
        ]));
    }
}
