<?php

namespace App\Services\Operations;

use App\Models\Catalog\BookCopy;
use App\Models\Ksu\KsuAuditEvent;
use App\Models\Ksu\KsuBook;
use App\Models\Ksu\KsuConflict;
use App\Models\Ksu\KsuEntry;
use App\Models\Ksu\KsuEntryItem;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

final readonly class KsuLegacyReviewService
{
    public const ACTION_LINK_EXISTING = 'link_existing';

    public const ACTION_CREATE_HISTORICAL = 'create_historical';

    public const ACTION_IGNORE = 'ignore';

    public const ACTION_LEAVE_UNRESOLVED = 'leave_unresolved';

    public function __construct(private AuditLogger $audit) {}

    /**
     * Default review queue: one row per untouched raw source value.
     *
     * @param  array{status?:string,kind?:string,book?:int,q?:string}  $filters
     */
    public function groupedQueue(array $filters, int $perPage = 25): LengthAwarePaginator
    {
        $query = KsuConflict::query()
            ->leftJoin('book_copies as direct_copy', 'direct_copy.id', '=', 'ksu_conflicts.book_copy_id')
            ->leftJoin('book_copies as source_copy', 'source_copy.legacy_inv_id', '=', 'ksu_conflicts.source_inv_id');

        $this->applyFilters($query, $filters, 'ksu_conflicts.');

        $groups = $query
            ->select('ksu_conflicts.ksu_number_raw')
            ->selectRaw('COUNT(*) AS conflict_count')
            ->selectRaw('MIN(ksu_conflicts.source_inv_id) AS source_inv_min')
            ->selectRaw('MAX(ksu_conflicts.source_inv_id) AS source_inv_max')
            ->selectRaw('MIN(ksu_conflicts.source_doc_id) AS source_doc_min')
            ->selectRaw('MAX(ksu_conflicts.source_doc_id) AS source_doc_max')
            ->selectRaw('MIN(COALESCE(direct_copy.registration_date, source_copy.registration_date)) AS registration_date_from')
            ->selectRaw('MAX(COALESCE(direct_copy.registration_date, source_copy.registration_date)) AS registration_date_to')
            ->selectRaw('MIN(ksu_conflicts.created_at) AS queued_at_from')
            ->selectRaw('MAX(ksu_conflicts.created_at) AS queued_at_to')
            ->selectRaw('MIN(ksu_conflicts.ksu_book_id) AS primary_book_id')
            ->selectRaw('COUNT(DISTINCT ksu_conflicts.ksu_book_id) AS book_count')
            ->groupBy('ksu_conflicts.ksu_number_raw')
            ->orderByDesc('conflict_count')
            ->orderBy('ksu_conflicts.ksu_number_raw')
            ->paginate($perPage, pageName: 'groups_page')
            ->withQueryString();

        $groups->setCollection($groups->getCollection()->map(function (KsuConflict $group) use ($filters): KsuConflict {
            $group->setAttribute('conflict_count', (int) $group->getAttribute('conflict_count'));
            foreach (['source_inv_min', 'source_inv_max', 'source_doc_min', 'source_doc_max', 'primary_book_id'] as $attribute) {
                $value = $group->getAttribute($attribute);
                $group->setAttribute($attribute, $value === null ? null : (int) $value);
            }
            $group->setAttribute('book_count', (int) $group->getAttribute('book_count'));
            foreach (['registration_date_from', 'registration_date_to'] as $attribute) {
                $value = $group->getAttribute($attribute);
                $group->setAttribute($attribute, $value === null ? null : Carbon::parse($value)->toDateString());
            }
            $group->setAttribute(
                'valid_historical_number',
                KsuEntry::parseStrictLegacyNumber($group->ksu_number_raw) !== null,
            );

            $examples = KsuConflict::query()
                ->forRawNumber($group->ksu_number_raw)
                ->with([
                    'copy:id,bibliographic_record_id,inventory_number,legacy_inv_id,registration_date',
                    'sourceCopy:id,bibliographic_record_id,inventory_number,legacy_inv_id,registration_date',
                ]);
            $this->applyFilters($examples, $filters);
            $group->setRelation('examples', $examples->orderBy('id')->limit(3)->get());

            return $group;
        }));

        return $groups;
    }

    /**
     * Resolve an entire raw-number group. `leave_unresolved` deliberately
     * returns before any query, transaction, timestamp, or audit write.
     *
     * @return array{action:string,mutated:bool,conflicts:int,copies:int,entry_id:int|null}
     */
    public function resolveGroup(
        ?string $rawNumber,
        string $action,
        User $actor,
        ?int $entryId = null,
        ?string $reason = null,
    ): array {
        if ($action === self::ACTION_LEAVE_UNRESOLVED) {
            return [
                'action' => $action,
                'mutated' => false,
                'conflicts' => 0,
                'copies' => 0,
                'entry_id' => null,
            ];
        }

        $reason = trim((string) $reason);
        if ($reason === '') {
            throw ValidationException::withMessages([
                'resolution_note' => __('operations.messages.ksu_resolution_note_required'),
            ]);
        }

        $tuple = null;
        if ($action === self::ACTION_CREATE_HISTORICAL) {
            $tuple = KsuEntry::parseStrictLegacyNumber($rawNumber);
            if ($tuple === null) {
                throw ValidationException::withMessages([
                    'ksu_number_raw' => __('operations.messages.ksu_historical_number_invalid'),
                ]);
            }
        }

        return DB::transaction(function () use ($rawNumber, $action, $actor, $entryId, $reason, $tuple): array {
            $conflicts = $this->lockOpenGroup($rawNumber);

            if ($action === self::ACTION_IGNORE) {
                return $this->ignoreLockedGroup($conflicts, $rawNumber, $actor, $reason);
            }

            if ($action === self::ACTION_LINK_EXISTING) {
                if ($entryId === null) {
                    throw ValidationException::withMessages([
                        'ksu_entry_id' => __('operations.messages.ksu_existing_entry_required'),
                    ]);
                }
                $entry = $this->lockExistingPartOneEntry($entryId);
                $copiesByConflict = $this->lockCopiesFor($conflicts);

                return $this->linkLockedGroup($conflicts, $copiesByConflict, $entry, $rawNumber, $actor, $reason);
            }

            if ($action !== self::ACTION_CREATE_HISTORICAL || $tuple === null) {
                throw ValidationException::withMessages([
                    'action' => __('operations.messages.ksu_group_action_invalid'),
                ]);
            }

            $book = KsuBook::query()->where('code', 'KSU-1')->lockForUpdate()->first();
            if ($book === null) {
                throw ValidationException::withMessages([
                    'ksu_number_raw' => __('operations.messages.ksu_part_one_missing'),
                ]);
            }

            $collision = KsuEntry::query()
                ->where('ksu_book_id', $book->getKey())
                ->where(function (Builder $query) use ($rawNumber, $tuple): void {
                    $query->where(function (Builder $numeric) use ($tuple): void {
                        $numeric->where('number', $tuple['number'])->where('year', $tuple['year']);
                    })->orWhere('entry_number', $rawNumber);
                })
                ->lockForUpdate()
                ->first();
            if ($collision !== null) {
                throw ValidationException::withMessages([
                    'ksu_number_raw' => __('operations.messages.ksu_historical_entry_exists'),
                ]);
            }

            $copiesByConflict = $this->lockCopiesFor($conflicts);
            $copies = $copiesByConflict->unique(fn (BookCopy $copy): int => (int) $copy->getKey())->values();
            $entry = $this->createHistoricalEntry($book, $rawNumber, $tuple, $copies, $conflicts, $actor, $reason);
            $result = $this->linkLockedGroup($conflicts, $copiesByConflict, $entry, $rawNumber, $actor, $reason);

            KsuAuditEvent::query()->create([
                'event_type' => 'legacy.historical_entry_created',
                'ksu_book_id' => $book->getKey(),
                'ksu_entry_id' => $entry->getKey(),
                'actor_id' => $actor->getKey(),
                'actor_name' => $actor->name,
                'new_values' => [
                    'entry_number' => $entry->entry_number,
                    'number' => $entry->number,
                    'year' => $entry->year,
                    'copy_count' => $entry->copy_count,
                    'ksu_number_raw' => $rawNumber,
                ],
                'reason' => $reason,
                'occurred_at' => now('UTC'),
            ]);
            $this->audit->logRequired(
                'ksu.legacy.historical_created',
                'ksu_entry',
                (string) $entry->getKey(),
                newValues: [
                    'entry_number' => $entry->entry_number,
                    'number' => $entry->number,
                    'year' => $entry->year,
                    'ksu_number_raw' => $rawNumber,
                    'linked_copy_count' => $result['copies'],
                ],
                reason: $reason,
                scope: 'operational',
                actor: $actor,
            );

            return array_replace($result, ['action' => self::ACTION_CREATE_HISTORICAL]);
        }, 5);
    }

    /** @return Collection<int,KsuConflict> */
    private function lockOpenGroup(?string $rawNumber): Collection
    {
        $conflicts = KsuConflict::query()
            ->forRawNumber($rawNumber)
            ->where('kind', 'unresolved_link')
            ->where('status', 'open')
            ->orderBy('id')
            ->lockForUpdate()
            ->get();

        if ($conflicts->isEmpty()) {
            throw ValidationException::withMessages([
                'ksu_number_raw' => __('operations.messages.ksu_group_not_found'),
            ]);
        }

        return $conflicts;
    }

    private function lockExistingPartOneEntry(int $entryId): KsuEntry
    {
        $candidate = KsuEntry::query()->whereKey($entryId)->first(['id', 'ksu_book_id']);
        if ($candidate === null) {
            throw ValidationException::withMessages([
                'ksu_entry_id' => __('operations.messages.ksu_existing_entry_required'),
            ]);
        }

        // All mutating paths take the KSU book lock before an entry lock. The
        // initial unlocked lookup is only used to find that serialization row;
        // the entry is authoritatively re-read under lock immediately after.
        $book = KsuBook::query()->whereKey($candidate->ksu_book_id)->lockForUpdate()->first();
        $entry = KsuEntry::query()->whereKey($entryId)->lockForUpdate()->first();
        if ($entry === null || (int) $entry->ksu_book_id !== (int) $candidate->ksu_book_id) {
            throw ValidationException::withMessages([
                'ksu_entry_id' => __('operations.messages.ksu_existing_entry_required'),
            ]);
        }
        if ($book?->code !== 'KSU-1') {
            throw ValidationException::withMessages([
                'ksu_entry_id' => __('operations.messages.ksu_existing_entry_not_part_one'),
            ]);
        }
        if (! in_array($entry->status, ['legacy', 'posted'], true)) {
            throw ValidationException::withMessages([
                'ksu_entry_id' => __('operations.messages.ksu_existing_entry_not_final'),
            ]);
        }

        $entry->setRelation('book', $book);

        return $entry;
    }

    /**
     * @param  Collection<int,KsuConflict>  $conflicts
     * @return Collection<int,BookCopy> keyed by conflict id
     */
    private function lockCopiesFor(Collection $conflicts): Collection
    {
        $copyIds = $conflicts->pluck('book_copy_id')->filter()->map(fn ($id): int => (int) $id)->unique()->values();
        $sourceInvIds = $conflicts->pluck('source_inv_id')->filter()->map(fn ($id): int => (int) $id)->unique()->values();

        $copies = BookCopy::query()
            ->where(function (Builder $query) use ($copyIds, $sourceInvIds): void {
                if ($copyIds->isNotEmpty()) {
                    $query->whereKey($copyIds->all());
                }
                if ($sourceInvIds->isNotEmpty()) {
                    $method = $copyIds->isEmpty() ? 'whereIn' : 'orWhereIn';
                    $query->{$method}('legacy_inv_id', $sourceInvIds->all());
                }
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        $byId = $copies->keyBy(fn (BookCopy $copy): int => (int) $copy->getKey());
        $bySource = $copies->whereNotNull('legacy_inv_id')->keyBy(fn (BookCopy $copy): int => (int) $copy->legacy_inv_id);
        $resolved = collect();

        foreach ($conflicts as $conflict) {
            $direct = $conflict->book_copy_id === null ? null : $byId->get((int) $conflict->book_copy_id);
            $source = $conflict->source_inv_id === null ? null : $bySource->get((int) $conflict->source_inv_id);
            if ($direct !== null && $source !== null && ! $direct->is($source)) {
                throw ValidationException::withMessages([
                    'ksu_number_raw' => __('operations.messages.ksu_group_copy_ambiguous'),
                ]);
            }

            $copy = $direct ?? $source;
            if ($copy === null) {
                throw ValidationException::withMessages([
                    'ksu_number_raw' => __('operations.messages.ksu_group_copy_missing'),
                ]);
            }
            $resolved->put((int) $conflict->getKey(), $copy);
        }

        return $resolved;
    }

    /**
     * @param  Collection<int,KsuConflict>  $conflicts
     * @param  Collection<int,BookCopy>  $copiesByConflict
     * @return array{action:string,mutated:bool,conflicts:int,copies:int,entry_id:int}
     */
    private function linkLockedGroup(
        Collection $conflicts,
        Collection $copiesByConflict,
        KsuEntry $entry,
        ?string $rawNumber,
        User $actor,
        string $reason,
    ): array {
        $copies = $copiesByConflict->unique(fn (BookCopy $copy): int => (int) $copy->getKey())->values();
        foreach ($copies as $copy) {
            if ($copy->ksu_entry_id !== null && (int) $copy->ksu_entry_id !== (int) $entry->getKey()) {
                throw ValidationException::withMessages([
                    'ksu_entry_id' => __('operations.messages.ksu_group_copy_already_linked'),
                ]);
            }
        }

        $copyIds = $copies->map(fn (BookCopy $copy): int => (int) $copy->getKey())->all();
        $sourceInvIds = $conflicts->pluck('source_inv_id')->filter()->map(fn ($id): int => (int) $id)->unique()->values();
        $existingLinks = KsuEntryItem::query()
            ->whereHas('entry', fn (Builder $query): Builder => $query->where('ksu_book_id', $entry->ksu_book_id))
            ->where(function (Builder $query) use ($copyIds, $sourceInvIds): void {
                $query->whereIn('book_copy_id', $copyIds);
                if ($sourceInvIds->isNotEmpty()) {
                    $query->orWhereIn('source_inv_id', $sourceInvIds->all());
                }
            })
            ->orderBy('id')
            ->lockForUpdate()
            ->get();
        if ($existingLinks->contains(fn (KsuEntryItem $item): bool => (int) $item->ksu_entry_id !== (int) $entry->getKey())) {
            throw ValidationException::withMessages([
                'ksu_entry_id' => __('operations.messages.ksu_group_copy_already_linked'),
            ]);
        }

        $oldCopyEntries = $copies->mapWithKeys(fn (BookCopy $copy): array => [
            (int) $copy->getKey() => $copy->ksu_entry_id === null ? null : (int) $copy->ksu_entry_id,
        ])->all();
        foreach ($copies as $copy) {
            if ($copy->ksu_entry_id === null) {
                $copy->forceFill(['ksu_entry_id' => $entry->getKey()])->save();
            }
        }

        $createdItemIds = [];
        foreach ($conflicts as $conflict) {
            /** @var BookCopy $copy */
            $copy = $copiesByConflict->get((int) $conflict->getKey());
            $identity = $conflict->source_inv_id !== null
                ? ['ksu_entry_id' => $entry->getKey(), 'source_inv_id' => $conflict->source_inv_id]
                : ['ksu_entry_id' => $entry->getKey(), 'book_copy_id' => $copy->getKey()];
            $item = KsuEntryItem::query()->firstOrNew($identity);
            if ($item->exists && $item->book_copy_id !== null && (int) $item->book_copy_id !== (int) $copy->getKey()) {
                throw ValidationException::withMessages([
                    'ksu_entry_id' => __('operations.messages.ksu_group_copy_ambiguous'),
                ]);
            }
            if (! $item->exists) {
                $item->fill([
                    'book_copy_id' => $copy->getKey(),
                    'bibliographic_record_id' => $copy->bibliographic_record_id,
                    'source_inv_id' => $conflict->source_inv_id ?? $copy->legacy_inv_id,
                    'source_doc_id' => $conflict->source_doc_id ?? $copy->legacy_doc_id,
                    'inventory_number' => $copy->inventory_number,
                    'price' => $copy->price,
                    'registration_date' => $copy->registration_date,
                    'link_method' => 'legacy_review.group',
                    'link_confidence' => 'high',
                ])->save();
            }
            $createdItemIds[(int) $item->getKey()] = true;

            $conflict->forceFill([
                'status' => 'resolved',
                'book_copy_id' => $copy->getKey(),
                'resolution_note' => $reason,
                'resolved_by' => $actor->getKey(),
                'resolved_at' => now('UTC'),
            ])->save();
        }

        $now = now('UTC');
        foreach ($copies as $copy) {
            KsuAuditEvent::query()->create([
                'event_type' => 'legacy.group_item_linked',
                'ksu_book_id' => $entry->ksu_book_id,
                'ksu_entry_id' => $entry->getKey(),
                'book_copy_id' => $copy->getKey(),
                'actor_id' => $actor->getKey(),
                'actor_name' => $actor->name,
                'old_values' => ['ksu_entry_id' => $oldCopyEntries[(int) $copy->getKey()] ?? null],
                'new_values' => ['ksu_entry_id' => $entry->getKey(), 'ksu_number_raw' => $rawNumber],
                'reason' => $reason,
                'occurred_at' => $now,
            ]);
        }

        $summary = $this->groupSummary($conflicts, $rawNumber) + [
            'entry_id' => (int) $entry->getKey(),
            'entry_number' => $entry->entry_number,
            'linked_copy_count' => $copies->count(),
            'entry_item_count' => count($createdItemIds),
        ];
        KsuAuditEvent::query()->create([
            'event_type' => 'legacy.group_linked',
            'ksu_book_id' => $entry->ksu_book_id,
            'ksu_entry_id' => $entry->getKey(),
            'actor_id' => $actor->getKey(),
            'actor_name' => $actor->name,
            'old_values' => ['status' => 'open', 'ksu_number_raw' => $rawNumber, 'conflict_count' => $conflicts->count()],
            'new_values' => ['status' => 'resolved'] + $summary,
            'reason' => $reason,
            'occurred_at' => $now,
        ]);
        $this->audit->logRequired(
            'ksu.legacy.group_linked',
            'ksu_conflict_group',
            $this->groupIdentifier($rawNumber),
            oldValues: ['status' => 'open', 'ksu_number_raw' => $rawNumber, 'conflict_count' => $conflicts->count()],
            newValues: ['status' => 'resolved'] + $summary,
            reason: $reason,
            scope: 'operational',
            actor: $actor,
        );

        return [
            'action' => self::ACTION_LINK_EXISTING,
            'mutated' => true,
            'conflicts' => $conflicts->count(),
            'copies' => $copies->count(),
            'entry_id' => (int) $entry->getKey(),
        ];
    }

    /**
     * @param  Collection<int,BookCopy>  $copies
     * @param  Collection<int,KsuConflict>  $conflicts
     * @param  array{number:int,year:int}  $tuple
     */
    private function createHistoricalEntry(
        KsuBook $book,
        string $rawNumber,
        array $tuple,
        Collection $copies,
        Collection $conflicts,
        User $actor,
        string $reason,
    ): KsuEntry {
        $dates = $copies->pluck('registration_date')->filter()->sort()->values();
        $prices = $copies->filter(fn (BookCopy $copy): bool => $copy->price !== null);
        $branches = $copies->pluck('branch_id')->filter()->unique()->values();
        $funds = $copies->pluck('fund_id')->filter()->unique()->values();
        $sources = $copies->pluck('acquisition_source')->filter()->unique()->values();
        $suppliers = $copies->pluck('supplier_name')->filter()->unique()->values();

        return KsuEntry::query()->create([
            'ksu_book_id' => $book->getKey(),
            'entry_number' => $rawNumber,
            'number' => $tuple['number'],
            'year' => $tuple['year'],
            'entry_date' => $dates->first()?->toDateString(),
            'operation_type' => 'arrival',
            'operation_reason' => $reason,
            'acquisition_source' => $sources->count() === 1 ? $sources->first() : null,
            'supplier_name' => $suppliers->count() === 1 ? $suppliers->first() : null,
            'title_count' => $copies->pluck('bibliographic_record_id')->filter()->unique()->count(),
            'copy_count' => $copies->count(),
            'total_cost' => $prices->isEmpty() ? null : number_format((float) $prices->sum('price'), 2, '.', ''),
            'fund_id' => $funds->count() === 1 ? $funds->first() : null,
            'branch_id' => $branches->count() === 1 ? $branches->first() : null,
            'status' => 'legacy',
            'legacy_ksu_id' => $rawNumber,
            'legacy_source_table' => 'INV.T990t',
            'legacy_breakdown' => $this->groupSummary($conflicts, $rawNumber),
            'source_row_hash' => hash('sha256', 'KSU-1|'.$rawNumber),
            'created_by' => $actor->getKey(),
        ]);
    }

    /**
     * @param  Collection<int,KsuConflict>  $conflicts
     * @return array{action:string,mutated:bool,conflicts:int,copies:int,entry_id:null}
     */
    private function ignoreLockedGroup(
        Collection $conflicts,
        ?string $rawNumber,
        User $actor,
        string $reason,
    ): array {
        foreach ($conflicts as $conflict) {
            $conflict->forceFill([
                'status' => 'ignored',
                'resolution_note' => $reason,
                'resolved_by' => $actor->getKey(),
                'resolved_at' => now('UTC'),
            ])->save();
        }

        $bookIds = $conflicts->pluck('ksu_book_id')->filter()->unique()->values();
        $summary = $this->groupSummary($conflicts, $rawNumber);
        KsuAuditEvent::query()->create([
            'event_type' => 'legacy.group_ignored',
            'ksu_book_id' => $bookIds->count() === 1 ? $bookIds->first() : null,
            'actor_id' => $actor->getKey(),
            'actor_name' => $actor->name,
            'old_values' => ['status' => 'open', 'ksu_number_raw' => $rawNumber, 'conflict_count' => $conflicts->count()],
            'new_values' => ['status' => 'ignored'] + $summary,
            'reason' => $reason,
            'occurred_at' => now('UTC'),
        ]);
        $this->audit->logRequired(
            'ksu.legacy.group_ignored',
            'ksu_conflict_group',
            $this->groupIdentifier($rawNumber),
            oldValues: ['status' => 'open', 'ksu_number_raw' => $rawNumber, 'conflict_count' => $conflicts->count()],
            newValues: ['status' => 'ignored'] + $summary,
            reason: $reason,
            scope: 'operational',
            actor: $actor,
        );

        return [
            'action' => self::ACTION_IGNORE,
            'mutated' => true,
            'conflicts' => $conflicts->count(),
            'copies' => 0,
            'entry_id' => null,
        ];
    }

    /** @param array{status?:string,kind?:string,book?:int,q?:string} $filters */
    private function applyFilters(Builder $query, array $filters, string $prefix = ''): void
    {
        $query
            ->when($filters['status'] ?? null, fn (Builder $builder, $status): Builder => $builder->where($prefix.'status', $status))
            ->when($filters['kind'] ?? null, fn (Builder $builder, $kind): Builder => $builder->where($prefix.'kind', $kind))
            ->when($filters['book'] ?? null, fn (Builder $builder, $book): Builder => $builder->where($prefix.'ksu_book_id', $book));

        $search = trim((string) ($filters['q'] ?? ''));
        if ($search !== '') {
            $query->where(function (Builder $scope) use ($prefix, $search): void {
                $scope->where($prefix.'ksu_number_raw', 'like', '%'.$search.'%')
                    ->orWhere($prefix.'reason', 'like', '%'.$search.'%');
                if (ctype_digit($search)) {
                    $scope->orWhere($prefix.'source_inv_id', (int) $search)
                        ->orWhere($prefix.'source_doc_id', (int) $search);
                }
            });
        }
    }

    /**
     * @param  Collection<int,KsuConflict>  $conflicts
     * @return array<string,mixed>
     */
    private function groupSummary(Collection $conflicts, ?string $rawNumber): array
    {
        $sourceInv = $conflicts->pluck('source_inv_id')->filter()->map(fn ($id): int => (int) $id);
        $sourceDoc = $conflicts->pluck('source_doc_id')->filter()->map(fn ($id): int => (int) $id);

        return [
            'ksu_number_raw' => $rawNumber,
            'conflict_count' => $conflicts->count(),
            'source_inv_from' => $sourceInv->min(),
            'source_inv_to' => $sourceInv->max(),
            'source_doc_from' => $sourceDoc->min(),
            'source_doc_to' => $sourceDoc->max(),
        ];
    }

    private function groupIdentifier(?string $rawNumber): string
    {
        return $rawNumber === null ? '[NULL]' : $rawNumber;
    }
}
