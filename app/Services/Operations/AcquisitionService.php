<?php

namespace App\Services\Operations;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Fund;
use App\Models\Ksu\KsuAuditEvent;
use App\Models\Ksu\KsuBook;
use App\Models\Ksu\KsuEntry;
use App\Models\Ksu\KsuEntryItem;
use App\Models\Operations\AcquisitionBatch;
use App\Models\Operations\AcquisitionBatchItem;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class AcquisitionService
{
    public function __construct(
        private readonly KsuNumberAllocator $ksuNumbers,
        private readonly InventoryNumberAllocator $inventoryNumbers,
        private readonly AuditLogger $audit,
    ) {}

    /** @param array<string,mixed> $data */
    public function createDraft(User $actor, array $data): AcquisitionBatch
    {
        return DB::transaction(function () use ($actor, $data): AcquisitionBatch {
            $items = $this->normalizeItems($data['items'] ?? []);
            unset($data['items']);
            [$titleCount, $copyCount, $totalAmount] = $this->totals($items);
            $attributes = $this->batchAttributes($data);
            $this->assertFundBelongsToBranch(
                $attributes['branch_id'] ?? null,
                $attributes['fund_id'] ?? null,
            );

            $batch = AcquisitionBatch::query()->create([
                ...$attributes,
                'batch_number' => trim((string) ($data['batch_number'] ?? '')) ?: $this->newBatchNumber($data),
                'status' => 'draft',
                'title_count' => $titleCount,
                'copy_count' => $copyCount,
                'total_amount' => $totalAmount,
                'created_by' => $actor->getKey(),
            ]);
            $batch->items()->createMany($items);

            $this->audit->logRequired(
                'acquisition_batch.draft_created',
                'acquisition_batch',
                (string) $batch->getKey(),
                newValues: $this->auditSnapshot($batch),
                scope: 'operational',
                actor: $actor,
            );

            return $batch->load(['items.bibliographicRecord', 'branch', 'fund']);
        });
    }

    /** @param array<string,mixed> $data */
    public function updateDraft(User $actor, AcquisitionBatch $batch, array $data): AcquisitionBatch
    {
        return DB::transaction(function () use ($actor, $batch, $data): AcquisitionBatch {
            $batch = AcquisitionBatch::query()->whereKey($batch->getKey())->lockForUpdate()->firstOrFail();
            $this->requireDraft($batch);
            $old = $this->auditSnapshot($batch);

            $attributes = $this->batchAttributes($data, true);
            $this->assertFundBelongsToBranch(
                array_key_exists('branch_id', $attributes) ? $attributes['branch_id'] : $batch->branch_id,
                array_key_exists('fund_id', $attributes) ? $attributes['fund_id'] : $batch->fund_id,
            );
            if (array_key_exists('items', $data)) {
                $items = $this->normalizeItems($data['items']);
                [$titleCount, $copyCount, $totalAmount] = $this->totals($items);
                $batch->items()->delete();
                $batch->items()->createMany($items);
                $attributes['title_count'] = $titleCount;
                $attributes['copy_count'] = $copyCount;
                $attributes['total_amount'] = $totalAmount;
            }
            $batch->update($attributes);

            $this->audit->logRequired(
                'acquisition_batch.draft_updated',
                'acquisition_batch',
                (string) $batch->getKey(),
                oldValues: $old,
                newValues: $this->auditSnapshot($batch->refresh()),
                scope: 'operational',
                actor: $actor,
            );

            return $batch->load(['items.bibliographicRecord', 'branch', 'fund']);
        });
    }

    public function cancelDraft(User $actor, AcquisitionBatch $batch): AcquisitionBatch
    {
        return DB::transaction(function () use ($actor, $batch): AcquisitionBatch {
            $batch = AcquisitionBatch::query()->whereKey($batch->getKey())->lockForUpdate()->firstOrFail();
            $this->requireDraft($batch);
            $batch->update(['status' => 'cancelled']);
            $this->audit->logRequired(
                'acquisition_batch.cancelled',
                'acquisition_batch',
                (string) $batch->getKey(),
                newValues: ['status' => 'cancelled'],
                scope: 'operational',
                actor: $actor,
            );

            return $batch->refresh();
        });
    }

    /**
     * Atomically posts a draft as one KSU-1 entry and creates every copy.
     * Any allocation, copy, KSU item, or audit failure rolls back the batch.
     */
    public function confirm(User $actor, AcquisitionBatch $batch): AcquisitionBatch
    {
        try {
            return DB::transaction(function () use ($actor, $batch): AcquisitionBatch {
                $batch = AcquisitionBatch::query()->whereKey($batch->getKey())->lockForUpdate()->firstOrFail();
                if ($batch->status === 'confirmed') {
                    return $batch->load(['items', 'ksuEntry.items']);
                }
                $this->requireDraft($batch);

                $items = AcquisitionBatchItem::query()
                    ->where('acquisition_batch_id', $batch->getKey())
                    ->orderBy('id')
                    ->lockForUpdate()
                    ->get();
                if ($items->isEmpty()) {
                    throw ValidationException::withMessages([
                        'items' => __('operations.messages.items_required'),
                    ]);
                }

                $numberPlans = [];
                foreach ($items->values() as $index => $item) {
                    $numberPlans[(int) $item->getKey()] = $this->numberingAttributes(
                        $item->attributesToArray(),
                        (int) $item->quantity,
                        $index,
                    );
                }
                $this->validateUniqueNumberPlans(array_values($numberPlans));
                $this->lockAndValidateAvailableNumbers(array_values($numberPlans));

                /** @var KsuBook $book */
                $book = KsuBook::query()
                    ->where('code', 'KSU-1')
                    ->where('is_active', true)
                    ->lockForUpdate()
                    ->firstOrFail();
                $receivedAt = Carbon::parse($batch->received_at)->startOfDay();
                $allocation = $this->ksuNumbers->allocate($book, (int) $receivedAt->year);

                $entry = KsuEntry::query()->create([
                    'ksu_book_id' => $book->getKey(),
                    'entry_number' => $allocation['entry_number'],
                    'number' => $allocation['number'],
                    'year' => $allocation['year'],
                    'entry_date' => $receivedAt->toDateString(),
                    'operation_type' => 'arrival',
                    'acquisition_source' => $batch->acquisition_source,
                    'supplier_name' => $batch->supplier_name,
                    'title_count' => $batch->title_count,
                    'copy_count' => $batch->copy_count,
                    'total_cost' => $batch->total_amount,
                    'fund_id' => $batch->fund_id,
                    'branch_id' => $batch->branch_id,
                    'status' => 'posted',
                    'created_by' => $actor->getKey(),
                ]);

                $this->ksuAudit('number.allocated', $actor, $book, $entry, null, [
                    'number' => $allocation['number'],
                    'year' => $allocation['year'],
                    'entry_number' => $allocation['entry_number'],
                ]);

                foreach ($items as $item) {
                    $plan = $numberPlans[(int) $item->getKey()];
                    $autoInventory = $plan['inventory_number_mode'] === 'auto';
                    $autoBarcode = $plan['barcode_mode'] === 'auto';
                    for ($ordinal = 1; $ordinal <= (int) $item->quantity; $ordinal++) {
                        $numbers = $this->allocateAutomaticNumbers(
                            $batch,
                            $item,
                            $allocation['year'],
                            $autoInventory,
                            $autoBarcode,
                        );
                        $inventoryNumber = $autoInventory
                            ? $numbers['inventory_number']
                            : $plan['manual_inventory_numbers'][$ordinal - 1];
                        $barcode = match ($plan['barcode_mode']) {
                            'auto' => $numbers['barcode'],
                            'manual_list' => $plan['manual_barcodes'][$ordinal - 1],
                            default => null,
                        };

                        $copy = BookCopy::query()->forceCreate([
                            'bibliographic_record_id' => $item->bibliographic_record_id,
                            'inventory_number' => $inventoryNumber,
                            'barcode' => $barcode,
                            'accounting_type' => $item->accounting_type,
                            'ksu_number' => $entry->entry_number,
                            'storage_sigla' => $item->storage_sigla,
                            'service_point_code' => $item->service_point_code,
                            'branch_id' => $batch->branch_id,
                            'fund_id' => $batch->fund_id,
                            'room' => $item->room,
                            'section' => $item->section,
                            'shelf_location' => $item->shelf_location,
                            'shelf_index' => $item->shelf_index,
                            'price' => $item->unit_price,
                            'acquisition_source' => $batch->acquisition_source,
                            'supplier_name' => $batch->supplier_name,
                            'acquisition_date' => $receivedAt->toDateString(),
                            'registration_date' => $receivedAt->toDateString(),
                            'condition' => $item->condition,
                            'status' => 'available',
                            'access_restriction' => $item->access_restriction,
                            'issue_count' => 0,
                            'acquisition_batch_id' => $batch->getKey(),
                            'acquisition_batch_item_id' => $item->getKey(),
                            'ksu_entry_id' => $entry->getKey(),
                            'inventory_sequence_scope' => $numbers['scope_key'],
                            'inventory_sequence_year' => $numbers['year'],
                            'inventory_sequence_number' => $numbers['inventory_sequence_number'],
                            'barcode_sequence_number' => $numbers['barcode_sequence_number'],
                            'inventory_status' => 'active',
                            'circulation_status' => 'available',
                        ]);

                        KsuEntryItem::query()->create([
                            'ksu_entry_id' => $entry->getKey(),
                            'book_copy_id' => $copy->getKey(),
                            'bibliographic_record_id' => $item->bibliographic_record_id,
                            'inventory_number' => $copy->inventory_number,
                            'price' => $item->unit_price,
                            'registration_date' => $receivedAt->toDateString(),
                            'link_method' => 'acquisition_batch',
                            'link_confidence' => 'high',
                        ]);
                        $copy->recordHistory(
                            'acquisition.received',
                            actorId: (int) $actor->getKey(),
                            details: [
                                'acquisition_batch_id' => $batch->getKey(),
                                'acquisition_batch_item_id' => $item->getKey(),
                                'ksu_entry_id' => $entry->getKey(),
                                'ordinal' => $ordinal,
                                'inventory_number_mode' => $plan['inventory_number_mode'],
                                'barcode_mode' => $plan['barcode_mode'],
                            ],
                        );
                        $this->ksuAudit('item.linked', $actor, $book, $entry, $copy, [
                            'inventory_number' => $copy->inventory_number,
                            'barcode' => $copy->barcode,
                            'bibliographic_record_id' => $item->bibliographic_record_id,
                        ]);
                    }
                }

                $batch->update([
                    'status' => 'confirmed',
                    'ksu_entry_id' => $entry->getKey(),
                    'confirmed_by' => $actor->getKey(),
                    'confirmed_at' => now('UTC'),
                ]);

                $this->ksuAudit('entry.created', $actor, $book, $entry, null, [
                    'acquisition_batch_id' => $batch->getKey(),
                    'title_count' => $entry->title_count,
                    'copy_count' => $entry->copy_count,
                    'total_cost' => $entry->total_cost,
                ]);
                $this->audit->logRequired(
                    'acquisition_batch.confirmed',
                    'acquisition_batch',
                    (string) $batch->getKey(),
                    newValues: $this->auditSnapshot($batch->refresh()) + [
                        'ksu_entry_number' => $entry->entry_number,
                    ],
                    scope: 'operational',
                    actor: $actor,
                );

                return $batch->load(['items.bibliographicRecord', 'ksuEntry.items.copy', 'branch', 'fund']);
            }, 5);
        } catch (UniqueConstraintViolationException) {
            throw ValidationException::withMessages([
                'items' => __('operations.messages.copy_number_conflict'),
            ]);
        }
    }

    /** @param array<int,array<string,mixed>> $items
     * @return array<int,array<string,mixed>>
     */
    private function normalizeItems(array $items): array
    {
        if ($items === []) {
            throw ValidationException::withMessages(['items' => __('operations.messages.items_required')]);
        }

        $recordIds = collect($items)->pluck('bibliographic_record_id')->map(fn ($id): int => (int) $id)->unique();
        $records = BibliographicRecord::query()->whereKey($recordIds->all())->get(['id', 'title'])->keyBy('id');

        $normalized = collect($items)->values()->map(function (array $item, int $index) use ($records): array {
            $recordId = (int) ($item['bibliographic_record_id'] ?? 0);
            $record = $records->get($recordId);
            $quantity = (int) ($item['quantity'] ?? 0);
            if ($record === null) {
                throw ValidationException::withMessages([
                    "items.{$index}.bibliographic_record_id" => __('operations.messages.record_required'),
                ]);
            }
            if ($quantity < 1 || $quantity > 10000) {
                throw ValidationException::withMessages([
                    "items.{$index}.quantity" => __('operations.messages.quantity_invalid'),
                ]);
            }

            return [
                'bibliographic_record_id' => $recordId,
                'title_snapshot' => (string) $record->title,
                'quantity' => $quantity,
                'unit_price' => number_format((float) ($item['unit_price'] ?? 0), 2, '.', ''),
                'accounting_type' => $item['accounting_type'] ?? 'inventory',
                'condition' => $item['condition'] ?? 'new',
                'access_restriction' => $item['access_restriction'] ?? 'free',
                'storage_sigla' => $this->nullableString($item['storage_sigla'] ?? Setting::valueFor('default_sigla')),
                'service_point_code' => $this->nullableString($item['service_point_code'] ?? Setting::valueFor('default_service_point')),
                'room' => $this->nullableString($item['room'] ?? null),
                'section' => $this->nullableString($item['section'] ?? null),
                'shelf_location' => $this->nullableString($item['shelf_location'] ?? null),
                'shelf_index' => $this->nullableString($item['shelf_index'] ?? null),
                ...$this->numberingAttributes($item, $quantity, $index),
                'inventory_prefix' => trim((string) ($item['inventory_prefix'] ?? Setting::valueFor('inventory_number_prefix', 'INV'))) ?: 'INV',
                'barcode_prefix' => trim((string) ($item['barcode_prefix'] ?? Setting::valueFor('barcode_prefix', 'KAZUTB'))) ?: 'KAZUTB',
                'notes' => $this->nullableString($item['notes'] ?? null),
            ];
        })->all();

        $this->validateUniqueNumberPlans($normalized);

        return $normalized;
    }

    /**
     * @param  array<string,mixed>  $item
     * @return array{
     *   inventory_number_mode:string,manual_inventory_numbers:?array<int,string>,
     *   inventory_range_start:?string,inventory_range_end:?string,
     *   barcode_mode:string,manual_barcodes:?array<int,string>
     * }
     */
    private function numberingAttributes(array $item, int $quantity, int $index): array
    {
        $inventoryAutoEnabled = (bool) Setting::valueFor('inventory_numbering_enabled', true);
        $barcodeAutoEnabled = (bool) Setting::valueFor('barcode_generation_enabled', true);

        $inventoryMode = $this->normalizeMode(
            $item['inventory_number_mode'] ?? $item['inventory_mode'] ?? ($inventoryAutoEnabled ? 'auto' : 'manual_list'),
        );
        if (! in_array($inventoryMode, AcquisitionBatchItem::INVENTORY_NUMBER_MODES, true)) {
            throw ValidationException::withMessages([
                "items.{$index}.inventory_number_mode" => __('operations.messages.inventory_mode_invalid'),
            ]);
        }
        if ($inventoryMode === 'auto' && ! $inventoryAutoEnabled) {
            throw ValidationException::withMessages([
                "items.{$index}.inventory_number_mode" => __('operations.messages.inventory_numbering_disabled'),
            ]);
        }

        $inventoryNumbers = null;
        $rangeStart = null;
        $rangeEnd = null;
        if ($inventoryMode === 'manual_list') {
            $inventoryNumbers = $this->manualValues(
                $item['manual_inventory_numbers'] ?? $item['inventory_numbers'] ?? null,
                $index,
                'manual_inventory_numbers',
            );
            $this->validateExactCount(
                $inventoryNumbers,
                $quantity,
                $index,
                'manual_inventory_numbers',
                'manual_inventory_count',
            );
        } elseif ($inventoryMode === 'range') {
            $rangeStart = $this->nullableString($item['inventory_range_start'] ?? null);
            $rangeEnd = $this->nullableString($item['inventory_range_end'] ?? null);
            $inventoryNumbers = $this->expandInventoryRange($rangeStart, $rangeEnd, $quantity, $index);
        }

        $barcodeMode = $this->normalizeMode(
            $item['barcode_mode'] ?? ($barcodeAutoEnabled ? 'auto' : 'none'),
        );
        if (! in_array($barcodeMode, AcquisitionBatchItem::BARCODE_MODES, true)) {
            throw ValidationException::withMessages([
                "items.{$index}.barcode_mode" => __('operations.messages.barcode_mode_invalid'),
            ]);
        }
        if ($barcodeMode === 'auto' && ! $barcodeAutoEnabled) {
            throw ValidationException::withMessages([
                "items.{$index}.barcode_mode" => __('operations.messages.barcode_generation_disabled'),
            ]);
        }

        $barcodes = null;
        if ($barcodeMode === 'manual_list') {
            $barcodes = $this->manualValues(
                $item['manual_barcodes'] ?? $item['barcodes'] ?? null,
                $index,
                'manual_barcodes',
            );
            $this->validateExactCount(
                $barcodes,
                $quantity,
                $index,
                'manual_barcodes',
                'manual_barcode_count',
            );
        }

        return [
            'inventory_number_mode' => $inventoryMode,
            'manual_inventory_numbers' => $inventoryNumbers,
            'inventory_range_start' => $rangeStart,
            'inventory_range_end' => $rangeEnd,
            'barcode_mode' => $barcodeMode,
            'manual_barcodes' => $barcodes,
        ];
    }

    private function normalizeMode(mixed $mode): string
    {
        return str_replace('-', '_', mb_strtolower(trim((string) $mode)));
    }

    /**
     * @return array<int,string>
     */
    private function manualValues(mixed $raw, int $index, string $field): array
    {
        if ($raw === null || $raw === '') {
            return [];
        }

        if (is_string($raw)) {
            $raw = preg_split('/[\r\n,;]+/u', $raw) ?: [];
        }
        if (! is_array($raw)) {
            throw ValidationException::withMessages([
                "items.{$index}.{$field}" => __('operations.messages.number_list_invalid'),
            ]);
        }

        $values = [];
        foreach ($raw as $value) {
            if (! is_scalar($value)) {
                throw ValidationException::withMessages([
                    "items.{$index}.{$field}" => __('operations.messages.number_list_invalid'),
                ]);
            }
            $value = trim((string) $value);
            if ($value === '') {
                continue;
            }
            if (mb_strlen($value) > 64) {
                throw ValidationException::withMessages([
                    "items.{$index}.{$field}" => __('operations.messages.copy_number_too_long'),
                ]);
            }
            $values[] = $value;
        }

        return $values;
    }

    /**
     * @param  array<int,string>  $values
     */
    private function validateExactCount(
        array $values,
        int $quantity,
        int $index,
        string $field,
        string $message,
    ): void {
        if (count($values) !== $quantity) {
            throw ValidationException::withMessages([
                "items.{$index}.{$field}" => __("operations.messages.{$message}", [
                    'expected' => $quantity,
                    'actual' => count($values),
                ]),
            ]);
        }
        if (count(array_unique($values, SORT_STRING)) !== count($values)) {
            throw ValidationException::withMessages([
                "items.{$index}.{$field}" => __('operations.messages.number_list_duplicate'),
            ]);
        }
    }

    /**
     * @return array<int,string>
     */
    private function expandInventoryRange(
        ?string $start,
        ?string $end,
        int $quantity,
        int $index,
    ): array {
        $valid = $start !== null
            && $end !== null
            && mb_strlen($start) <= 64
            && mb_strlen($end) <= 64
            && preg_match('/^(.*?)([0-9]+)$/u', $start, $startParts) === 1
            && preg_match('/^(.*?)([0-9]+)$/u', $end, $endParts) === 1
            && $startParts[1] === $endParts[1]
            && strlen($startParts[2]) === strlen($endParts[2])
            && strlen($startParts[2]) <= 18;
        if (! $valid) {
            throw ValidationException::withMessages([
                "items.{$index}.inventory_range_start" => __('operations.messages.inventory_range_invalid'),
            ]);
        }

        $first = (int) $startParts[2];
        $last = (int) $endParts[2];
        $actual = $last >= $first ? $last - $first + 1 : 0;
        if ($actual !== $quantity) {
            throw ValidationException::withMessages([
                "items.{$index}.inventory_range_end" => __('operations.messages.inventory_range_count', [
                    'expected' => $quantity,
                    'actual' => $actual,
                ]),
            ]);
        }

        $values = [];
        $width = strlen($startParts[2]);
        for ($value = $first; $value <= $last; $value++) {
            $number = $startParts[1].str_pad((string) $value, $width, '0', STR_PAD_LEFT);
            if (mb_strlen($number) > 64) {
                throw ValidationException::withMessages([
                    "items.{$index}.inventory_range_start" => __('operations.messages.copy_number_too_long'),
                ]);
            }
            $values[] = $number;
        }

        return $values;
    }

    /**
     * Rejects duplicates before persistence. The database unique constraints
     * remain the final authority for races with other transactions.
     *
     * @param  array<int,array<string,mixed>>  $plans
     */
    private function validateUniqueNumberPlans(array $plans): void
    {
        $seenInventory = [];
        $seenBarcodes = [];
        foreach ($plans as $index => $plan) {
            foreach ((array) ($plan['manual_inventory_numbers'] ?? []) as $number) {
                $key = base64_encode((string) $number);
                if (isset($seenInventory[$key])) {
                    throw ValidationException::withMessages([
                        "items.{$index}.manual_inventory_numbers" => __('operations.messages.number_list_duplicate'),
                    ]);
                }
                $seenInventory[$key] = true;
            }
            foreach ((array) ($plan['manual_barcodes'] ?? []) as $barcode) {
                $key = base64_encode((string) $barcode);
                if (isset($seenBarcodes[$key])) {
                    throw ValidationException::withMessages([
                        "items.{$index}.manual_barcodes" => __('operations.messages.number_list_duplicate'),
                    ]);
                }
                $seenBarcodes[$key] = true;
            }
        }
    }

    /**
     * @param  array<int,array<string,mixed>>  $plans
     */
    private function lockAndValidateAvailableNumbers(array $plans): void
    {
        $inventoryNumbers = [];
        $barcodes = [];
        foreach ($plans as $plan) {
            array_push($inventoryNumbers, ...(array) ($plan['manual_inventory_numbers'] ?? []));
            array_push($barcodes, ...(array) ($plan['manual_barcodes'] ?? []));
        }

        foreach (array_chunk($inventoryNumbers, 500) as $chunk) {
            $conflict = BookCopy::query()
                ->whereIn('inventory_number', $chunk)
                ->lockForUpdate()
                ->value('inventory_number');
            if ($conflict !== null) {
                throw ValidationException::withMessages([
                    'items' => __('operations.messages.inventory_number_conflict', ['value' => $conflict]),
                ]);
            }
        }
        foreach (array_chunk($barcodes, 500) as $chunk) {
            $conflict = BookCopy::query()
                ->whereIn('barcode', $chunk)
                ->lockForUpdate()
                ->value('barcode');
            if ($conflict !== null) {
                throw ValidationException::withMessages([
                    'items' => __('operations.messages.barcode_conflict', ['value' => $conflict]),
                ]);
            }
        }
    }

    /**
     * @return array{
     *   scope_key:?string,year:?int,inventory_sequence_number:?int,
     *   barcode_sequence_number:?int,inventory_number:?string,barcode:?string
     * }
     */
    private function allocateAutomaticNumbers(
        AcquisitionBatch $batch,
        AcquisitionBatchItem $item,
        int $year,
        bool $inventory,
        bool $barcode,
    ): array {
        if (! $inventory && ! $barcode) {
            return [
                'scope_key' => null,
                'year' => null,
                'inventory_sequence_number' => null,
                'barcode_sequence_number' => null,
                'inventory_number' => null,
                'barcode' => null,
            ];
        }

        $branchId = $batch->branch_id === null ? null : (int) $batch->branch_id;
        if ($inventory && $barcode) {
            return $this->inventoryNumbers->allocate(
                $branchId,
                $year,
                (string) $item->inventory_prefix,
                (string) $item->barcode_prefix,
            );
        }

        return $this->inventoryNumbers->allocateSelected(
            $branchId,
            $year,
            (string) $item->inventory_prefix,
            (string) $item->barcode_prefix,
            allocateInventory: $inventory,
            allocateBarcode: $barcode,
        );
    }

    /** @param array<int,array<string,mixed>> $items
     * @return array{0:int,1:int,2:string}
     */
    private function totals(array $items): array
    {
        $copies = 0;
        $totalCents = 0;
        foreach ($items as $item) {
            $quantity = (int) $item['quantity'];
            $copies += $quantity;
            $totalCents += $quantity * (int) round(((float) $item['unit_price']) * 100);
        }

        return [count($items), $copies, number_format($totalCents / 100, 2, '.', '')];
    }

    /** @param array<string,mixed> $data
     * @return array<string,mixed>
     */
    private function batchAttributes(array $data, bool $partial = false): array
    {
        $defaults = [
            'received_at' => now('UTC')->toDateString(),
            'acquisition_source' => 'purchase',
            'supplier_name' => null,
            'currency' => 'KZT',
            'branch_id' => null,
            'fund_id' => null,
            'acquisition_order_id' => null,
            'notes' => null,
        ];
        $source = $partial ? $data : [...$defaults, ...$data];
        $attributes = [];
        foreach (array_keys($defaults) as $key) {
            if (! array_key_exists($key, $source)) {
                continue;
            }
            $attributes[$key] = in_array($key, ['supplier_name', 'notes'], true)
                ? $this->nullableString($source[$key])
                : $source[$key];
        }

        return $attributes;
    }

    private function assertFundBelongsToBranch(mixed $branchId, mixed $fundId): void
    {
        if ($branchId === null || $branchId === '' || $fundId === null || $fundId === '') {
            return;
        }

        if (! Fund::query()
            ->whereKey((int) $fundId)
            ->where('branch_id', (int) $branchId)
            ->exists()) {
            throw ValidationException::withMessages([
                'fund_id' => __('operations.messages.fund_branch_mismatch'),
            ]);
        }
    }

    /** @param array<string,mixed> $data */
    private function newBatchNumber(array $data): string
    {
        $date = Carbon::parse($data['received_at'] ?? now('UTC'));

        return 'ARR-'.$date->format('Ymd').'-'.Str::upper(Str::random(8));
    }

    private function requireDraft(AcquisitionBatch $batch): void
    {
        if ($batch->status !== 'draft') {
            throw ValidationException::withMessages([
                'status' => __('operations.messages.only_draft_mutable'),
            ]);
        }
    }

    /** @return array<string,mixed> */
    private function auditSnapshot(AcquisitionBatch $batch): array
    {
        return $batch->only([
            'batch_number',
            'status',
            'received_at',
            'acquisition_source',
            'supplier_name',
            'currency',
            'branch_id',
            'fund_id',
            'title_count',
            'copy_count',
            'total_amount',
            'ksu_entry_id',
            'confirmed_by',
            'confirmed_at',
        ]);
    }

    /** @param array<string,mixed> $newValues */
    private function ksuAudit(
        string $event,
        User $actor,
        KsuBook $book,
        KsuEntry $entry,
        ?BookCopy $copy,
        array $newValues,
    ): void {
        KsuAuditEvent::query()->create([
            'event_type' => $event,
            'ksu_book_id' => $book->getKey(),
            'ksu_entry_id' => $entry->getKey(),
            'book_copy_id' => $copy?->getKey(),
            'actor_id' => $actor->getKey(),
            'actor_name' => $actor->name,
            'new_values' => $newValues,
            'occurred_at' => now('UTC'),
        ]);
    }

    private function nullableString(mixed $value): ?string
    {
        $value = trim((string) ($value ?? ''));

        return $value === '' ? null : $value;
    }
}
