<?php

namespace App\Services\Catalog;

use App\Exceptions\CirculationException;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\InventoryScan;
use App\Models\Catalog\InventorySession;
use App\Models\Fund;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\DataQuality\DataQualityScanner;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class InventoryService
{
    public function __construct(
        private readonly AuditLogger $audit,
        private readonly DataQualityScanner $scanner,
    ) {}

    public function create(array $data, User $actor): InventorySession
    {
        $this->assertFundBelongsToBranch($data['branch_id'] ?? null, $data['fund_id'] ?? null);

        return DB::transaction(function () use ($data, $actor): InventorySession {
            $session = InventorySession::query()->create([
                ...$data,
                'session_number' => 'INV-'.now()->format('YmdHis').'-'.strtoupper(bin2hex(random_bytes(2))),
                'inventory_date' => $data['inventory_date'] ?? today(),
                'responsible_id' => $actor->getKey(),
                'status' => 'draft',
            ]);
            $this->audit->logRequired('inventory.session_created', 'inventory_session', $session->getKey(), newValues: $session->only(['branch_id', 'fund_id', 'room', 'shelf_range']), scope: 'operational', actor: $actor);

            return $session;
        });
    }

    public function start(InventorySession $session, User $actor): InventorySession
    {
        return DB::transaction(function () use ($session, $actor): InventorySession {
            $session = InventorySession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
            if ($session->status !== 'draft') {
                throw CirculationException::because('inventory_not_draft');
            }
            $conflict = InventorySession::query()->whereKeyNot($session->getKey())
                ->where('branch_id', $session->branch_id)->whereIn('status', ['running', 'review'])
                ->when($session->fund_id, fn ($q) => $q->where('fund_id', $session->fund_id))
                ->when($session->shelf_range, fn ($q) => $q->where('shelf_range', $session->shelf_range))
                ->lockForUpdate()->exists();
            if ($conflict) {
                throw CirculationException::because('inventory_zone_conflict');
            }

            $copies = BookCopy::query()->where('branch_id', $session->branch_id)
                ->when($session->fund_id, fn ($q) => $q->where('fund_id', $session->fund_id))
                ->when($session->shelf_range, fn ($q) => $q->where('shelf_location', 'like', $session->shelf_range.'%'))
                ->when($session->pilot_limit, fn ($q) => $q->where(fn ($copy) => $copy->whereNull('barcode')->orWhere('barcode', '')))
                ->orderBy('shelf_location')->orderBy('inventory_number')
                ->when($session->pilot_limit, fn ($q) => $q->limit($session->pilot_limit))
                ->lockForUpdate()->get();
            foreach ($copies as $copy) {
                $session->items()->create([
                    'copy_id' => $copy->getKey(), 'expected_branch_id' => $copy->branch_id,
                    'expected_fund_id' => $copy->fund_id, 'expected_shelf' => $copy->shelf_location,
                    'expected_status' => $copy->status, 'result' => 'missing',
                ]);
            }
            $old = $session->only(['status', 'started_at', 'expected_count', 'missing_count']);
            $session->update(['status' => 'running', 'started_at' => now(), 'expected_count' => $copies->count(), 'missing_count' => $copies->count()]);
            $this->audit->logRequired(
                'inventory.started',
                'inventory_session',
                $session->getKey(),
                oldValues: $old,
                newValues: $session->fresh()->only(['status', 'started_at', 'expected_count', 'missing_count']),
                scope: 'operational',
                actor: $actor,
            );

            return $session->refresh();
        });
    }

    public function scan(InventorySession $session, string $code, User $actor): InventoryScan
    {
        return DB::transaction(function () use ($session, $code, $actor): InventoryScan {
            $session = InventorySession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
            if ($session->status !== 'running') {
                throw CirculationException::because('inventory_not_running');
            }
            $scanLimit = max(10, (int) Setting::valueFor('inventory_batch_scan_limit', 5000));
            if ($session->scans()->count() >= $scanLimit) {
                throw CirculationException::because('inventory_scan_limit');
            }
            $code = trim($code);
            $duplicate = $session->scans()->where('code', $code)->exists();
            $copy = BookCopy::query()->where('barcode', $code)->orWhere('inventory_number', $code)->first();
            $item = $copy ? $session->items()->where('copy_id', $copy->getKey())->lockForUpdate()->first() : null;

            $classification = match (true) {
                $duplicate => 'duplicate',
                $copy === null => 'unknown',
                $item === null => 'misplaced',
                in_array($copy->status, ['issued', 'overdue', 'lost', 'written_off'], true) => 'status_conflict',
                default => 'found',
            };
            $scan = $session->scans()->create([
                'copy_id' => $copy?->getKey(), 'scanned_by' => $actor->getKey(), 'code' => $code,
                'classification' => $classification, 'is_duplicate' => $duplicate,
                'details' => $copy ? ['actual_branch_id' => $copy->branch_id, 'actual_status' => $copy->status, 'actual_shelf' => $copy->shelf_location] : null,
                'scanned_at' => now(),
            ]);
            if (! $duplicate && $item !== null) {
                $item->update(['result' => $classification, 'first_scanned_at' => now()]);
            }
            $this->recount($session);
            $this->audit->logRequired('inventory.copy_scanned', 'inventory_scan', $scan->getKey(), newValues: ['session_id' => $session->getKey(), 'classification' => $classification, 'copy_id' => $copy?->getKey()], scope: 'operational', actor: $actor);

            return $scan;
        });
    }

    /**
     * Confirm that an employee physically saw a known copy. The database
     * inventory number is the lookup key; ISBN is deliberately not accepted.
     */
    public function verifyPhysical(
        InventorySession $session,
        string $inventoryNumber,
        string $inventoryCondition,
        User $actor,
        ?string $observedInventoryNumber = null,
    ): InventoryScan {
        $started = hrtime(true);
        $allowed = ['visible', 'db_only', 'unreadable', 'mismatch'];
        if (! in_array($inventoryCondition, $allowed, true)) {
            throw ValidationException::withMessages(['inventory_condition' => __('librarian.inventory.invalid_inventory_condition')]);
        }
        if ($inventoryCondition === 'mismatch' && blank($observedInventoryNumber)) {
            throw ValidationException::withMessages(['observed_inventory_number' => __('librarian.inventory.observed_required')]);
        }

        return DB::transaction(function () use ($session, $inventoryNumber, $inventoryCondition, $observedInventoryNumber, $actor, $started): InventoryScan {
            $session = InventorySession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
            if ($session->status !== 'running') {
                throw CirculationException::because('inventory_not_running');
            }
            $copy = BookCopy::query()->where('inventory_number', trim($inventoryNumber))->lockForUpdate()->first();
            if ($copy === null) {
                throw ValidationException::withMessages(['inventory_number' => __('librarian.inventory.inventory_not_found')]);
            }

            $item = $session->items()->where('copy_id', $copy->getKey())->lockForUpdate()->first();
            $inExpectedZone = $item !== null;
            if ($item === null) {
                $item = $session->items()->create([
                    'copy_id' => $copy->getKey(), 'expected_branch_id' => $copy->branch_id,
                    'expected_fund_id' => $copy->fund_id, 'expected_shelf' => $copy->shelf_location,
                    'expected_status' => $copy->status, 'result' => 'misplaced',
                ]);
            }

            $identified = $inventoryCondition === 'visible';
            $classification = match (true) {
                ! $identified => 'requires_review',
                ! $inExpectedZone => 'misplaced',
                in_array($copy->status, ['issued', 'overdue', 'lost', 'written_off'], true) => 'status_conflict',
                default => 'found',
            };
            $details = [
                'inventory_condition' => $inventoryCondition,
                'observed_inventory_number' => $observedInventoryNumber,
                'actual_branch_id' => $session->branch_id,
                'actual_fund_id' => $session->fund_id,
                'actual_room' => $session->room,
                'actual_section' => $session->section,
                'actual_shelf' => $session->shelf_range,
                'db_branch_id' => $copy->branch_id,
                'db_fund_id' => $copy->fund_id,
                'db_room' => $copy->room,
                'db_section' => $copy->section,
                'db_shelf' => $copy->shelf_location,
            ];
            $scan = $session->scans()->create([
                'copy_id' => $copy->getKey(), 'scanned_by' => $actor->getKey(),
                'code' => trim($inventoryNumber), 'classification' => $classification,
                'is_duplicate' => false, 'details' => $details, 'scanned_at' => now(),
            ]);
            $item->update([
                'result' => $classification,
                'first_scanned_at' => $item->first_scanned_at ?? now(),
                'inventory_condition' => $inventoryCondition,
                'observed_inventory_number' => $observedInventoryNumber,
                'verified_by' => $actor->getKey(),
                'verified_at' => $identified ? now() : null,
                'handling_seconds' => max(0, (int) ((hrtime(true) - $started) / 1_000_000_000)),
            ]);
            if ($identified) {
                $copy->recordHistory('physical_presence_confirmed', null, $actor->getKey(), null, $details + ['inventory_session_id' => $session->getKey()]);
            }
            $this->recount($session);
            $this->audit->logRequired(
                'inventory.physical_verification', 'book_copy', $copy->getKey(),
                newValues: $details + ['session_id' => $session->getKey(), 'classification' => $classification],
                scope: 'operational', actor: $actor,
            );

            return $scan;
        });
    }

    /** Confirm the observed zone, applying a correction only with explicit authority and confirmation. */
    public function confirmLocation(InventorySession $session, BookCopy $copy, User $actor, bool $applyCorrection): array
    {
        $correction = DB::transaction(function () use ($session, $copy, $actor, $applyCorrection): array {
            $session = InventorySession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
            $copy = BookCopy::query()->whereKey($copy->getKey())->lockForUpdate()->firstOrFail();
            $this->assertFundBelongsToBranch($session->branch_id, $session->fund_id);
            $item = $session->items()->where('copy_id', $copy->getKey())->lockForUpdate()->firstOrFail();
            if ($item->inventory_condition !== 'visible' || $item->verified_at === null) {
                throw CirculationException::because('inventory_identity_not_confirmed');
            }
            $actual = [
                'branch_id' => $session->branch_id,
                'fund_id' => $session->fund_id,
                'room' => $session->room,
                'section' => $session->section,
                'shelf_location' => $session->shelf_range,
            ];
            $old = $copy->only(array_keys($actual));
            $changes = array_filter($actual, fn ($value, $key) => filled($value) && (string) $old[$key] !== (string) $value, ARRAY_FILTER_USE_BOTH);
            if ($changes !== [] && ! $applyCorrection) {
                throw CirculationException::because('inventory_location_confirmation_required');
            }
            if ($changes !== [] && ! $actor->can('copies.edit')) {
                throw CirculationException::because('inventory_location_correction_forbidden');
            }

            if ($changes !== []) {
                $copy->update($changes);
                $copy->recordHistory('physical_location_corrected', null, $actor->getKey(), null, ['session_id' => $session->getKey(), 'old' => $old, 'new' => $copy->only(array_keys($actual))]);
            }
            $item->update([
                'location_confirmed_at' => now(),
                'location_corrected_at' => $changes === [] ? null : now(),
            ]);
            $this->audit->logRequired(
                $changes === [] ? 'inventory.location_confirmed' : 'inventory.location_corrected',
                'book_copy', $copy->getKey(), oldValues: $old,
                newValues: $copy->fresh()->only(array_keys($actual)) + ['session_id' => $session->getKey()],
                scope: 'operational', actor: $actor,
            );

            return ['corrected' => $changes !== []];
        });

        $before = $this->openQualityCount($copy);
        $this->scanner->scanModel($copy->fresh(), 'book_copy');
        $after = $this->openQualityCount($copy);

        return $correction + ['resolved' => max(0, $before - $after), 'remaining' => $after];
    }

    public function complete(InventorySession $session, User $actor): InventorySession
    {
        return DB::transaction(function () use ($session, $actor): InventorySession {
            $session = InventorySession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
            if ($session->status !== 'running') {
                throw CirculationException::because('inventory_not_running');
            }
            $old = $session->only(['status', 'completed_at', 'found_count', 'missing_count', 'misplaced_count', 'unknown_count', 'duplicate_count']);
            $this->recount($session);
            $session->update(['status' => 'review', 'completed_at' => now()]);
            $this->audit->logRequired(
                'inventory.completed',
                'inventory_session',
                $session->getKey(),
                oldValues: $old,
                newValues: $session->fresh()->only(['status', 'completed_at', 'found_count', 'missing_count', 'misplaced_count', 'unknown_count', 'duplicate_count']),
                scope: 'operational',
                actor: $actor,
            );

            return $session->refresh();
        });
    }

    public function approve(InventorySession $session, User $actor): InventorySession
    {
        return DB::transaction(function () use ($session, $actor): InventorySession {
            $session = InventorySession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
            if ($session->status !== 'review') {
                throw CirculationException::because('inventory_not_review');
            }
            $old = $session->only(['status', 'approved_at', 'approved_by']);
            $session->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $actor->getKey()]);
            $this->audit->logRequired(
                'inventory.approved',
                'inventory_session',
                $session->getKey(),
                oldValues: $old,
                newValues: $session->fresh()->only(['status', 'approved_at', 'approved_by']),
                scope: 'operational',
                actor: $actor,
            );

            return $session;
        });
    }

    private function recount(InventorySession $session): void
    {
        $session->update([
            'found_count' => $session->items()->whereIn('result', ['found', 'status_conflict'])->count(),
            'missing_count' => $session->items()->where('result', 'missing')->count(),
            'misplaced_count' => $session->scans()->where('classification', 'misplaced')->where('is_duplicate', false)->count(),
            'unknown_count' => $session->scans()->where('classification', 'unknown')->where('is_duplicate', false)->count(),
            'duplicate_count' => $session->scans()->where('is_duplicate', true)->count(),
        ]);
    }

    private function openQualityCount(BookCopy $copy): int
    {
        return DB::table('data_quality_issues')->where('entity_type', 'book_copy')
            ->where('entity_id', (string) $copy->getKey())
            ->whereNotIn('status', ['resolved', 'dismissed', 'ignored', 'false_positive'])
            ->count();
    }

    private function assertFundBelongsToBranch(mixed $branchId, mixed $fundId): void
    {
        if ($fundId === null || $fundId === '') {
            return;
        }

        if ($branchId === null || $branchId === '' || ! Fund::query()
            ->whereKey((int) $fundId)
            ->where('branch_id', (int) $branchId)
            ->exists()) {
            throw ValidationException::withMessages([
                'fund_id' => __('librarian.inventory.fund_branch_mismatch'),
            ]);
        }
    }
}
