<?php

namespace App\Services\Catalog;

use App\Models\Catalog\BookCopy;
use App\Models\Catalog\ReaderNotification;
use App\Models\Catalog\Reservation;
use Illuminate\Support\Collection;

/**
 * Read-side helpers for the reservation screens (8): how deep the waiting
 * line is, a rough availability estimate for queued readers, and the log of
 * notifications already delivered for a reservation.
 *
 * The forecast is deliberately crude — it is an ESTIMATE, not a promise. It
 * assumes every circulating copy turns over once per standard loan period and
 * that readers ahead in the queue collect their copy. Callers must label it as
 * approximate in the UI.
 */
class ReservationInsightService
{
    /** Copy statuses that will never come back into circulation. */
    private const DEAD_COPY_STATUSES = ['lost', 'written_off', 'under_repair'];

    public function __construct(private readonly LoanPeriodPolicy $loanPeriods) {}

    /**
     * Waiting readers per bibliographic record — pending reservations that do
     * not yet hold a copy.
     *
     * @param  iterable<int>  $recordIds
     * @return Collection<int, int>
     */
    public function queueDepths(iterable $recordIds): Collection
    {
        $ids = collect($recordIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return Reservation::query()
            ->selectRaw('bibliographic_record_id, count(*) as total')
            ->whereIn('bibliographic_record_id', $ids)
            ->where('status', 'queued')
            ->groupBy('bibliographic_record_id')
            ->pluck('total', 'bibliographic_record_id')
            ->map(fn ($total): int => (int) $total);
    }

    /**
     * Circulating copies per bibliographic record (lost and written-off stock
     * excluded — it will never satisfy a queue).
     *
     * @param  iterable<int>  $recordIds
     * @return Collection<int, int>
     */
    public function circulatingCopyCounts(iterable $recordIds): Collection
    {
        $ids = collect($recordIds)->filter()->unique()->values();
        if ($ids->isEmpty()) {
            return collect();
        }

        return BookCopy::query()
            ->selectRaw('bibliographic_record_id, count(*) as total')
            ->whereIn('bibliographic_record_id', $ids)
            ->whereNotIn('status', self::DEAD_COPY_STATUSES)
            ->groupBy('bibliographic_record_id')
            ->pluck('total', 'bibliographic_record_id')
            ->map(fn ($total): int => (int) $total);
    }

    /**
     * Rough "available in about N days" estimate for a queued reservation.
     * Returns null when the estimate would be meaningless: the reservation
     * already holds a copy, it is not queued, or the record has no circulating
     * copies at all (nothing to wait for).
     */
    public function estimatedDaysUntilAvailable(Reservation $reservation, ?int $copyCount = null): ?int
    {
        if ($reservation->assigned_copy_id !== null || $reservation->status !== 'queued') {
            return null;
        }

        $position = $this->queuePosition($reservation);
        if ($position < 1) {
            return null;
        }

        $copies = $copyCount ?? (int) BookCopy::query()
            ->where('bibliographic_record_id', $reservation->bibliographic_record_id)
            ->whereNotIn('status', self::DEAD_COPY_STATUSES)
            ->count();
        if ($copies < 1) {
            return null;
        }

        // 9.3 — the turnover assumption must match the period actually
        // written to loans for this edition, which now scales with copy count.
        $loanPeriod = $this->loanPeriods->daysForCopyCount($copies);

        return (int) ceil($position * $loanPeriod / $copies);
    }

    /**
     * In-app notifications delivered for one reservation, newest first.
     *
     * Only the in-app channel is recorded. Email dispatch is fire-and-forget
     * through Laravel Mail and is NOT persisted per reservation, so this log
     * must never be presented as proof of email delivery.
     *
     * @return Collection<int, ReaderNotification>
     */
    public function notificationLog(Reservation $reservation): Collection
    {
        return ReaderNotification::query()
            ->where('user_id', $reservation->user_id)
            ->where('payload->reservation_id', $reservation->getKey())
            ->orderByDesc('created_at')
            ->get();
    }

    /**
     * Copies of the reservation's edition that a librarian may pin to it by
     * hand — free stock plus whatever is already assigned to this reservation.
     *
     * @return Collection<int, BookCopy>
     */
    public function assignableCopies(Reservation $reservation): Collection
    {
        return BookCopy::query()
            ->with('branch')
            ->where('bibliographic_record_id', $reservation->bibliographic_record_id)
            ->where(function ($query) use ($reservation): void {
                $query->where('status', 'available')
                    ->orWhere('id', $reservation->assigned_copy_id);
            })
            ->orderBy('inventory_number')
            ->get();
    }

    /**
     * 8.3 — a pickup hold may only be extended when nobody is next in line.
     */
    public function hasWaitingQueue(Reservation $reservation): bool
    {
        return Reservation::query()
            ->where('bibliographic_record_id', $reservation->bibliographic_record_id)
            ->whereKeyNot($reservation->getKey())
            ->where('status', 'queued')
            ->whereNull('assigned_copy_id')
            ->exists();
    }

    public function queuePosition(Reservation $reservation): int
    {
        if ($reservation->status !== 'queued') {
            return 0;
        }

        $ahead = Reservation::query()
            ->where('bibliographic_record_id', $reservation->bibliographic_record_id)
            ->where('status', 'queued')
            ->where(function ($query) use ($reservation): void {
                $query->where('priority', '>', $reservation->priority)
                    ->orWhere(function ($samePriority) use ($reservation): void {
                        $samePriority->where('priority', $reservation->priority)
                            ->where(function ($earlier) use ($reservation): void {
                                $earlier->where('queue_sequence', '<', $reservation->queue_sequence)
                                    ->orWhere(function ($tie) use ($reservation): void {
                                        $tie->where('queue_sequence', $reservation->queue_sequence)->where('id', '<', $reservation->getKey());
                                    });
                            });
                    });
            })->count();

        return $ahead + 1;
    }
}
