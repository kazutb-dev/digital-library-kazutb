<?php

namespace Tests\Feature;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsCopyLifecycleOperations;
use Tests\TestCase;

class BookCopySeparatedEligibilityTest extends TestCase
{
    use BuildsCopyLifecycleOperations;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCopyLifecycleOperations();
    }

    public function test_available_scope_honours_both_lifecycle_columns_and_legacy_null_fallback(): void
    {
        $record = BibliographicRecord::query()->create(['title' => 'Separated eligibility record']);
        $eligible = $this->copy($record, 1, 'available', 'active', 'available');
        $legacy = $this->copy($record, 2, 'available', 'active', 'available');
        $writtenOff = $this->copy($record, 3, 'available', 'written_off', 'available');
        $onLoan = $this->copy($record, 4, 'available', 'active', 'on_loan');
        $reserved = $this->copy($record, 5, 'reserved', 'active', 'reserved');

        // Simulate a pre-migration row. The compatibility status remains the
        // fallback until a lifecycle mutation backfills both new columns.
        DB::table('book_copies')->where('id', $legacy->getKey())->update([
            'inventory_status' => null,
            'circulation_status' => null,
        ]);

        $ids = BookCopy::query()->availableForCirculation()->orderBy('id')->pluck('id')->all();

        $this->assertSame([$eligible->getKey(), $legacy->getKey()], $ids);
        $this->assertNotContains($writtenOff->getKey(), $ids);
        $this->assertNotContains($onLoan->getKey(), $ids);
        $this->assertNotContains($reserved->getKey(), $ids);
    }

    private function copy(
        BibliographicRecord $record,
        int $sequence,
        string $status,
        string $inventoryStatus,
        string $circulationStatus,
    ): BookCopy {
        return BookCopy::query()->create([
            'bibliographic_record_id' => $record->getKey(),
            'inventory_number' => 'ELIGIBILITY-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'status' => $status,
            'inventory_status' => $inventoryStatus,
            'circulation_status' => $circulationStatus,
            'condition' => 'good',
            'access_restriction' => 'free',
        ]);
    }
}
