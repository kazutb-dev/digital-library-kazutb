<?php

namespace App\Services\Operations;

use App\Models\Ksu\KsuBook;
use App\Models\Ksu\KsuEntry;
use App\Models\Ksu\KsuSequence;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class KsuNumberAllocator
{
    /**
     * Allocate the next numeric number/year tuple under a locked sequence row.
     *
     * The presentation string is never used to determine the maximum. An
     * explicit batch confirmation is an attended operator action, so the
     * legacy `auto_numbering_enabled` flag (which controls unattended legacy
     * allocation) is intentionally not used as a gate here.
     *
     * @return array{number:int,year:int,entry_number:string}
     */
    public function allocate(KsuBook $book, int $year): array
    {
        if (! (bool) Setting::valueFor('ksu_numbering_enabled', true)) {
            throw ValidationException::withMessages([
                'ksu' => __('operations.messages.ksu_numbering_disabled'),
            ]);
        }
        if ($year < 1900 || $year > 9999) {
            throw new InvalidArgumentException('KSU year must be between 1900 and 9999.');
        }

        return DB::transaction(function () use ($book, $year): array {
            $now = now('UTC');

            KsuSequence::query()->insertOrIgnore([
                'ksu_book_id' => $book->getKey(),
                'year' => $year,
                'last_number' => 0,
                'allocation_enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            /** @var KsuSequence $sequence */
            $sequence = KsuSequence::query()
                ->where('ksu_book_id', $book->getKey())
                ->where('year', $year)
                ->lockForUpdate()
                ->firstOrFail();

            // `number` is an integer column. This re-check makes imported rows
            // authoritative even when ksu_sequences.last_number is stale.
            $observedMaximum = (int) (KsuEntry::query()
                ->where('ksu_book_id', $book->getKey())
                ->where('year', $year)
                ->max('number') ?? 0);
            $next = max((int) $sequence->last_number, $observedMaximum) + 1;

            $sequence->forceFill([
                'last_number' => $next,
                'min_observed' => $sequence->min_observed === null
                    ? $next
                    : min((int) $sequence->min_observed, $next),
                'max_observed' => max((int) ($sequence->max_observed ?? 0), $observedMaximum, $next),
                'allocation_enabled' => true,
            ])->save();

            return [
                'number' => $next,
                'year' => $year,
                'entry_number' => $next.'/'.$year,
            ];
        }, 5);
    }
}
