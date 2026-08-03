<?php

namespace App\Http\Controllers\Librarian;

use App\Exceptions\CirculationException;
use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\CopyTransfer;
use App\Models\Catalog\Reservation;
use App\Models\Setting;
use App\Services\Catalog\CopyTransferService;
use App\Services\Catalog\LoanPeriodPolicy;
use App\Services\Catalog\ReservationInsightService;
use App\Services\Catalog\ReservationQueueService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Reservation queue management (Master.md §13.5, ДИР §8): confirm, assign a
 * specific copy, prepare for pickup, extend the hold, hand it to the next
 * reader, cancel, and monitor the waiting line across branches.
 */
class ReservationController extends Controller
{
    public function __construct(
        private readonly ReservationQueueService $reservations,
        private readonly ReservationInsightService $insights,
        private readonly LoanPeriodPolicy $loanPeriods,
        private readonly CopyTransferService $transfers,
    ) {}

    public function index(Request $request): View
    {
        $filters = $request->validate([
            'status' => ['nullable', Rule::in(Reservation::STATUSES)],
            'search' => ['nullable', 'string', 'max:200'],
        ]);

        $query = Reservation::query()->with([
            'reader.readerProfile',
            'bibliographicRecord',
            'assignedCopy.branch',
            'pendingTransferBranch',
            'pickupBranch',
            'transfer.sourceBranch',
            'transfer.destinationBranch',
        ]);

        if ($status = ($filters['status'] ?? null)) {
            $query->where('status', $status);
        } else {
            $query->active();
        }
        if ($search = trim((string) ($filters['search'] ?? ''))) {
            $needle = '%'.mb_strtolower($search).'%';
            $query->where(function ($builder) use ($needle): void {
                $builder
                    ->whereHas('reader', fn ($reader) => $reader->whereRaw('LOWER(name) LIKE ?', [$needle]))
                    ->orWhereHas('bibliographicRecord', fn ($record) => $record->whereRaw('LOWER(title) LIKE ?', [$needle]));
            });
        }

        $reservations = $query
            ->orderByRaw("CASE status WHEN 'ready_for_pickup' THEN 0 WHEN 'in_transit' THEN 1 WHEN 'confirmed' THEN 2 WHEN 'queued' THEN 3 WHEN 'pending' THEN 4 ELSE 5 END")
            ->orderBy('created_at')
            ->paginate(Setting::resultsPerPage())
            ->withQueryString();
        $reservations->getCollection()->each(function (Reservation $reservation): void {
            if ($reservation->status === 'queued') {
                $reservation->setAttribute('queue_position', $this->insights->queuePosition($reservation));
            }
        });

        // §8: queue depth and the availability forecast are per-edition, so
        // they are resolved once for the page instead of per row.
        $recordIds = $reservations->pluck('bibliographic_record_id')->all();
        $copyCounts = $this->insights->circulatingCopyCounts($recordIds);

        return view('librarian.reservations.index', [
            'reservations' => $reservations,
            'filters' => $filters,
            'statusCounts' => Reservation::query()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'queueDepths' => $this->insights->queueDepths($recordIds),
            'copyCounts' => $copyCounts,
            'forecasts' => $reservations->mapWithKeys(fn (Reservation $reservation): array => [
                $reservation->getKey() => $this->insights->estimatedDaysUntilAvailable(
                    $reservation,
                    (int) ($copyCounts[$reservation->bibliographic_record_id] ?? 0),
                ),
            ]),
            'notificationLogs' => $reservations->mapWithKeys(fn (Reservation $reservation): array => [
                $reservation->getKey() => $this->insights->notificationLog($reservation),
            ]),
            'assignableCopies' => $reservations->mapWithKeys(fn (Reservation $reservation): array => [
                $reservation->getKey() => $reservation->status === 'pending'
                    ? $this->insights->assignableCopies($reservation)
                    : collect(),
            ]),
            'queueWaiting' => $reservations->mapWithKeys(fn (Reservation $reservation): array => [
                $reservation->getKey() => $this->insights->hasWaitingQueue($reservation),
            ]),
            'pickupHoldDays' => (int) Setting::valueFor('reservation_lifespan_days', 3),
            // §9.3 — the forecast explanation must quote the period this
            // edition actually gets, not a flat library-wide number.
            'loanPeriodDays' => $copyCounts->mapWithKeys(fn (int $copies, int $recordId): array => [
                $recordId => $this->loanPeriods->daysForCopyCount($copies),
            ]),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function confirm(Request $request, Reservation $reservation): RedirectResponse
    {
        $validated = $request->validate([
            'assigned_copy_id' => ['nullable', 'integer', 'exists:book_copies,id'],
        ]);

        $chosen = isset($validated['assigned_copy_id'])
            ? BookCopy::query()->find($validated['assigned_copy_id'])
            : null;

        try {
            $this->reservations->confirm($reservation, $request->user(), $chosen);
        } catch (CirculationException $exception) {
            return back()->withErrors(['reservation' => $exception->getMessage()]);
        }

        return back()->with('success', __('librarian.reservations.confirmed_success'));
    }

    public function markReady(Request $request, Reservation $reservation): RedirectResponse
    {
        try {
            $this->reservations->markReady($reservation, $request->user());
        } catch (CirculationException $exception) {
            return back()->withErrors(['reservation' => $exception->getMessage()]);
        }

        return back()->with('success', __('librarian.reservations.ready_success'));
    }

    /**
     * §8.3 — extend the pickup hold, but only while the queue is empty.
     */
    public function extend(Request $request, Reservation $reservation): RedirectResponse
    {
        try {
            $reservation = $this->reservations->extend($reservation, $request->user());
        } catch (CirculationException $exception) {
            return back()->withErrors(['reservation' => $exception->getMessage()]);
        }

        return back()->with('success', __('librarian.reservations.extended_success', [
            'until' => $reservation->expires_at?->format('d.m.Y') ?? '—',
        ]));
    }

    /**
     * §8 — release the current hold early and hand the copy to the next reader.
     */
    public function passToNext(Request $request, Reservation $reservation): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        try {
            $this->reservations->passToNext($reservation, $request->user(), $validated['reason']);
        } catch (CirculationException $exception) {
            return back()->withErrors(['reservation' => $exception->getMessage()]);
        }

        return back()->with('success', __('librarian.reservations.passed_success'));
    }

    public function requestTransfer(Request $request, Reservation $reservation): RedirectResponse
    {
        return $this->transferMutation(fn () => $this->transfers->request($reservation, $request->user()));
    }

    public function approveTransfer(Request $request, CopyTransfer $transfer): RedirectResponse
    {
        return $this->transferMutation(fn () => $this->transfers->approve($transfer, $request->user()));
    }

    public function sendTransfer(Request $request, CopyTransfer $transfer): RedirectResponse
    {
        return $this->transferMutation(fn () => $this->transfers->send($transfer, $request->user()));
    }

    public function receiveTransfer(Request $request, CopyTransfer $transfer): RedirectResponse
    {
        $validated = $request->validate(['scanned_code' => ['required', 'string', 'max:128']]);

        return $this->transferMutation(fn () => $this->transfers->receive($transfer, $request->user(), $validated['scanned_code'], $this->reservations));
    }

    public function cancelTransfer(Request $request, CopyTransfer $transfer): RedirectResponse
    {
        $validated = $request->validate(['reason' => ['required', 'string', 'min:5', 'max:1000']]);

        return $this->transferMutation(fn () => $this->transfers->cancel($transfer, $request->user(), $validated['reason']));
    }

    public function cancel(Request $request, Reservation $reservation): RedirectResponse
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'min:5', 'max:1000'],
        ]);

        try {
            $this->reservations->cancel($reservation, $request->user(), $validated['reason'], byStaff: true);
        } catch (CirculationException $exception) {
            return back()->withErrors(['reservation' => $exception->getMessage()]);
        }

        return back()->with('success', __('librarian.reservations.cancelled_success'));
    }

    private function transferMutation(callable $callback): RedirectResponse
    {
        try {
            $callback();
        } catch (CirculationException $exception) {
            return back()->withErrors(['transfer' => $exception->getMessage()]);
        }

        return back()->with('success', __('librarian.reservations.transfer_updated'));
    }
}
