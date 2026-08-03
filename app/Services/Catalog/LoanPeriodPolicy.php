<?php

namespace App\Services\Catalog;

use App\Models\Catalog\BookCopy;
use App\Models\Setting;

/**
 * ДИР §9.3 — the loan period depends on how many copies of the edition the
 * library holds: a scarce title comes back sooner so the next reader is not
 * kept waiting, an abundant one can stay out longer.
 *
 * The interview gives only the RANGE ("от 3 до 7 дней"), not the step logic.
 * The three-tier scale below is therefore a proposed heuristic, and every
 * threshold and duration lives in Settings so the library can retune it from
 * /admin/settings without a code change.
 *
 * Reading-room stock is a DIFFERENT axis — "may this leave the building at
 * all" rather than "how scarce is it" — so reference_loan_period_days keeps
 * overriding the scale entirely.
 */
class LoanPeriodPolicy
{
    /** Dead stock never returns to circulation, so it cannot ease scarcity. */
    private const DEAD_COPY_STATUSES = ['lost', 'written_off'];

    /**
     * The period that will actually be written to a loan for this copy.
     */
    public function daysForCopy(BookCopy $copy): int
    {
        if ($copy->access_restriction === 'reading_room') {
            return max(1, (int) Setting::valueFor('reference_loan_period_days', 1));
        }

        return $this->daysForRecord((int) $copy->bibliographic_record_id);
    }

    public function daysForRecord(int $recordId): int
    {
        return $this->daysForCopyCount($this->circulatingCopies($recordId));
    }

    /**
     * The scale itself. A record with no registered copies yet is treated as
     * the scarcest case — that is what it will be the moment one is added.
     */
    public function daysForCopyCount(int $copies): int
    {
        foreach ($this->tiers() as $tier) {
            if ($tier['max_copies'] === null || $copies <= $tier['max_copies']) {
                return $tier['days'];
            }
        }

        return max(1, (int) Setting::valueFor('loan_period_abundant_days', 7));
    }

    /**
     * Physical copies of the edition that can circulate. Counts every status
     * except lost/written-off — an issued or reserved copy is still part of
     * the pool that determines scarcity.
     */
    public function circulatingCopies(int $recordId): int
    {
        return (int) BookCopy::query()
            ->where('bibliographic_record_id', $recordId)
            ->whereNotIn('status', self::DEAD_COPY_STATUSES)
            ->count();
    }

    /**
     * The configured scale, normalised so the thresholds always ascend even if
     * someone saves a lower "standard" ceiling than the "scarce" one.
     *
     * @return list<array{key: string, days: int, max_copies: int|null}>
     */
    public function tiers(): array
    {
        $scarceMax = max(1, (int) Setting::valueFor('loan_period_scarce_max_copies', 2));
        $standardMax = max($scarceMax + 1, (int) Setting::valueFor('loan_period_standard_max_copies', 5));

        return [
            [
                'key' => 'scarce',
                'days' => max(1, (int) Setting::valueFor('loan_period_scarce_days', 3)),
                'max_copies' => $scarceMax,
            ],
            [
                'key' => 'standard',
                'days' => max(1, (int) Setting::valueFor('loan_period_standard_days', 5)),
                'max_copies' => $standardMax,
            ],
            [
                'key' => 'abundant',
                'days' => max(1, (int) Setting::valueFor('loan_period_abundant_days', 7)),
                'max_copies' => null,
            ],
        ];
    }

    /**
     * Which tier a copy count falls into, for explaining the outcome in the UI.
     *
     * @return array{key: string, days: int, copies: int}
     */
    public function describeCopyCount(int $copies): array
    {
        foreach ($this->tiers() as $tier) {
            if ($tier['max_copies'] === null || $copies <= $tier['max_copies']) {
                return ['key' => $tier['key'], 'days' => $tier['days'], 'copies' => $copies];
            }
        }

        $last = $this->tiers()[2];

        return ['key' => $last['key'], 'days' => $last['days'], 'copies' => $copies];
    }

    /**
     * Human-readable summary of the whole scale, e.g. "1–2 экз. → 3 дн.".
     *
     * @return list<array{key: string, days: int, from: int, to: int|null}>
     */
    public function scaleRows(): array
    {
        $rows = [];
        $from = 1;
        foreach ($this->tiers() as $tier) {
            $rows[] = [
                'key' => $tier['key'],
                'days' => $tier['days'],
                'from' => $from,
                'to' => $tier['max_copies'],
            ];
            $from = ($tier['max_copies'] ?? $from) + 1;
        }

        return $rows;
    }
}
