<?php

namespace Tests\Feature;

use App\Models\AcquisitionOrder;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\LibraryTask;
use App\Models\PeriodicalIssue;
use App\Models\PeriodicalSubscription;
use App\Services\Reports\ReportRegistry;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class LibrarianWorkspaceFullStackTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
    }

    public function test_registry_exposes_complete_metadata_and_twenty_two_operational_reports(): void
    {
        $registry = app(ReportRegistry::class);
        $this->assertCount(22, ReportRegistry::OPERATIONAL_CODES);
        foreach ($registry->all() as $definition) {
            $this->assertNotEmpty($definition->code);
            $this->assertNotEmpty($definition->dataset);
            $this->assertNotEmpty($definition->columns);
            $this->assertNotEmpty($definition->defaultSort);
            $this->assertNotEmpty($definition->totals);
            $this->assertNotEmpty($definition->charts);
            $this->assertNotEmpty($definition->exports);
            $this->assertNotEmpty($definition->permission);
            $this->assertNotEmpty($definition->sensitivityClass);
        }
    }

    public function test_librarian_creates_and_completes_only_own_task(): void
    {
        $librarian = $this->makeControlPlaneUser('librarian');
        $this->signInToLibraryAs($librarian)->get(route('librarian.workspace.tasks'))
            ->assertOk()
            ->assertSee('data-global-search', false)
            ->assertSee('name="q"', false);
        $this->post(route('librarian.workspace.tasks.store'), ['title' => 'Check reader request', 'type' => 'message', 'priority' => 'high'])->assertRedirect();
        $task = LibraryTask::query()->sole();
        $this->assertSame($librarian->id, $task->assigned_to);
        $this->patch(route('librarian.workspace.tasks.update', $task), ['status' => 'completed'])->assertRedirect();
        $this->assertNotNull($task->fresh()->completed_at);

        $other = $this->makeControlPlaneUser('librarian');
        $this->signInToLibraryAs($other)->patch(route('librarian.workspace.tasks.update', $task), ['status' => 'open'])->assertForbidden();
    }

    public function test_acquisitions_role_creates_real_order_without_catalogue_mutation(): void
    {
        $user = $this->makeControlPlaneUser('acquisitions');
        $record = BibliographicRecord::factory()->create(['title' => 'Existing evidence-backed title']);
        $beforeRecords = BibliographicRecord::query()->count();
        $beforeCopies = BookCopy::query()->count();

        $this->signInToLibraryAs($user)->post(route('librarian.workspace.orders.store'), [
            'order_number' => 'TEST-ORDER-001', 'supplier' => 'Test supplier', 'status' => 'requested', 'currency' => 'KZT',
            'item' => ['bibliographic_record_id' => $record->id, 'title_snapshot' => $record->title, 'quantity_ordered' => 3, 'quantity_received' => 0, 'unit_price' => 1250.50],
        ])->assertRedirect();

        $order = AcquisitionOrder::query()->with('items')->sole();
        $this->assertSame('3751.50', $order->total_amount);
        $this->assertSame(3, $order->items->sole()->quantity_ordered);
        $this->assertSame($beforeRecords, BibliographicRecord::query()->count());
        $this->assertSame($beforeCopies, BookCopy::query()->count());
    }

    public function test_periodical_issue_ledger_is_idempotent_by_subscription_and_number(): void
    {
        $user = $this->makeControlPlaneUser('acquisitions');
        $this->signInToLibraryAs($user)->post(route('librarian.workspace.periodicals.store'), ['title_snapshot' => 'Test journal', 'year' => 2026, 'expected_issues' => 12, 'status' => 'active'])->assertRedirect();
        $subscription = PeriodicalSubscription::query()->sole();
        $payload = ['issue_number' => '№1', 'received_at' => '2026-08-13', 'status' => 'received'];
        $this->post(route('librarian.workspace.periodicals.issues.store', $subscription), $payload)->assertRedirect();
        $this->post(route('librarian.workspace.periodicals.issues.store', $subscription), $payload)->assertRedirect();
        $this->assertSame(1, PeriodicalIssue::query()->count());
    }

    public function test_member_cannot_enter_staff_workspace_and_bibliographer_has_no_circulation_write(): void
    {
        $member = $this->makeControlPlaneUser('member');
        $this->signInToLibraryAs($member)->get(route('librarian.workspace.search'))->assertForbidden();

        $bibliographer = $this->makeControlPlaneUser('bibliographer');
        $this->assertTrue($bibliographer->can('edd.manage'));
        $this->assertFalse($bibliographer->can('circulation.issue'));
        $this->signInToLibraryAs($bibliographer)->get(route('librarian.workspace.edd'))->assertOk();
    }

    public function test_global_search_is_case_insensitive_and_permission_scoped(): void
    {
        $record = BibliographicRecord::factory()->create([
            'title' => 'Қазақ Ғылымы',
            'primary_author' => 'Test Author',
            'isbn' => '978-601-000-000-1',
        ]);
        BookCopy::factory()->create([
            'bibliographic_record_id' => $record->id,
            'inventory_number' => 'INV-SEARCH-001',
            'barcode' => 'BAR-SEARCH-001',
        ]);

        $librarian = $this->makeControlPlaneUser('librarian');
        $this->signInToLibraryAs($librarian)
            ->get(route('librarian.workspace.search', ['q' => 'TEST AUTHOR']))
            ->assertOk()
            ->assertSee('Қазақ Ғылымы');

        $member = $this->makeControlPlaneUser('member');
        $this->signInToLibraryAs($member)
            ->get(route('librarian.workspace.search', ['q' => 'TEST AUTHOR']))
            ->assertForbidden();
    }
}
