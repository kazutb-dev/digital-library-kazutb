<?php

namespace App\Services\Operations;

use App\Models\Catalog\BookCopy;
use App\Models\Ksu\KsuAuditEvent;
use App\Models\Ksu\KsuBook;
use App\Models\Ksu\KsuEntry;
use App\Models\Ksu\KsuEntryItem;
use App\Models\User;
use App\Services\AuditLogger;
use DateTimeInterface;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class KsuOperationsService
{
    public function __construct(
        private readonly KsuNumberAllocator $numbers,
        private readonly AuditLogger $audit,
    ) {}

    /**
     * Register a KSU Part 2 withdrawal without changing copy or reservation
     * state. Call this inside the write-off transaction that owns those state
     * transitions; Laravel's nested transaction keeps this work atomic with it.
     *
     * @param  iterable<int,BookCopy|int>  $copies
     */
    public function recordWithdrawal(
        iterable $copies,
        DateTimeInterface|string $date,
        string $act,
        string $reason,
        User $actor,
    ): KsuEntry {
        $act = trim($act);
        $reason = trim($reason);
        if ($act === '' || $reason === '') {
            throw ValidationException::withMessages([
                'writeoff_act' => __('operations.messages.withdrawal_details_required'),
            ]);
        }

        $ids = Collection::make($copies)
            ->map(fn (BookCopy|int $copy): int => $copy instanceof BookCopy ? (int) $copy->getKey() : $copy)
            ->unique()
            ->sort()
            ->values();
        if ($ids->isEmpty()) {
            throw ValidationException::withMessages([
                'copies' => __('operations.messages.withdrawal_copies_required'),
            ]);
        }

        return DB::transaction(function () use ($ids, $date, $act, $reason, $actor): KsuEntry {
            $lockedCopies = BookCopy::query()
                ->whereKey($ids->all())
                ->orderBy('id')
                ->lockForUpdate()
                ->get();
            if ($lockedCopies->count() !== $ids->count()) {
                throw ValidationException::withMessages([
                    'copies' => __('operations.messages.withdrawal_copy_missing'),
                ]);
            }

            $now = now('UTC');
            KsuBook::query()->insertOrIgnore([
                'code' => 'KSU-2',
                'name' => 'КСУ. Часть 2 — выбытие',
                'description' => 'Суммарный учет выбытия библиотечного фонда',
                'numbering_format' => 'number/year',
                'reset_period' => 'year',
                'auto_numbering_enabled' => true,
                'requires_manual_decision' => false,
                'is_active' => true,
                'sort_order' => 20,
                'created_at' => $now,
                'updated_at' => $now,
            ]);
            /** @var KsuBook $book */
            $book = KsuBook::query()->where('code', 'KSU-2')->where('is_active', true)->lockForUpdate()->firstOrFail();

            $entryDate = Carbon::parse($date)->startOfDay();
            $allocation = $this->numbers->allocate($book, (int) $entryDate->year);
            $branchIds = $lockedCopies->pluck('branch_id')->filter()->unique();
            $fundIds = $lockedCopies->pluck('fund_id')->filter()->unique();
            $entry = KsuEntry::query()->create([
                'ksu_book_id' => $book->getKey(),
                'entry_number' => $allocation['entry_number'],
                'number' => $allocation['number'],
                'year' => $allocation['year'],
                'entry_date' => $entryDate->toDateString(),
                'operation_type' => 'withdrawal',
                'act_number' => $act,
                'operation_reason' => $reason,
                'acquisition_source' => 'writeoff',
                'title_count' => $lockedCopies->pluck('bibliographic_record_id')->unique()->count(),
                'copy_count' => $lockedCopies->count(),
                'total_cost' => number_format((float) $lockedCopies->sum('price'), 2, '.', ''),
                'fund_id' => $fundIds->count() === 1 ? $fundIds->first() : null,
                'branch_id' => $branchIds->count() === 1 ? $branchIds->first() : null,
                'status' => 'posted',
                'created_by' => $actor->getKey(),
            ]);

            foreach ($lockedCopies as $copy) {
                KsuEntryItem::query()->create([
                    'ksu_entry_id' => $entry->getKey(),
                    'book_copy_id' => $copy->getKey(),
                    'bibliographic_record_id' => $copy->bibliographic_record_id,
                    'inventory_number' => $copy->inventory_number,
                    'price' => $copy->price,
                    'registration_date' => $copy->registration_date,
                    'link_method' => 'writeoff_act',
                    'link_confidence' => 'high',
                ]);
                KsuAuditEvent::query()->create([
                    'event_type' => 'withdrawal.item_linked',
                    'ksu_book_id' => $book->getKey(),
                    'ksu_entry_id' => $entry->getKey(),
                    'book_copy_id' => $copy->getKey(),
                    'actor_id' => $actor->getKey(),
                    'actor_name' => $actor->name,
                    'new_values' => [
                        'inventory_number' => $copy->inventory_number,
                        'act_number' => $act,
                    ],
                    'reason' => $reason,
                    'occurred_at' => $now,
                ]);
            }

            KsuAuditEvent::query()->create([
                'event_type' => 'withdrawal.created',
                'ksu_book_id' => $book->getKey(),
                'ksu_entry_id' => $entry->getKey(),
                'actor_id' => $actor->getKey(),
                'actor_name' => $actor->name,
                'new_values' => [
                    'entry_number' => $entry->entry_number,
                    'act_number' => $act,
                    'copy_count' => $entry->copy_count,
                ],
                'reason' => $reason,
                'occurred_at' => $now,
            ]);
            $this->audit->logRequired(
                'ksu.withdrawal.created',
                'ksu_entry',
                (string) $entry->getKey(),
                newValues: [
                    'entry_number' => $entry->entry_number,
                    'act_number' => $act,
                    'copy_ids' => $lockedCopies->modelKeys(),
                ],
                reason: $reason,
                scope: 'operational',
                actor: $actor,
            );

            return $entry->load(['book', 'items.copy']);
        }, 5);
    }
}
