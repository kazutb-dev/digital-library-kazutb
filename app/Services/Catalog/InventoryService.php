<?php

namespace App\Services\Catalog;

use App\Exceptions\CirculationException;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\InventoryScan;
use App\Models\Catalog\InventorySession;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Support\Facades\DB;

class InventoryService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function create(array $data, User $actor): InventorySession
    {
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
                ->lockForUpdate()->get();
            foreach ($copies as $copy) {
                $session->items()->create([
                    'copy_id' => $copy->getKey(), 'expected_branch_id' => $copy->branch_id,
                    'expected_fund_id' => $copy->fund_id, 'expected_shelf' => $copy->shelf_location,
                    'expected_status' => $copy->status, 'result' => 'missing',
                ]);
            }
            $session->update(['status' => 'running', 'started_at' => now(), 'expected_count' => $copies->count(), 'missing_count' => $copies->count()]);
            $this->audit->logRequired('inventory.started', 'inventory_session', $session->getKey(), newValues: ['expected_count' => $copies->count()], scope: 'operational', actor: $actor);

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

    public function complete(InventorySession $session, User $actor): InventorySession
    {
        return DB::transaction(function () use ($session, $actor): InventorySession {
            $session = InventorySession::query()->whereKey($session->getKey())->lockForUpdate()->firstOrFail();
            if ($session->status !== 'running') {
                throw CirculationException::because('inventory_not_running');
            }
            $this->recount($session);
            $session->update(['status' => 'review', 'completed_at' => now()]);
            $this->audit->logRequired('inventory.completed', 'inventory_session', $session->getKey(), newValues: $session->only(['found_count', 'missing_count', 'misplaced_count', 'unknown_count', 'duplicate_count']), scope: 'operational', actor: $actor);

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
            $session->update(['status' => 'approved', 'approved_at' => now(), 'approved_by' => $actor->getKey()]);
            $this->audit->logRequired('inventory.approved', 'inventory_session', $session->getKey(), scope: 'operational', actor: $actor);

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
}
