<?php

namespace App\Services\Catalog;

use App\Exceptions\CirculationException;
use App\Models\Catalog\LibraryVisit;
use App\Models\Catalog\ReaderProfile;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Attendance recording (ДИР 9.4). A card scan at the entrance produces a
 * visit; nothing about circulation is touched.
 *
 * This is the software entry point only — a physical turnstile or kiosk can
 * call `record()` later with source='turnstile' without any change here.
 */
class LibraryVisitService
{
    /**
     * A second scan of the same card within this window is treated as the same
     * visit — readers tap twice, and door hardware repeats.
     */
    public const DEDUPE_MINUTES = 10;

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * Resolve a reader from a scanned card barcode or ticket number.
     */
    public function findReaderByCode(string $code): ?User
    {
        $code = trim($code);
        if ($code === '') {
            return null;
        }

        $folded = mb_strtolower($code);

        return User::query()
            ->whereHas('readerProfile', fn (Builder $profile) => $profile
                ->whereRaw('LOWER(COALESCE(barcode, \'\')) = ?', [$folded])
                ->orWhereRaw('LOWER(ticket_number) = ?', [$folded]))
            ->with('readerProfile')
            ->first();
    }

    /**
     * Record a visit. Returns the existing row when the same reader was already
     * scanned moments ago, so repeat taps do not inflate the numbers.
     *
     * @return array{visit: LibraryVisit, duplicate: bool}
     */
    public function record(
        User $reader,
        ?int $branchId = null,
        ?User $staff = null,
        string $source = 'desk',
        ?string $notes = null,
    ): array {
        // Attendance is not circulation: a blocked reader who walks in has
        // still walked in, so status is not a gate here. Only a profile is
        // required, to keep visits tied to actual library readers.
        $profile = ReaderProfile::query()->where('user_id', $reader->getKey())->first();
        if ($profile === null) {
            throw CirculationException::because('visit_reader_not_registered');
        }

        $recent = LibraryVisit::query()
            ->where('user_id', $reader->getKey())
            ->where('scanned_at', '>=', now()->subMinutes(self::DEDUPE_MINUTES))
            ->orderByDesc('scanned_at')
            ->first();

        if ($recent !== null) {
            return ['visit' => $recent, 'duplicate' => true];
        }

        $visit = LibraryVisit::query()->create([
            'user_id' => $reader->getKey(),
            'branch_id' => $branchId,
            'scanned_at' => now(),
            'scanned_by' => $staff?->getKey(),
            'source' => in_array($source, LibraryVisit::SOURCES, true) ? $source : 'desk',
            'notes' => $notes,
        ]);

        $this->audit->log(
            actionType: 'visit.record',
            entityType: 'library_visit',
            entityId: $visit->getKey(),
            newValues: [
                'reader_id' => $reader->getKey(),
                'branch_id' => $branchId,
                'source' => $visit->source,
            ],
            scope: 'library',
            actor: $staff ?? ['name' => 'Kiosk', 'role' => 'system'],
        );

        return ['visit' => $visit, 'duplicate' => false];
    }

    /**
     * Visits per calendar day across the period, zero-filled so a quiet day
     * reads as zero rather than vanishing from the series.
     *
     * @return Collection<string, int>
     */
    public function dailyTotals(Carbon $from, Carbon $to): Collection
    {
        $counted = LibraryVisit::query()
            ->selectRaw('DATE(scanned_at) as day, count(*) as total')
            ->between($from, $to)
            ->groupBy('day')
            ->pluck('total', 'day');

        $series = collect();
        $cursor = $from->copy()->startOfDay();
        while ($cursor->lte($to)) {
            $day = $cursor->toDateString();
            $series->put($day, (int) ($counted[$day] ?? 0));
            $cursor->addDay();
        }

        return $series;
    }

    /**
     * Visits per ISO week, for periods too long to read day by day.
     *
     * @return Collection<string, int>
     */
    public function weeklyTotals(Carbon $from, Carbon $to): Collection
    {
        return $this->dailyTotals($from, $to)
            ->groupBy(fn (int $total, string $day): string => Carbon::parse($day)->startOfWeek()->toDateString())
            ->map(fn (Collection $days): int => (int) $days->sum())
            ->sortKeys();
    }

    /**
     * Visits per branch. Unattributed scans are grouped under a null branch.
     *
     * @return Collection<int, object>
     */
    public function branchTotals(Carbon $from, Carbon $to): Collection
    {
        return LibraryVisit::query()
            ->leftJoin('branches', 'branches.id', '=', 'library_visits.branch_id')
            ->selectRaw("COALESCE(branches.name, '—') as branch, count(*) as visits, count(DISTINCT library_visits.user_id) as readers")
            ->whereBetween('library_visits.scanned_at', [$from, $to])
            ->groupBy('branches.name')
            ->orderByDesc('visits')
            ->get();
    }

    /**
     * @return array{visits: int, unique_readers: int, busiest_day: string|null, busiest_day_visits: int}
     */
    public function summary(Carbon $from, Carbon $to): array
    {
        $daily = $this->dailyTotals($from, $to);
        $busiest = $daily->filter()->sortDesc()->keys()->first();

        return [
            'visits' => (int) LibraryVisit::query()->between($from, $to)->count(),
            'unique_readers' => (int) LibraryVisit::query()->between($from, $to)->distinct()->count('user_id'),
            'busiest_day' => $busiest,
            'busiest_day_visits' => $busiest === null ? 0 : (int) $daily[$busiest],
        ];
    }
}
