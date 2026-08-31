<?php

namespace Tests\Feature;

use App\Exceptions\CirculationException;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\ReaderProfile;
use App\Models\Fund;
use App\Services\Catalog\CirculationService;
use App\Services\Catalog\CopyTransferService;
use App\Services\Catalog\InventoryService;
use App\Services\Catalog\MachineCodeService;
use App\Services\Catalog\ReservationQueueService;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class ProductionCirculationPlatformTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
    }

    public function test_code128_and_qr_are_real_local_svg_without_pii(): void
    {
        $codes = app(MachineCodeService::class);
        $barcode = $codes->code128('RDR00001234');
        $qr = $codes->qr('RDR00001234');

        $this->assertStringContainsString('<svg', $barcode);
        $this->assertMatchesRegularExpression('/<(rect|path)\b/', $barcode);
        $this->assertStringContainsString('<svg', $qr);
        $this->assertStringContainsString('<path', $qr);
        $this->assertStringNotContainsString('reader@example', $qr);
        $this->assertStringNotContainsString('full name', mb_strtolower($qr));
    }

    public function test_interbranch_transfer_requires_physical_send_and_scanned_receipt(): void
    {
        [$source, $destination] = Branch::query()->active()->take(2)->get()->all();
        $reader = $this->makeControlPlaneUser('member');
        ReaderProfile::forUser($reader);
        $record = BibliographicRecord::factory()->create();
        $copy = BookCopy::factory()->create(['bibliographic_record_id' => $record->getKey(), 'branch_id' => $source->getKey(), 'status' => 'available']);

        $reservation = app(ReservationQueueService::class)->create($reader, $record, pickupBranchId: $destination->getKey());
        $this->assertSame('confirmed', $reservation->status);

        $transfers = app(CopyTransferService::class);
        $transfer = $transfers->request($reservation, $this->adminUser);
        $this->assertSame('in_transit', $reservation->fresh()->status);
        $transfer = $transfers->approve($transfer, $this->adminUser);
        $transfer = $transfers->send($transfer, $this->adminUser);
        $this->assertNull($reservation->fresh()->expires_at, 'Pickup hold must not start while physically in transit.');
        $transfer = $transfers->receive($transfer, $this->adminUser, $copy->barcode, app(ReservationQueueService::class));

        $this->assertSame('received', $transfer->status);
        $this->assertSame($destination->getKey(), $copy->fresh()->branch_id);
        $this->assertSame('ready_for_pickup', $reservation->fresh()->status);
        $this->assertNotNull($reservation->fresh()->expires_at);
    }

    public function test_inventory_snapshot_classifies_duplicates_unknown_and_missing_without_mutating_copy(): void
    {
        $branch = Branch::query()->active()->firstOrFail();
        $record = BibliographicRecord::factory()->create();
        $found = BookCopy::factory()->create(['bibliographic_record_id' => $record->getKey(), 'branch_id' => $branch->getKey(), 'status' => 'available']);
        $missing = BookCopy::factory()->create(['bibliographic_record_id' => $record->getKey(), 'branch_id' => $branch->getKey(), 'status' => 'available']);
        $inventory = app(InventoryService::class);
        $session = $inventory->create(['branch_id' => $branch->getKey(), 'inventory_date' => today()], $this->adminUser);
        $session = $inventory->start($session, $this->adminUser);

        $this->assertSame(2, $session->expected_count);
        $inventory->scan($session, $found->barcode, $this->adminUser);
        $duplicate = $inventory->scan($session, $found->inventory_number, $this->adminUser);
        $unknown = $inventory->scan($session, 'UNKNOWN-'.str()->random(8), $this->adminUser);
        $session = $inventory->complete($session, $this->adminUser);

        $this->assertTrue($duplicate->is_duplicate);
        $this->assertSame('unknown', $unknown->classification);
        $this->assertSame(1, $session->found_count);
        $this->assertSame(1, $session->missing_count);
        $this->assertSame(1, $session->duplicate_count);
        $this->assertSame('available', $missing->fresh()->status);
        $this->assertSame('review', $session->status);
        $this->assertSame('approved', $inventory->approve($session, $this->adminUser)->status);
    }

    public function test_inventory_rejects_an_identifier_shared_across_barcode_and_inventory_fields(): void
    {
        $branch = Branch::query()->active()->firstOrFail();
        BookCopy::factory()->create([
            'branch_id' => $branch->getKey(),
            'inventory_number' => 'INV-AMBIGUOUS-A',
            'barcode' => 'SHARED-CODE',
        ]);
        BookCopy::factory()->create([
            'branch_id' => $branch->getKey(),
            'inventory_number' => 'SHARED-CODE',
            'barcode' => 'BAR-AMBIGUOUS-B',
        ]);
        $inventory = app(InventoryService::class);
        $session = $inventory->start($inventory->create([
            'branch_id' => $branch->getKey(),
            'inventory_date' => today(),
        ], $this->adminUser), $this->adminUser);

        try {
            $inventory->scan($session, 'SHARED-CODE', $this->adminUser);
            $this->fail('An ambiguous operational code must not select an arbitrary copy.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('code', $exception->errors());
        }

        $this->assertDatabaseCount('inventory_scans', 0);
    }

    public function test_branch_and_nested_fund_inventory_sessions_cannot_run_concurrently(): void
    {
        $branch = Branch::query()->active()->firstOrFail();
        $fund = Fund::query()->firstOrCreate(
            ['branch_id' => $branch->getKey(), 'code' => 'INV-OVERLAP'],
            ['name' => 'Inventory overlap test fund', 'fund_type' => 'main', 'is_active' => true],
        );
        BookCopy::factory()->create([
            'branch_id' => $branch->getKey(),
            'fund_id' => $fund->getKey(),
        ]);
        $inventory = app(InventoryService::class);
        $inventory->start($inventory->create([
            'scope_type' => 'branch',
            'branch_id' => $branch->getKey(),
            'inventory_date' => today(),
        ], $this->adminUser), $this->adminUser);
        $nested = $inventory->create([
            'scope_type' => 'fund',
            'fund_id' => $fund->getKey(),
            'inventory_date' => today(),
        ], $this->adminUser);

        $this->expectException(CirculationException::class);
        $inventory->start($nested, $this->adminUser);
    }

    public function test_manual_due_date_requires_permission_reason_and_is_audited(): void
    {
        $reader = $this->makeControlPlaneUser('member');
        ReaderProfile::forUser($reader);
        $copy = BookCopy::factory()->create(['status' => 'available']);
        $due = now()->addDays(10)->toDateString();

        $loan = app(CirculationService::class)->issue($reader, $copy, $this->adminUser, manualDueAt: $due, dueDateReason: 'Approved academic fieldwork');

        $this->assertSame($due, $loan->due_at->toDateString());
        $this->assertDatabaseHas('activity_logs', ['action_type' => 'circulation.override_due_date', 'entity_id' => (string) $loan->getKey()]);
    }

    public function test_invalid_reservation_transition_is_rejected(): void
    {
        $reader = $this->makeControlPlaneUser('member');
        ReaderProfile::forUser($reader);
        $record = BibliographicRecord::factory()->create();
        BookCopy::factory()->create(['bibliographic_record_id' => $record->getKey(), 'status' => 'available']);
        $reservation = app(ReservationQueueService::class)->create($reader, $record);

        $this->expectException(CirculationException::class);
        app(ReservationQueueService::class)->confirm($reservation, $this->adminUser);
    }

    public function test_fifo_stops_for_staff_resolution_instead_of_skipping_an_inactive_first_reader(): void
    {
        $record = BibliographicRecord::factory()->create();
        $copy = BookCopy::factory()->create(['bibliographic_record_id' => $record->getKey(), 'status' => 'issued']);
        $service = app(ReservationQueueService::class);

        $firstReader = $this->makeControlPlaneUser('member');
        ReaderProfile::forUser($firstReader);
        $first = $service->create($firstReader, $record);
        $secondReader = $this->makeControlPlaneUser('member');
        ReaderProfile::forUser($secondReader);
        $second = $service->create($secondReader, $record);
        $firstReader->update(['is_active' => false]);

        $this->assertTrue($service->offerReturnedCopy($copy, $this->adminUser));
        $this->assertSame('queued', $first->fresh()->status);
        $this->assertTrue($first->fresh()->requires_resolution);
        $this->assertSame('reader_inactive', $first->fresh()->resolution_reason);
        $this->assertSame('queued', $second->fresh()->status);
        $this->assertSame('reserved_stock', $copy->fresh()->status);
        ActivityLog::query()->where('action_type', 'reservation.requires_resolution')->firstOrFail();
    }
}
