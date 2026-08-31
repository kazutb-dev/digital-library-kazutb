<?php

namespace Tests\Unit\Models;

use App\Models\Catalog\BookCopy;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class BookCopySeparatedEligibilityTest extends TestCase
{
    #[DataProvider('issueEligibilityStates')]
    public function test_issue_eligibility_requires_compatible_and_separated_states(
        string $status,
        ?string $inventoryStatus,
        ?string $circulationStatus,
        bool $expected,
    ): void {
        $copy = new BookCopy([
            'status' => $status,
            'inventory_status' => $inventoryStatus,
            'circulation_status' => $circulationStatus,
        ]);

        $eligible = in_array($copy->status, BookCopy::ISSUABLE_STATUSES, true)
            && $copy->isCirculatable();

        self::assertSame($expected, $eligible);
    }

    /** @return iterable<string, array{string, ?string, ?string, bool}> */
    public static function issueEligibilityStates(): iterable
    {
        yield 'legacy available row falls back to compatibility status' => ['available', null, null, true];
        yield 'available in both state machines' => ['available', 'active', 'available', true];
        yield 'ready reserved copy is potentially issuable' => ['reserved', 'active', 'reserved', true];
        yield 'written-off inventory blocks misleading available status' => ['available', 'written_off', 'available', false];
        yield 'damaged inventory blocks misleading available status' => ['available', 'damaged', 'available', false];
        yield 'open-loan circulation blocks misleading available status' => ['available', 'active', 'on_loan', false];
        yield 'unavailable circulation blocks misleading available status' => ['available', 'active', 'unavailable', false];
        yield 'compatibility issued status still blocks contradictory available state' => ['issued', 'active', 'available', false];
        yield 'compatibility write-off still blocks contradictory available state' => ['written_off', 'active', 'available', false];
    }

    #[DataProvider('compatibilityMappings')]
    public function test_compatibility_status_maps_to_separate_lifecycle_states(
        string $status,
        string $inventoryStatus,
        string $circulationStatus,
    ): void {
        self::assertSame(
            [$inventoryStatus, $circulationStatus],
            BookCopy::separatedStateFor($status),
        );
    }

    /** @return iterable<string, array{string, string, string}> */
    public static function compatibilityMappings(): iterable
    {
        yield 'available' => ['available', 'active', 'available'];
        yield 'reserved' => ['reserved', 'active', 'reserved'];
        yield 'issued' => ['issued', 'active', 'on_loan'];
        yield 'overdue' => ['overdue', 'active', 'on_loan'];
        yield 'lost' => ['lost', 'lost', 'unavailable'];
        yield 'written off' => ['written_off', 'written_off', 'unavailable'];
        yield 'repair' => ['under_repair', 'repair', 'unavailable'];
        yield 'non-circulating default' => ['in_processing', 'active', 'unavailable'];
    }
}
