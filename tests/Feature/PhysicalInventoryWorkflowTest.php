<?php

namespace Tests\Feature;

use App\Exceptions\CirculationException;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\CopyHistory;
use App\Services\Catalog\InventoryService;
use App\Services\DataQuality\DataQualityScanner;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class PhysicalInventoryWorkflowTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
    }

    public function test_visible_inventory_confirms_physical_presence_without_changing_copy_status(): void
    {
        $branch = Branch::query()->active()->firstOrFail();
        $copy = BookCopy::factory()->create([
            'branch_id' => $branch->id, 'fund_id' => null, 'room' => 'Hall 1',
            'section' => 'A', 'shelf_location' => 'A-01', 'status' => 'available', 'barcode' => null,
        ]);
        $service = app(InventoryService::class);
        $session = $service->create([
            'branch_id' => $branch->id, 'room' => 'Hall 1', 'section' => 'A',
            'shelf_range' => 'A-01', 'pilot_limit' => 10, 'inventory_date' => today(),
        ], $this->adminUser);
        $session = $service->start($session, $this->adminUser);

        $scan = $service->verifyPhysical($session, $copy->inventory_number, 'visible', $this->adminUser);
        $location = $service->confirmLocation($session, $copy, $this->adminUser, false);

        $this->assertSame('found', $scan->classification);
        $this->assertSame('available', $copy->fresh()->status);
        $this->assertFalse($location['corrected']);
        $this->assertDatabaseHas('copy_history', ['copy_id' => $copy->id, 'event_type' => 'physical_presence_confirmed']);
        $this->assertDatabaseHas('activity_logs', ['entity_id' => (string) $copy->id, 'action_type' => 'inventory.location_confirmed']);
    }

    public function test_not_found_and_inventory_mismatch_never_mark_copy_lost_or_change_inventory_number(): void
    {
        $branch = Branch::query()->active()->firstOrFail();
        $copy = BookCopy::factory()->create(['branch_id' => $branch->id, 'shelf_location' => 'B-01', 'status' => 'available']);
        $service = app(InventoryService::class);
        $session = $service->start($service->create([
            'branch_id' => $branch->id, 'shelf_range' => 'B-01', 'pilot_limit' => 10, 'inventory_date' => today(),
        ], $this->adminUser), $this->adminUser);

        $scan = $service->verifyPhysical($session, $copy->inventory_number, 'mismatch', $this->adminUser, 'OLD-123');
        $session = $service->complete($session, $this->adminUser);

        $this->assertSame('requires_review', $scan->classification);
        $this->assertSame('available', $copy->fresh()->status);
        $this->assertSame($copy->inventory_number, $copy->fresh()->inventory_number);
        $this->assertSame(0, CopyHistory::query()->where('copy_id', $copy->id)->where('event_type', 'physical_presence_confirmed')->count());
        $this->assertSame('review', $session->status);
    }

    public function test_isbn_cannot_identify_a_physical_copy_and_creates_nothing(): void
    {
        $branch = Branch::query()->active()->firstOrFail();
        $copy = BookCopy::factory()->create(['branch_id' => $branch->id, 'shelf_location' => 'C-01']);
        $service = app(InventoryService::class);
        $session = $service->start($service->create([
            'branch_id' => $branch->id, 'shelf_range' => 'C-01', 'pilot_limit' => 10, 'inventory_date' => today(),
        ], $this->adminUser), $this->adminUser);
        $before = BookCopy::query()->count();

        try {
            $service->verifyPhysical($session, (string) $copy->bibliographicRecord->isbn, 'visible', $this->adminUser);
            $this->fail('ISBN must not identify a physical copy.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('inventory_number', $exception->errors());
        }

        $this->assertSame($before, BookCopy::query()->count());
    }

    public function test_location_mismatch_requires_explicit_authorized_correction_and_scoped_revalidation(): void
    {
        $branch = Branch::query()->active()->firstOrFail();
        $copy = BookCopy::factory()->create(['branch_id' => $branch->id, 'fund_id' => null, 'room' => null, 'section' => null, 'shelf_location' => null]);
        app(DataQualityScanner::class)->scanModel($copy, 'book_copy');
        $service = app(InventoryService::class);
        $session = $service->start($service->create([
            'branch_id' => $branch->id, 'room' => 'Hall 2', 'section' => 'B',
            'shelf_range' => 'B-07', 'pilot_limit' => 10, 'inventory_date' => today(),
        ], $this->adminUser), $this->adminUser);
        $service->verifyPhysical($session, $copy->inventory_number, 'visible', $this->adminUser);

        $this->expectException(CirculationException::class);
        try {
            $service->confirmLocation($session, $copy, $this->adminUser, false);
        } finally {
            $this->assertNull($copy->fresh()->shelf_location);
        }
    }

    public function test_confirmed_location_correction_is_audited(): void
    {
        $branch = Branch::query()->active()->firstOrFail();
        $copy = BookCopy::factory()->create(['branch_id' => $branch->id, 'fund_id' => null, 'storage_sigla' => null, 'shelf_location' => null]);
        app(DataQualityScanner::class)->scanModel($copy, 'book_copy');
        $service = app(InventoryService::class);
        $session = $service->start($service->create([
            'branch_id' => $branch->id, 'room' => 'Hall 3', 'section' => 'C',
            'shelf_range' => 'C-04', 'pilot_limit' => 10, 'inventory_date' => today(),
        ], $this->adminUser), $this->adminUser);
        $service->verifyPhysical($session, $copy->inventory_number, 'visible', $this->adminUser);

        $result = $service->confirmLocation($session, $copy, $this->adminUser, true);

        $this->assertTrue($result['corrected']);
        // A valid branch-backed placement no longer creates a synthetic
        // copy.location.missing issue, so correction can legitimately resolve
        // zero DQ rows. The invariant is that no actionable issue remains.
        $this->assertSame(0, $result['remaining']);
        $this->assertSame('C-04', $copy->fresh()->shelf_location);
        ActivityLog::query()->where('action_type', 'inventory.location_corrected')->where('entity_id', (string) $copy->id)->firstOrFail();
    }

    public function test_reader_and_director_cannot_verify_physical_inventory(): void
    {
        $branch = Branch::query()->active()->firstOrFail();
        $copy = BookCopy::factory()->create(['branch_id' => $branch->id, 'shelf_location' => 'D-01', 'barcode' => null]);
        $service = app(InventoryService::class);
        $session = $service->start($service->create([
            'branch_id' => $branch->id, 'shelf_range' => 'D-01', 'pilot_limit' => 10, 'inventory_date' => today(),
        ], $this->adminUser), $this->adminUser);

        foreach (['member', 'director'] as $role) {
            $user = $this->makeControlPlaneUser($role);
            $this->signInToLibraryAs($user)->withoutMiddleware(PreventRequestForgery::class)
                ->post(route('librarian.inventory.verify', $session), [
                    'inventory_number' => $copy->inventory_number, 'inventory_condition' => 'visible',
                ])->assertForbidden();
        }

        $this->assertSame('missing', $session->items()->where('copy_id', $copy->id)->firstOrFail()->result);
    }

    public function test_pilot_snapshot_is_limited_and_sorted_by_physical_zone(): void
    {
        $branch = Branch::query()->active()->firstOrFail();
        foreach (range(1, 12) as $index) {
            BookCopy::factory()->create([
                'branch_id' => $branch->id, 'shelf_location' => 'P-01',
                'inventory_number' => sprintf('PILOT-%03d', 13 - $index), 'barcode' => null,
            ]);
        }
        $service = app(InventoryService::class);
        $session = $service->start($service->create([
            'branch_id' => $branch->id, 'shelf_range' => 'P-01', 'pilot_limit' => 10, 'inventory_date' => today(),
        ], $this->adminUser), $this->adminUser);

        $this->assertSame(10, $session->expected_count);
        $this->assertSame('PILOT-001', $session->items()->with('copy')->get()->pluck('copy.inventory_number')->sort()->first());
    }

    public function test_inventory_screen_exposes_compact_location_profile_and_pilot_controls_to_creator(): void
    {
        $staff = $this->makeControlPlaneUser('senior_librarian');
        $staff->update(['locale' => 'ru']);

        $this->signInToLibraryAs($staff)->get(route('librarian.inventory.index', ['lang' => 'ru']))
            ->assertOk()
            ->assertSee('Проблемы размещения фонда')
            ->assertSee('Нет фонда')
            ->assertSee('Размер pilot')
            ->assertDontSee('librarian.inventory.');
    }

    public function test_inventory_screen_hides_session_creation_without_create_permission(): void
    {
        $staff = $this->makeControlPlaneUser('librarian');

        $this->signInToLibraryAs($staff)->get(route('librarian.inventory.index', ['lang' => 'ru']))
            ->assertOk()
            ->assertSee('Проблемы размещения фонда')
            ->assertDontSee('Размер pilot')
            ->assertDontSee(__('librarian.inventory.new_session'));
    }
}
