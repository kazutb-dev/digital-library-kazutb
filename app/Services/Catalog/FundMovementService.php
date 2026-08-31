<?php

namespace App\Services\Catalog;

use App\Models\Catalog\BookCopy;
use App\Models\Fund;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DataQuality\DataQualityScanner;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * Atomic physical-placement changes for one or more copies.
 *
 * This is deliberately separate from reservation transfers: a fund movement
 * changes a confirmed storage placement, while CopyTransferService transports
 * a copy reserved for a reader between branches.
 */
final class FundMovementService
{
    /** @var list<string> */
    private const LOCATION_FIELDS = [
        'branch_id', 'fund_id', 'storage_sigla', 'service_point_code',
        'room', 'section', 'shelf_index', 'shelf_location',
    ];

    public function __construct(
        private readonly AuditLogger $audit,
        private readonly DataQualityScanner $scanner,
    ) {}

    /**
     * @param  list<string>  $codes
     * @param  array<string, mixed>  $destination
     * @return array{batch_id:string,copies:Collection<int, BookCopy>}
     */
    public function move(array $codes, array $destination, string $reason, User $actor): array
    {
        $codes = collect($codes)
            ->map(static fn (mixed $code): string => trim((string) $code))
            ->filter()
            ->unique()
            ->values();

        if ($codes->isEmpty()) {
            throw ValidationException::withMessages([
                'copy_codes' => __('fund_movements.validation.codes_required'),
            ]);
        }

        $destination = collect($destination)
            ->only(self::LOCATION_FIELDS)
            ->map(static fn (mixed $value): mixed => is_string($value) ? trim($value) : $value)
            ->reject(static fn (mixed $value): bool => $value === '' || $value === null)
            ->all();

        if ($destination === []) {
            throw ValidationException::withMessages([
                'destination' => __('fund_movements.validation.destination_required'),
            ]);
        }

        $batchId = (string) Str::uuid();

        /** @var Collection<int, BookCopy> $moved */
        $moved = DB::transaction(function () use ($codes, $destination, $reason, $actor, $batchId): Collection {
            $copies = BookCopy::query()
                ->where(function ($query) use ($codes): void {
                    $query->whereIn('barcode', $codes->all())
                        ->orWhereIn('inventory_number', $codes->all());
                })
                ->orderBy('id')
                ->lockForUpdate()
                ->get();

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
                    throw ValidationException::withMessages([
                        'copy_codes' => __('fund_movements.validation.codes_ambiguous'),
                    ]);
                }
                $resolvedCopies->push($matches->first());
            }
            if ($missing->isNotEmpty()) {
                throw ValidationException::withMessages([
                    'copy_codes' => __('fund_movements.validation.codes_unknown', [
                        'codes' => $missing->take(10)->implode(', '),
                    ]),
                ]);
            }

            if ($resolvedCopies->unique(fn (BookCopy $copy): int => (int) $copy->getKey())->count() !== $codes->count()) {
                throw ValidationException::withMessages([
                    'copy_codes' => __('fund_movements.validation.codes_ambiguous'),
                ]);
            }
            $copies = $resolvedCopies->sortBy(fn (BookCopy $copy): int => (int) $copy->getKey())->values();

            $effectiveBranch = array_key_exists('branch_id', $destination)
                ? (int) $destination['branch_id']
                : null;
            $destinationFund = null;
            if (array_key_exists('fund_id', $destination)) {
                $destinationFund = Fund::query()->whereKey((int) $destination['fund_id'])->firstOrFail();
                if ($effectiveBranch !== null && (int) $destinationFund->branch_id !== $effectiveBranch) {
                    throw ValidationException::withMessages([
                        'fund_id' => __('fund_movements.validation.fund_branch_mismatch'),
                    ]);
                }
            }

            $existingFunds = Fund::query()
                ->whereIn('id', $copies->pluck('fund_id')->filter()->unique()->all())
                ->get()
                ->keyBy(fn (Fund $fund): int => (int) $fund->getKey());

            foreach ($copies as $copy) {
                if ($copy->activeLoan()->exists()) {
                    throw ValidationException::withMessages([
                        'copy_codes' => __('fund_movements.validation.active_loan', ['copy' => $copy->inventory_number]),
                    ]);
                }
                if ($copy->activeReservation()->exists()) {
                    throw ValidationException::withMessages([
                        'copy_codes' => __('fund_movements.validation.active_reservation', ['copy' => $copy->inventory_number]),
                    ]);
                }
                [$legacyInventory, $legacyCirculation] = BookCopy::separatedStateFor((string) $copy->status);
                $inventoryState = $copy->inventory_status ?: $legacyInventory;
                $circulationState = $copy->circulation_status ?: $legacyCirculation;
                if (in_array($inventoryState, ['lost', 'written_off', 'repair'], true)
                    || in_array($circulationState, ['on_loan', 'in_transfer'], true)
                    || in_array($copy->status, ['lost', 'written_off', 'under_repair', 'issued', 'overdue'], true)) {
                    throw ValidationException::withMessages([
                        'copy_codes' => __('fund_movements.validation.status_blocked', [
                            'copy' => $copy->inventory_number,
                            'status' => __('librarian.copies.statuses.'.$copy->status),
                        ]),
                    ]);
                }

                $copyDestination = $destination;
                $resultingBranchId = array_key_exists('branch_id', $copyDestination)
                    ? (int) $copyDestination['branch_id']
                    : ($copy->branch_id === null ? null : (int) $copy->branch_id);
                $resultingFund = $destinationFund
                    ?? ($copy->fund_id === null ? null : $existingFunds->get((int) $copy->fund_id));
                if ($resultingBranchId !== null && $resultingFund !== null
                    && (int) $resultingFund->branch_id !== $resultingBranchId) {
                    throw ValidationException::withMessages([
                        'fund_id' => __('fund_movements.validation.fund_branch_mismatch'),
                    ]);
                }

                $old = $copy->only(self::LOCATION_FIELDS);
                $copy->fill($copyDestination);
                $new = $copy->only(self::LOCATION_FIELDS);
                if ($old === $new) {
                    throw ValidationException::withMessages([
                        'destination' => __('fund_movements.validation.unchanged', ['copy' => $copy->inventory_number]),
                    ]);
                }

                $copy->save();
                $copy->recordHistory('fund_movement', null, $actor->getKey(), null, [
                    'movement_batch_id' => $batchId,
                    'reason' => $reason,
                    'old' => $old,
                    'new' => $new,
                ]);
                $this->audit->logRequired(
                    actionType: 'copies.movement',
                    entityType: 'book_copy',
                    entityId: $copy->getKey(),
                    oldValues: $old,
                    newValues: $new,
                    reason: $reason,
                    scope: 'library',
                    metadata: ['movement_batch_id' => $batchId],
                    actor: $actor,
                );
            }

            return $copies;
        }, 3);

        $moved->each(fn (BookCopy $copy) => $this->scanner->scanModel($copy->fresh(), 'book_copy'));

        return ['batch_id' => $batchId, 'copies' => $moved];
    }
}
