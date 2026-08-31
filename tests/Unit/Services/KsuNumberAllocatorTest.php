<?php

namespace Tests\Unit\Services;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Ksu\KsuBook;
use App\Models\Ksu\KsuEntry;
use App\Models\Ksu\KsuSequence;
use App\Services\Operations\InventoryNumberAllocator;
use App\Services\Operations\KsuNumberAllocator;
use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsAcquisitionOperations;
use Tests\TestCase;

class KsuNumberAllocatorTest extends TestCase
{
    use BuildsAcquisitionOperations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAcquisitionOperations();
    }

    public function test_next_number_uses_numeric_authoritative_maximum_for_the_requested_year(): void
    {
        $book = $this->book();
        KsuSequence::query()->create([
            'ksu_book_id' => $book->getKey(),
            'year' => 2026,
            'last_number' => 4,
            'allocation_enabled' => true,
        ]);
        KsuEntry::query()->create([
            'ksu_book_id' => $book->getKey(),
            'entry_number' => 'legacy-label-that-does-not-sort',
            'number' => 9,
            'year' => 2026,
            'status' => 'legacy',
        ]);
        KsuEntry::query()->create([
            'ksu_book_id' => $book->getKey(),
            'entry_number' => '99/2025',
            'number' => 99,
            'year' => 2025,
            'status' => 'legacy',
        ]);

        $allocated = app(KsuNumberAllocator::class)->allocate($book, 2026);

        $this->assertSame(10, $allocated['number']);
        $this->assertSame(2026, $allocated['year']);
        $this->assertSame('10/2026', $allocated['entry_number']);
        $this->assertDatabaseHas('ksu_sequences', [
            'ksu_book_id' => $book->getKey(),
            'year' => 2026,
            'last_number' => 10,
        ]);
    }

    public function test_inventory_allocator_skips_legacy_string_collisions_and_tracks_numeric_values(): void
    {
        $record = BibliographicRecord::query()->create(['title' => 'Collision record']);
        BookCopy::query()->forceCreate([
            'bibliographic_record_id' => $record->getKey(),
            'inventory_number' => 'INV-2026-0000001',
            'barcode' => 'KAZUTB202600000001',
        ]);

        $allocated = app(InventoryNumberAllocator::class)->allocate(null, 2026);

        $this->assertSame(2, $allocated['inventory_sequence_number']);
        $this->assertSame(2, $allocated['barcode_sequence_number']);
        $this->assertSame('INV-2026-0000002', $allocated['inventory_number']);
        $this->assertSame('KAZUTB202600000002', $allocated['barcode']);
    }

    public function test_inventory_and_barcode_counters_can_be_allocated_independently(): void
    {
        $allocator = app(InventoryNumberAllocator::class);

        $inventory = $allocator->allocateSelected(null, 2026, allocateInventory: true, allocateBarcode: false);
        $barcode = $allocator->allocateSelected(null, 2026, allocateInventory: false, allocateBarcode: true);

        $this->assertSame('INV-2026-0000001', $inventory['inventory_number']);
        $this->assertNull($inventory['barcode']);
        $this->assertNull($inventory['barcode_sequence_number']);
        $this->assertNull($barcode['inventory_number']);
        $this->assertNull($barcode['inventory_sequence_number']);
        $this->assertSame('KAZUTB202600000001', $barcode['barcode']);
        $this->assertDatabaseHas('inventory_sequences', [
            'scope_key' => 'global',
            'year' => 2026,
            'last_inventory_number' => 1,
            'last_barcode_number' => 1,
        ]);
    }

    public function test_additive_migration_rolls_back_on_sqlite(): void
    {
        $migration = require base_path('database/migrations/2026_08_29_120000_create_acquisition_batches_and_safe_number_sequences.php');

        $migration->down();

        $this->assertFalse(Schema::hasTable('acquisition_batches'));
        $this->assertFalse(Schema::hasTable('inventory_sequences'));
        $this->assertFalse(Schema::hasTable('legacy_recovery_reviews'));
        $this->assertFalse(Schema::hasColumn('book_copies', 'acquisition_batch_item_id'));
        $this->assertFalse(Schema::hasColumn('book_copies', 'inventory_status'));
        $this->assertTrue(Schema::hasTable('legacy_marc_records'));
    }

    private function book(): KsuBook
    {
        return KsuBook::query()->create([
            'code' => 'KSU-1',
            'name' => 'KSU Part 1',
            'auto_numbering_enabled' => true,
            'requires_manual_decision' => false,
            'is_active' => true,
        ]);
    }
}
