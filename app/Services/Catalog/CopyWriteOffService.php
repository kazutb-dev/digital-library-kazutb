<?php

namespace App\Services\Catalog;

use App\Models\Catalog\BookCopy;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DataQuality\DataQualityScanner;
use App\Services\Operations\KsuOperationsService;
use DateTimeInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final class CopyWriteOffService
{
    public function __construct(
        private readonly ReservationQueueService $reservations,
        private readonly KsuOperationsService $ksu,
        private readonly AuditLogger $audit,
        private readonly DataQualityScanner $scanner,
    ) {}

    /**
     * @param  list<string>  $codes
     * @return array{copies:Collection<int,BookCopy>,ksu_entry_id:int}
     */
    public function writeOffByCodes(
        array $codes,
        DateTimeInterface|string $date,
        string $act,
        string $reason,
        User $actor,
        array $context = [],
    ): array {
        $codes = collect($codes)->map(static fn ($code) => trim((string) $code))->filter()->unique()->values();
        if ($codes->isEmpty()) {
            throw ValidationException::withMessages(['copy_codes' => __('copy_writeoff.validation.codes_required')]);
        }

        $result = DB::transaction(function () use ($codes, $date, $act, $reason, $actor, $context): array {
            $copies = BookCopy::query()->where(function ($query) use ($codes): void {
                $query->whereIn('inventory_number', $codes->all())->orWhereIn('barcode', $codes->all());
            })->orderBy('id')->lockForUpdate()->get();
            $resolvedCopies = collect();
            $missing = collect();
            foreach ($codes as $code) {
                $matches = $copies->filter(static fn (BookCopy $copy): bool => (
                    trim((string) $copy->inventory_number) === $code
                    || trim((string) $copy->barcode) === $code
                ));
                if ($matches->isEmpty()) {
                    $missing->push($code);

                    continue;
                }
                if ($matches->count() > 1) {
                    throw ValidationException::withMessages(['copy_codes' => __('copy_writeoff.validation.ambiguous')]);
                }
                $resolvedCopies->push($matches->first());
            }
            if ($missing->isNotEmpty()) {
                throw ValidationException::withMessages(['copy_codes' => __('copy_writeoff.validation.unknown', ['codes' => $missing->take(10)->implode(', ')])]);
            }
            if ($resolvedCopies->unique(fn (BookCopy $copy): int => (int) $copy->getKey())->count() !== $codes->count()) {
                throw ValidationException::withMessages(['copy_codes' => __('copy_writeoff.validation.ambiguous')]);
            }
            $copies = $resolvedCopies->sortBy(fn (BookCopy $copy): int => (int) $copy->getKey())->values();

            $oldStates = [];
            foreach ($copies as $copy) {
                if ($copy->activeLoan()->exists()) {
                    throw ValidationException::withMessages(['copy_codes' => __('copy_writeoff.validation.active_loan', ['copy' => $copy->inventory_number])]);
                }
                if ($copy->status === 'written_off' || $copy->inventory_status === 'written_off') {
                    throw ValidationException::withMessages(['copy_codes' => __('copy_writeoff.validation.already_written_off', ['copy' => $copy->inventory_number])]);
                }
                $oldStates[$copy->getKey()] = $copy->only(['status', 'inventory_status', 'circulation_status', 'writeoff_date', 'writeoff_act', 'writeoff_reason']);
                $copy->update([
                    'status' => 'written_off', 'writeoff_date' => $date,
                    'writeoff_act' => trim($act), 'writeoff_reason' => trim($reason),
                ]);
                foreach ($copy->reservations()->active()->with('reader')->lockForUpdate()->get() as $reservation) {
                    $this->reservations->cancel($reservation, $actor, __('copy_lifecycle.reservation_cancel_reason', ['copy' => $copy->inventory_number]), true);
                }
            }

            $entry = $this->ksu->recordWithdrawal($copies, $date, $act, $reason, $actor);
            foreach ($copies as $copy) {
                $new = $copy->fresh()->only(['status', 'inventory_status', 'circulation_status', 'writeoff_date', 'writeoff_act', 'writeoff_reason']);
                $copy->recordHistory('write_off', null, $actor->getKey(), null, [
                    'old' => $oldStates[$copy->getKey()], 'new' => $new,
                    'comment' => $reason, 'ksu_withdrawal_entry_id' => $entry->getKey(),
                    ...$context,
                ]);
                $this->audit->logRequired(
                    'copies.status_change', 'book_copy', $copy->getKey(),
                    oldValues: $oldStates[$copy->getKey()], newValues: $new,
                    reason: $reason, scope: 'operational', actor: $actor,
                    metadata: [
                        'ksu_withdrawal_entry_id' => $entry->getKey(),
                        'writeoff_batch_size' => $copies->count(),
                        ...$context,
                    ],
                );
            }

            return ['copies' => $copies, 'ksu_entry_id' => (int) $entry->getKey()];
        }, 5);

        $result['copies']->each(fn (BookCopy $copy) => $this->scanner->scanModel($copy->fresh(), 'book_copy'));

        return $result;
    }
}
