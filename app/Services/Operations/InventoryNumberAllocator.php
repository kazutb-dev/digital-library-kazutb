<?php

namespace App\Services\Operations;

use App\Models\Catalog\BookCopy;
use App\Models\Operations\InventorySequence;
use App\Models\Setting;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class InventoryNumberAllocator
{
    /**
     * @return array{
     *   scope_key:string,year:int,inventory_sequence_number:int,
     *   barcode_sequence_number:int,inventory_number:string,barcode:string
     * }
     */
    public function allocate(
        ?int $branchId,
        int $year,
        string $inventoryPrefix = 'INV',
        string $barcodePrefix = 'KAZUTB',
    ): array {
        /** @var array{scope_key:string,year:int,inventory_sequence_number:int,barcode_sequence_number:int,inventory_number:string,barcode:string} */
        return $this->allocateSelected(
            $branchId,
            $year,
            $inventoryPrefix,
            $barcodePrefix,
            allocateInventory: true,
            allocateBarcode: true,
        );
    }

    /**
     * Allocates either or both independent counters without deriving a value
     * from a database id. A single locked row remains the authority for the
     * branch/year scope, while unused counters are not consumed.
     *
     * @return array{
     *   scope_key:string,year:int,inventory_sequence_number:?int,
     *   barcode_sequence_number:?int,inventory_number:?string,barcode:?string
     * }
     */
    public function allocateSelected(
        ?int $branchId,
        int $year,
        string $inventoryPrefix = 'INV',
        string $barcodePrefix = 'KAZUTB',
        bool $allocateInventory = true,
        bool $allocateBarcode = true,
    ): array {
        if (! $allocateInventory && ! $allocateBarcode) {
            throw new InvalidArgumentException('At least one number must be allocated.');
        }
        if ($allocateInventory && ! (bool) Setting::valueFor('inventory_numbering_enabled', true)) {
            throw ValidationException::withMessages([
                'inventory_number' => __('operations.messages.inventory_numbering_disabled'),
            ]);
        }
        if ($allocateBarcode && ! (bool) Setting::valueFor('barcode_generation_enabled', true)) {
            throw ValidationException::withMessages([
                'barcode' => __('operations.messages.barcode_generation_disabled'),
            ]);
        }
        if ($year < 1900 || $year > 9999) {
            throw new InvalidArgumentException('Inventory sequence year must be between 1900 and 9999.');
        }

        $scopeKey = $branchId === null ? 'global' : 'branch:'.$branchId;
        $inventoryPrefix = $this->normalizePrefix($inventoryPrefix, 'INV');
        $barcodePrefix = $this->normalizePrefix($barcodePrefix, 'KAZUTB');

        return DB::transaction(function () use (
            $scopeKey,
            $branchId,
            $year,
            $inventoryPrefix,
            $barcodePrefix,
            $allocateInventory,
            $allocateBarcode,
        ): array {
            $now = now('UTC');
            InventorySequence::query()->insertOrIgnore([
                'scope_key' => $scopeKey,
                'branch_id' => $branchId,
                'year' => $year,
                'inventory_prefix' => $inventoryPrefix,
                'barcode_prefix' => $barcodePrefix,
                'last_inventory_number' => 0,
                'last_barcode_number' => 0,
                'created_at' => $now,
                'updated_at' => $now,
            ]);

            /** @var InventorySequence $sequence */
            $sequence = InventorySequence::query()
                ->where('scope_key', $scopeKey)
                ->where('year', $year)
                ->lockForUpdate()
                ->firstOrFail();

            $copyScope = BookCopy::query()
                ->where('inventory_sequence_scope', $scopeKey)
                ->where('inventory_sequence_year', $year);
            $observedInventory = (int) ((clone $copyScope)->max('inventory_sequence_number') ?? 0);
            $observedBarcode = (int) ((clone $copyScope)->max('barcode_sequence_number') ?? 0);

            if ($allocateInventory && $sequence->inventory_prefix !== $inventoryPrefix) {
                if ((int) $sequence->last_inventory_number !== 0 || $observedInventory !== 0) {
                    throw new InvalidArgumentException(
                        "Inventory prefix for {$scopeKey}/{$year} is already fixed by the sequence.",
                    );
                }
                $sequence->inventory_prefix = $inventoryPrefix;
            }
            if ($allocateBarcode && $sequence->barcode_prefix !== $barcodePrefix) {
                if ((int) $sequence->last_barcode_number !== 0 || $observedBarcode !== 0) {
                    throw new InvalidArgumentException(
                        "Barcode prefix for {$scopeKey}/{$year} is already fixed by the sequence.",
                    );
                }
                $sequence->barcode_prefix = $barcodePrefix;
            }

            $inventoryNumeric = null;
            $inventoryNumber = null;
            if ($allocateInventory) {
                $inventoryNumeric = max((int) $sequence->last_inventory_number, $observedInventory) + 1;
                do {
                    $inventoryNumber = sprintf('%s-%04d-%07d', $inventoryPrefix, $year, $inventoryNumeric);
                    $inventoryNumeric++;
                } while (BookCopy::query()->where('inventory_number', $inventoryNumber)->exists());
                $inventoryNumeric--;
                $sequence->last_inventory_number = $inventoryNumeric;
            }

            $barcodeNumeric = null;
            $barcode = null;
            if ($allocateBarcode) {
                $barcodeNumeric = max((int) $sequence->last_barcode_number, $observedBarcode) + 1;
                do {
                    $barcode = sprintf('%s%04d%08d', $barcodePrefix, $year, $barcodeNumeric);
                    $barcodeNumeric++;
                } while (BookCopy::query()->where('barcode', $barcode)->exists());
                $barcodeNumeric--;
                $sequence->last_barcode_number = $barcodeNumeric;
            }

            $sequence->save();

            return [
                'scope_key' => $scopeKey,
                'year' => $year,
                'inventory_sequence_number' => $inventoryNumeric,
                'barcode_sequence_number' => $barcodeNumeric,
                'inventory_number' => $inventoryNumber,
                'barcode' => $barcode,
            ];
        }, 5);
    }

    private function normalizePrefix(string $prefix, string $fallback): string
    {
        $normalized = mb_strtoupper((string) preg_replace('/[^A-Z0-9]/i', '', trim($prefix)));
        $normalized = mb_substr($normalized, 0, 24);

        return $normalized === '' ? $fallback : $normalized;
    }
}
