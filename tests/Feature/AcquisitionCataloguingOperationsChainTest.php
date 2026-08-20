<?php

namespace Tests\Feature;

use App\Exceptions\CirculationException;
use App\Models\AcquisitionOrder;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\CopyHistory;
use App\Models\Catalog\InventorySession;
use App\Models\Catalog\ReaderProfile;
use App\Models\Fund;
use App\Services\Catalog\CopyTransferService;
use App\Services\Catalog\InventoryService;
use App\Services\Catalog\ReservationQueueService;
use App\Services\DataQuality\DataQualityScanner;
use App\Services\Library\LibrarianWorkspaceService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class AcquisitionCataloguingOperationsChainTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
    }

    public function test_order_cataloguing_receipt_and_copy_intake_are_one_traceable_chain(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', config('database.default'));
        $this->assertSame(':memory:', config('database.connections.sqlite.database'));

        $acquisitions = $this->makeControlPlaneUser('acquisitions');
        $cataloguer = $this->makeControlPlaneUser('cataloguer');
        $branch = Branch::query()->where('code', 'SCIENTIFIC-LIBRARY')->firstOrFail();
        $fund = Fund::query()->where('code', 'MAIN')->where('branch_id', $branch->id)->firstOrFail();
        $foreignFund = Fund::query()->where('branch_id', '!=', $branch->id)->firstOrFail();

        $orderCount = AcquisitionOrder::query()->count();
        $this->signInToLibraryAs($acquisitions)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.workspace.orders.store'), [
                'order_number' => 'ISO-ACQ-FALSE-RECEIPT',
                'status' => 'received',
                'currency' => 'KZT',
                'item' => [
                    'title_snapshot' => 'Ложно принятая книга',
                    'quantity_ordered' => 1,
                    'quantity_received' => 1,
                    'unit_price' => 1,
                ],
            ])
            ->assertSessionHasErrors(['status', 'item.quantity_received']);
        $this->assertSame($orderCount, AcquisitionOrder::query()->count());

        $this->signInToLibraryAs($acquisitions)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.workspace.orders.store'), [
                'order_number' => 'ISO-ACQ-2026-001',
                'supplier' => 'Изолированный тестовый поставщик',
                'status' => 'requested',
                'currency' => 'KZT',
                'item' => [
                    'title_snapshot' => 'Қазақ кітапханасындағы деректер сапасы',
                    'quantity_ordered' => 2,
                    'unit_price' => 4750.50,
                ],
            ])->assertRedirect();

        $order = AcquisitionOrder::query()->with('items')->sole();
        $item = $order->items->sole();
        $this->assertSame('9501.00', $order->total_amount);
        $this->assertSame(0, $item->quantity_received);
        $this->signInToLibraryAs($acquisitions)
            ->get(route('librarian.workspace.orders'))
            ->assertOk()
            ->assertSee(route('librarian.workspace.orders.items.receive', [$order, $item]), false)
            ->assertSee('name="received_quantity"', false)
            ->assertSee('name="bibliographic_record_id"', false);
        $this->withoutMiddleware(PreventRequestForgery::class)
            ->patch(route('librarian.workspace.orders.items.receive', [$order, $item]), [
                'received_quantity' => 1,
            ])->assertSessionHasErrors('bibliographic_record_id');
        $this->assertSame(0, $item->fresh()->quantity_received);
        $this->assertSame('requested', $order->fresh()->status);
        $this->assertDatabaseMissing('activity_logs', [
            'action_type' => 'acquisition.received',
            'entity_type' => 'acquisition_order_item',
            'entity_id' => (string) $item->id,
        ]);

        $recordPayload = [
            'title' => 'Қазақ кітапханасындағы деректер сапасы',
            'primary_author' => 'Әбілқасымова Г.Қ.',
            'publisher' => 'Ғылым баспасы',
            'publication_year' => 2026,
            'language' => 'kk',
            'udc_code' => '02:004.6',
            'author_mark' => 'Ә14',
            'annotation' => 'Кітапхана қорындағы деректер сапасын басқару туралы оқу құралы.',
            'isbn' => '978-3-16-148410-0',
            'resource_type' => 'study_guide',
        ];
        $this->signInToLibraryAs($cataloguer)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.catalog.store'), $recordPayload)
            ->assertRedirect();

        $record = BibliographicRecord::query()->where('title', $recordPayload['title'])->sole();
        $this->assertSame('Әбілқасымова Г.Қ.', $record->primary_author);
        $this->assertSame('Ә14', $record->author_mark);
        $this->assertSame('study_guide', $record->resource_type);
        $this->assertFalse($record->is_draft);
        $this->assertDatabaseHas('data_quality_issues', [
            'entity_type' => 'bibliographic_record',
            'entity_id' => (string) $record->id,
            'rule_code' => 'bib.isbn.not_normalized',
            'status' => 'open',
        ]);
        $this->assertDatabaseHas('data_quality_issues', [
            'entity_type' => 'bibliographic_record',
            'entity_id' => (string) $record->id,
            'rule_code' => 'bib.physical.no_copies',
            'status' => 'open',
        ]);

        $this->signInToLibraryAs($cataloguer)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.catalog.store'), [
                ...$recordPayload,
                'title' => 'Басқа атаумен берілген ықтимал дубль',
                'isbn' => '9783161484100',
            ])
            ->assertSessionHas('duplicate_warning');
        $this->assertSame(1, BibliographicRecord::query()->count());

        $this->signInToLibraryAs($cataloguer)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->patch(route('librarian.catalog.update', $record), [
                ...$recordPayload,
                'isbn' => '9783161484100',
                'from' => 'data-quality',
                'save_and_revalidate' => '1',
            ])->assertRedirect();
        $this->assertDatabaseHas('data_quality_issues', [
            'entity_type' => 'bibliographic_record',
            'entity_id' => (string) $record->id,
            'rule_code' => 'bib.isbn.not_normalized',
            'status' => 'resolved',
        ]);

        $this->signInToLibraryAs($acquisitions)
            ->get(route('librarian.copies.create', ['record' => $record->id]))
            ->assertOk()
            ->assertSee('name="bibliographic_record_id"', false)
            ->assertSee('name="fund_id"', false)
            ->assertSee('name="room"', false)
            ->assertSee('name="section"', false)
            ->assertSee('name="shelf_location"', false);

        $this->signInToLibraryAs($acquisitions)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->patch(route('librarian.workspace.orders.items.receive', [$order, $item]), [
                'received_quantity' => 2,
                'bibliographic_record_id' => $record->id,
            ])->assertRedirect();
        $this->assertSame(2, $item->fresh()->quantity_received);
        $this->assertSame($record->id, $item->fresh()->bibliographic_record_id);
        $this->assertSame('received', $order->fresh()->status);
        $this->assertNotNull($order->fresh()->received_at);
        $unrelatedRecord = BibliographicRecord::factory()->create([
            'title' => 'Басқа қабылдау жазбасы',
            'isbn' => null,
        ]);
        $this->patch(route('librarian.workspace.orders.items.receive', [$order, $item]), [
            'received_quantity' => 0,
            'bibliographic_record_id' => $unrelatedRecord->id,
        ])->assertSessionHasErrors('bibliographic_record_id');
        $this->assertSame($record->id, $item->fresh()->bibliographic_record_id);
        $this->assertSame(2, $item->fresh()->quantity_received);
        $this->patch(route('librarian.workspace.orders.items.receive', [$order, $item]), [
            'received_quantity' => 1,
            'bibliographic_record_id' => $record->id,
        ])->assertSessionHasErrors('received_quantity');
        $this->assertSame(2, $item->fresh()->quantity_received);
        $this->assertSame(1, ActivityLog::query()->where('action_type', 'acquisition.received')
            ->where('entity_type', 'acquisition_order_item')->where('entity_id', (string) $item->id)->count());

        $this->post(route('librarian.copies.store'), [
            'bibliographic_record_id' => $record->id,
            'quantity' => 1,
            'inventory_number' => 'ISO-INV-WRONG-FUND',
            'branch_id' => $branch->id,
            'fund_id' => $foreignFund->id,
            'room' => 'Оқу залы 1',
            'section' => 'Қазақ қоры',
            'shelf_location' => 'KZ-01',
            'condition' => 'new',
            'access_restriction' => 'free',
        ])->assertSessionHasErrors('fund_id');
        $this->assertDatabaseMissing('book_copies', ['inventory_number' => 'ISO-INV-WRONG-FUND']);

        $this->signInToLibraryAs($acquisitions)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.copies.store'), [
                'bibliographic_record_id' => $record->id,
                'quantity' => 2,
                'inventory_number' => 'ISO-INV-001',
                'barcode' => 'ISOBC0001',
                'accounting_type' => 'individual',
                'ksu_number' => 'ISO-KSU-1',
                'storage_sigla' => 'ҒҚ',
                'branch_id' => $branch->id,
                'fund_id' => $fund->id,
                'room' => 'Оқу залы 1',
                'section' => 'Қазақ қоры',
                'shelf_location' => 'KZ-01',
                'price' => 4750.50,
                'acquisition_source' => 'purchase',
                'supplier_name' => $order->supplier,
                'acquisition_date' => '2026-08-19',
                'condition' => 'new',
                'access_restriction' => 'free',
            ])->assertRedirect(route('librarian.catalog.edit', $record));

        $copies = BookCopy::query()->where('bibliographic_record_id', $record->id)->orderBy('inventory_number')->get();
        $this->assertSame(['ISO-INV-001', 'ISO-INV-002'], $copies->pluck('inventory_number')->all());
        $this->assertSame(['ISOBC0001', 'ISOBC0002'], $copies->pluck('barcode')->all());
        $this->assertSame(['Оқу залы 1'], $copies->pluck('room')->unique()->values()->all());
        $this->assertSame(['Қазақ қоры'], $copies->pluck('section')->unique()->values()->all());
        $this->assertSame($item->fresh()->quantity_received, $copies->count());
        $this->assertSame(['purchase'], $copies->pluck('acquisition_source')->unique()->values()->all());
        $this->assertSame([$order->supplier], $copies->pluck('supplier_name')->unique()->values()->all());
        $this->assertSame(['4750.50'], $copies->pluck('price')->unique()->values()->all());
        $this->assertSame($order->total_amount, number_format($copies->sum(fn (BookCopy $copy): float => (float) $copy->price), 2, '.', ''));
        $this->assertSame(2, CopyHistory::query()->whereIn('copy_id', $copies->pluck('id'))->where('event_type', 'created')->count());
        $firstIntake = CopyHistory::query()->where('copy_id', $copies->first()->id)->where('event_type', 'created')->sole();
        $this->assertSame('purchase', data_get($firstIntake->details, 'acquisition_source'));
        $this->assertSame($order->supplier, data_get($firstIntake->details, 'supplier_name'));
        $this->assertSame('4750.50', data_get($firstIntake->details, 'price'));
        $this->get(route('librarian.copies.show', $copies->first()))
            ->assertOk()
            ->assertSee('Оқу залы 1')
            ->assertSee('Қазақ қоры')
            ->assertSee('KZ-01');
        $this->assertDatabaseHas('data_quality_issues', [
            'entity_type' => 'bibliographic_record',
            'entity_id' => (string) $record->id,
            'rule_code' => 'bib.physical.no_copies',
            'status' => 'resolved',
        ]);

        $orderAudit = ActivityLog::query()->where('action_type', 'create')
            ->where('entity_type', 'acquisition_order')->where('entity_id', (string) $order->id)->sole();
        $this->assertNull($orderAudit->old_values);
        $this->assertSame('ISO-ACQ-2026-001', data_get($orderAudit->new_values, 'order_number'));
        $this->assertSame($acquisitions->id, $orderAudit->actor_id);

        $receiptAudit = ActivityLog::query()->where('action_type', 'acquisition.received')
            ->where('entity_type', 'acquisition_order_item')->where('entity_id', (string) $item->id)->sole();
        $this->assertSame(0, data_get($receiptAudit->old_values, 'quantity_received'));
        $this->assertSame(2, data_get($receiptAudit->new_values, 'quantity_received'));
        $this->assertSame($record->id, data_get($receiptAudit->new_values, 'bibliographic_record_id'));
        $this->assertSame($acquisitions->id, $receiptAudit->actor_id);

        $metadataCreateAudit = ActivityLog::query()->where('action_type', 'metadata.create')
            ->where('entity_type', 'bibliographic_record')->where('entity_id', (string) $record->id)->sole();
        $this->assertNull($metadataCreateAudit->old_values);
        $this->assertSame($recordPayload['title'], data_get($metadataCreateAudit->new_values, 'title'));
        $this->assertSame($cataloguer->id, $metadataCreateAudit->actor_id);

        $metadataUpdateAudit = ActivityLog::query()->where('action_type', 'metadata.update')
            ->where('entity_type', 'bibliographic_record')->where('entity_id', (string) $record->id)->sole();
        $this->assertSame('978-3-16-148410-0', data_get($metadataUpdateAudit->old_values, 'isbn'));
        $this->assertSame('9783161484100', data_get($metadataUpdateAudit->new_values, 'isbn'));
        $this->assertSame($cataloguer->id, $metadataUpdateAudit->actor_id);

        $copyCreateAudit = ActivityLog::query()->where('action_type', 'copies.create')
            ->where('entity_type', 'book_copy')->where('entity_id', (string) $copies->first()->id)->sole();
        $this->assertNull($copyCreateAudit->old_values);
        $this->assertSame(2, data_get($copyCreateAudit->new_values, 'quantity'));
        $this->assertSame($acquisitions->id, $copyCreateAudit->actor_id);
    }

    public function test_checksum_invalid_isbn_is_opened_for_scoped_data_quality_repair(): void
    {
        $cataloguer = $this->makeControlPlaneUser('cataloguer');
        $payload = [
            'title' => 'Бақылау саны қате ISBN жазбасы',
            'primary_author' => 'Қатені түзетуші А.',
            'publisher' => 'Сынақ баспасы',
            'publication_year' => 2026,
            'language' => 'kk',
            'udc_code' => '02:004',
            'author_mark' => 'Қ41',
            'isbn' => '9783161484101',
            'resource_type' => 'book',
        ];

        $this->signInToLibraryAs($cataloguer)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.catalog.store'), $payload)
            ->assertRedirect();

        $record = BibliographicRecord::query()->where('title', $payload['title'])->sole();
        $this->assertSame('9783161484101', $record->isbn);
        $this->assertDatabaseHas('data_quality_issues', [
            'entity_type' => 'bibliographic_record',
            'entity_id' => (string) $record->id,
            'rule_code' => 'bib.isbn.invalid',
            'status' => 'open',
        ]);

        $this->signInToLibraryAs($cataloguer)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->patch(route('librarian.catalog.update', $record), [
                ...$payload,
                'isbn' => '9783161484100',
                'from' => 'data-quality',
                'save_and_revalidate' => '1',
            ])->assertRedirect();

        $this->assertDatabaseHas('data_quality_issues', [
            'entity_type' => 'bibliographic_record',
            'entity_id' => (string) $record->id,
            'rule_code' => 'bib.isbn.invalid',
            'status' => 'resolved',
        ]);
    }

    public function test_legacy_received_line_can_be_linked_once_without_rewriting_quantity(): void
    {
        $actor = $this->makeControlPlaneUser('acquisitions');
        $record = BibliographicRecord::factory()->create(['title' => 'Legacy receipt repair target']);
        $order = AcquisitionOrder::query()->create([
            'order_number' => 'ISO-LEGACY-RECEIPT-001',
            'status' => 'partially_received',
            'currency' => 'KZT',
            'total_amount' => 2000,
            'created_by' => $actor->id,
        ]);
        $item = $order->items()->create([
            'bibliographic_record_id' => null,
            'title_snapshot' => $record->title,
            'quantity_ordered' => 2,
            'quantity_received' => 1,
            'unit_price' => 1000,
        ]);

        $linked = app(LibrarianWorkspaceService::class)->receiveOrderItem($actor, $order, $item, [
            'received_quantity' => 0,
            'bibliographic_record_id' => $record->id,
        ]);

        $this->assertSame(1, $linked->quantity_received);
        $this->assertSame($record->id, $linked->bibliographic_record_id);
        $audit = ActivityLog::query()->where('action_type', 'acquisition.received')
            ->where('entity_type', 'acquisition_order_item')
            ->where('entity_id', (string) $item->id)
            ->sole();
        $this->assertNull(data_get($audit->old_values, 'bibliographic_record_id'));
        $this->assertSame(1, data_get($audit->old_values, 'quantity_received'));
        $this->assertSame($record->id, data_get($audit->new_values, 'bibliographic_record_id'));
        $this->assertSame(1, data_get($audit->new_values, 'quantity_received'));
        $this->assertSame(0, data_get($audit->new_values, 'received_quantity'));
        $this->assertSame($actor->id, $audit->actor_id);
    }

    public function test_copy_creation_requires_a_real_record_and_persists_complete_location(): void
    {
        $actor = $this->makeControlPlaneUser('acquisitions');
        $before = BookCopy::query()->count();

        $this->signInToLibraryAs($actor)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.copies.store'), [
                'bibliographic_record_id' => 999999,
                'quantity' => 1,
                'inventory_number' => 'ORPHAN-001',
                'condition' => 'new',
                'access_restriction' => 'free',
            ])->assertSessionHasErrors('bibliographic_record_id');

        $this->assertSame($before, BookCopy::query()->count());
    }

    public function test_location_correction_and_inventory_pilot_preserve_missing_as_reviewable(): void
    {
        $branch = Branch::query()->where('code', 'SCIENTIFIC-LIBRARY')->firstOrFail();
        $fund = Fund::query()->where('code', 'MAIN')->where('branch_id', $branch->id)->firstOrFail();
        $foreignFund = Fund::query()->where('branch_id', '!=', $branch->id)->firstOrFail();
        $service = app(InventoryService::class);

        $sessionCount = InventorySession::query()->count();
        $this->signInToLibraryAs($this->adminUser)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.inventory.store'), [
                'branch_id' => $branch->id,
                'fund_id' => $foreignFund->id,
                'room' => 'Қате зал',
                'section' => 'Қате қор',
                'shelf_range' => 'ERR-01',
                'pilot_limit' => 10,
                'inventory_date' => today()->toDateString(),
            ])->assertSessionHasErrors('fund_id');
        $this->assertSame($sessionCount, InventorySession::query()->count());
        try {
            $service->create([
                'branch_id' => $branch->id,
                'fund_id' => $foreignFund->id,
                'shelf_range' => 'ERR-02',
                'pilot_limit' => 10,
                'inventory_date' => today(),
            ], $this->adminUser);
            $this->fail('The domain service must reject a fund from another branch.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('fund_id', $exception->errors());
        }
        $this->assertSame($sessionCount, InventorySession::query()->count());

        $legacy = BookCopy::factory()->create([
            'branch_id' => $branch->id,
            'fund_id' => null,
            'room' => null,
            'section' => null,
            'storage_sigla' => null,
            'shelf_location' => null,
            'barcode' => null,
            'status' => 'available',
        ]);
        app(DataQualityScanner::class)->scanModel($legacy, 'book_copy');
        $this->assertDatabaseHas('data_quality_issues', [
            'entity_type' => 'book_copy', 'entity_id' => (string) $legacy->id,
            'rule_code' => 'copy.location.missing', 'status' => 'open',
        ]);

        $locationSession = $service->start($service->create([
            'branch_id' => $branch->id,
            'fund_id' => $fund->id,
            'room' => 'Зал 2',
            'section' => 'B',
            'shelf_range' => 'B-07',
            'pilot_limit' => 10,
            'inventory_date' => today(),
        ], $this->adminUser), $this->adminUser);
        $service->verifyPhysical($locationSession, $legacy->inventory_number, 'visible', $this->adminUser);
        $locationSession->update(['fund_id' => $foreignFund->id]);
        try {
            $service->confirmLocation($locationSession, $legacy, $this->adminUser, true);
            $this->fail('A legacy cross-branch inventory session must not update a copy location.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('fund_id', $exception->errors());
            $this->assertNull($legacy->fresh()->fund_id);
        }
        $locationSession->update(['fund_id' => $fund->id]);
        try {
            $service->confirmLocation($locationSession, $legacy, $this->adminUser, false);
            $this->fail('A location mismatch must require explicit correction confirmation.');
        } catch (CirculationException) {
            $this->assertNull($legacy->fresh()->fund_id);
        }
        $correction = $service->confirmLocation($locationSession, $legacy, $this->adminUser, true);
        $this->assertTrue($correction['corrected']);
        $this->assertGreaterThanOrEqual(1, $correction['resolved']);
        $this->assertSame('Зал 2', $legacy->fresh()->room);
        $this->assertSame('B', $legacy->fresh()->section);
        $this->assertSame('B-07', $legacy->fresh()->shelf_location);
        $this->assertDatabaseHas('data_quality_issues', [
            'entity_type' => 'book_copy', 'entity_id' => (string) $legacy->id,
            'rule_code' => 'copy.location.missing', 'status' => 'resolved',
        ]);
        $locationAudit = ActivityLog::query()
            ->where('action_type', 'inventory.location_corrected')
            ->where('entity_id', (string) $legacy->id)
            ->sole();
        $this->assertNull(data_get($locationAudit->old_values, 'fund_id'));
        $this->assertSame($fund->id, data_get($locationAudit->new_values, 'fund_id'));
        $qualityAudit = ActivityLog::query()
            ->where('action_type', 'data_quality.issue_resolved')
            ->where('entity_type', 'data_quality_issue')
            ->where('new_values->rule_code', 'copy.location.missing')
            ->sole();
        $this->assertSame('open', data_get($qualityAudit->old_values, 'status'));
        $this->assertSame('resolved', data_get($qualityAudit->new_values, 'status'));
        $service->complete($locationSession, $this->adminUser);
        $service->approve($locationSession->fresh(), $this->adminUser);

        $pilotCopies = collect(range(1, 3))->map(fn (int $index) => BookCopy::factory()->create([
            'branch_id' => $branch->id,
            'fund_id' => $fund->id,
            'room' => 'Зал 3',
            'section' => 'P',
            'shelf_location' => 'P-01',
            'inventory_number' => sprintf('PILOT-CHAIN-%03d', $index),
            'barcode' => null,
            'status' => 'available',
        ]));
        $pilot = $service->start($service->create([
            'branch_id' => $branch->id,
            'fund_id' => $fund->id,
            'room' => 'Зал 3',
            'section' => 'P',
            'shelf_range' => 'P-01',
            'pilot_limit' => 10,
            'inventory_date' => today(),
        ], $this->adminUser), $this->adminUser);

        $this->assertSame(3, $pilot->expected_count);
        $service->verifyPhysical($pilot, $pilotCopies[0]->inventory_number, 'visible', $this->adminUser);
        $mismatch = $service->verifyPhysical($pilot, $pilotCopies[1]->inventory_number, 'mismatch', $this->adminUser, 'OLD-PILOT-002');
        $this->assertSame('requires_review', $mismatch->classification);
        $recheck = $service->verifyPhysical($pilot, $pilotCopies[1]->inventory_number, 'visible', $this->adminUser);
        $this->assertSame('found', $recheck->classification);
        $pilot = $service->complete($pilot, $this->adminUser);

        $this->assertSame(2, $pilot->found_count);
        $this->assertSame(1, $pilot->missing_count);
        $this->assertSame('missing', $pilot->items()->where('copy_id', $pilotCopies[2]->id)->sole()->result);
        $this->assertSame('available', $pilotCopies[2]->fresh()->status);
        $this->assertSame(0, $pilotCopies->filter(fn (BookCopy $copy) => $copy->fresh()->status === 'lost')->count());
        $this->assertSame('approved', $service->approve($pilot, $this->adminUser)->status);
        foreach ([
            ['inventory.started', 'draft', 'running'],
            ['inventory.completed', 'running', 'review'],
            ['inventory.approved', 'review', 'approved'],
        ] as [$action, $oldStatus, $newStatus]) {
            $audit = ActivityLog::query()
                ->where('action_type', $action)
                ->where('entity_type', 'inventory_session')
                ->where('entity_id', (string) $pilot->id)
                ->sole();
            $this->assertSame($oldStatus, data_get($audit->old_values, 'status'));
            $this->assertSame($newStatus, data_get($audit->new_values, 'status'));
            $this->assertSame($this->adminUser->id, $audit->actor_id);
        }
    }

    public function test_location_repair_transfer_return_and_write_off_each_leave_history(): void
    {
        [$source, $destination] = Branch::query()->active()->take(2)->get()->all();
        $sourceFund = Fund::query()->active()->where('branch_id', $source->id)->firstOrFail();
        $destinationFund = Fund::query()->active()->where('branch_id', $destination->id)->firstOrFail();
        $copy = BookCopy::factory()->create([
            'branch_id' => $source->id,
            'fund_id' => $sourceFund->id,
            'storage_sigla' => 'SRC',
            'room' => 'Old room',
            'section' => 'Old section',
            'shelf_location' => 'OLD-01',
            'condition' => 'worn',
            'status' => 'available',
        ]);

        $this->signInToLibraryAs($this->adminUser)
            ->get(route('librarian.copies.edit', $copy))
            ->assertOk()
            ->assertSee('type="hidden" name="status"', false)
            ->assertDontSee('<select class="admin-input" name="status"', false);
        $this->signInToLibraryAs($this->adminUser)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->patch(route('librarian.copies.update', $copy), [
                'inventory_number' => $copy->inventory_number,
                'condition' => $copy->condition,
                'access_restriction' => $copy->access_restriction,
                'status' => 'written_off',
            ])->assertSessionHasErrors('status');
        $this->assertSame('available', $copy->fresh()->status);
        $this->assertDatabaseMissing('copy_history', ['copy_id' => $copy->id, 'event_type' => 'write_off']);

        $this->patch(route('librarian.copies.update', $copy), [
            'inventory_number' => $copy->inventory_number,
            'accounting_type' => $copy->accounting_type,
            'ksu_number' => $copy->ksu_number,
            'storage_sigla' => $copy->storage_sigla,
            'branch_id' => $source->id,
            'fund_id' => $destinationFund->id,
            'room' => $copy->room,
            'section' => $copy->section,
            'shelf_location' => $copy->shelf_location,
            'price' => $copy->price,
            'acquisition_source' => $copy->acquisition_source,
            'supplier_name' => $copy->supplier_name,
            'acquisition_date' => optional($copy->acquisition_date)->format('Y-m-d'),
            'condition' => $copy->condition,
            'access_restriction' => $copy->access_restriction,
            'status' => $copy->status,
        ])->assertSessionHasErrors('fund_id');
        $this->assertSame($sourceFund->id, $copy->fresh()->fund_id);
        $this->assertSame('Old room', $copy->fresh()->room);

        $this->signInToLibraryAs($this->adminUser)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->patch(route('librarian.copies.update', $copy), [
                'inventory_number' => $copy->inventory_number,
                'accounting_type' => $copy->accounting_type,
                'ksu_number' => $copy->ksu_number,
                'storage_sigla' => $copy->storage_sigla,
                'branch_id' => $source->id,
                'fund_id' => $sourceFund->id,
                'room' => 'New room',
                'section' => 'New section',
                'shelf_location' => 'NEW-02',
                'price' => $copy->price,
                'acquisition_source' => $copy->acquisition_source,
                'supplier_name' => $copy->supplier_name,
                'acquisition_date' => optional($copy->acquisition_date)->format('Y-m-d'),
                'condition' => $copy->condition,
                'access_restriction' => $copy->access_restriction,
                'status' => $copy->status,
            ])->assertRedirect();
        $locationHistory = CopyHistory::query()->where('copy_id', $copy->id)->where('event_type', 'location_changed')->sole();
        $this->assertSame('Old room', data_get($locationHistory->details, 'old.room'));
        $this->assertSame('New room', data_get($locationHistory->details, 'new.room'));
        $locationAudit = ActivityLog::query()->where('action_type', 'copies.update')->where('entity_id', (string) $copy->id)->sole();
        $this->assertSame('Old room', data_get($locationAudit->old_values, 'room'));
        $this->assertSame('New room', data_get($locationAudit->new_values, 'room'));

        $this->post(route('librarian.copies.status', $copy), [
            'action' => 'under_repair',
            'comment' => 'Пилотный ремонт переплёта',
        ])->assertRedirect();
        $this->assertSame('under_repair', $copy->fresh()->status);
        $repairHistory = CopyHistory::query()->where('copy_id', $copy->id)->where('event_type', 'repair')->sole();
        $this->assertSame('worn', data_get($repairHistory->details, 'old.condition'));
        $this->assertSame('worn', data_get($repairHistory->details, 'new.condition'));

        $this->post(route('librarian.copies.status', $copy), [
            'action' => 'restore',
            'comment' => 'Ремонт завершён, состояние проверено',
        ])->assertRedirect();
        $this->assertSame('available', $copy->fresh()->status);
        $this->assertSame('good', $copy->fresh()->condition);
        $repairReturnHistory = CopyHistory::query()->where('copy_id', $copy->id)->where('event_type', 'repair_returned')->sole();
        $this->assertSame('worn', data_get($repairReturnHistory->details, 'old.condition'));
        $this->assertSame('good', data_get($repairReturnHistory->details, 'new.condition'));
        $repairReturnAudit = ActivityLog::query()->where('action_type', 'copies.status_change')
            ->where('entity_type', 'book_copy')->where('entity_id', (string) $copy->id)->get()
            ->first(fn (ActivityLog $audit): bool => data_get($audit->old_values, 'status') === 'under_repair');
        $this->assertNotNull($repairReturnAudit);
        $this->assertSame('worn', data_get($repairReturnAudit->old_values, 'condition'));
        $this->assertSame('good', data_get($repairReturnAudit->new_values, 'condition'));

        $reader = $this->makeControlPlaneUser('member');
        ReaderProfile::forUser($reader);
        $reservation = app(ReservationQueueService::class)->create(
            $reader,
            $copy->bibliographicRecord,
            pickupBranchId: $destination->id,
        );
        $transfers = app(CopyTransferService::class);
        $transfer = $transfers->request($reservation, $this->adminUser);
        $transfer = $transfers->approve($transfer, $this->adminUser);
        $transfer = $transfers->send($transfer, $this->adminUser);
        $transfer = $transfers->receive($transfer, $this->adminUser, $copy->barcode, app(ReservationQueueService::class));

        $this->assertSame('received', $transfer->status);
        $receivedCopy = $copy->fresh();
        $this->assertSame($destination->id, $receivedCopy->branch_id);
        foreach (['fund_id', 'storage_sigla', 'room', 'section', 'shelf_location'] as $unverifiedField) {
            $this->assertNull($receivedCopy->{$unverifiedField}, "{$unverifiedField} must await destination verification");
        }
        $this->assertDatabaseHas('data_quality_issues', [
            'entity_type' => 'book_copy', 'entity_id' => (string) $copy->id,
            'rule_code' => 'copy.location.missing', 'status' => 'open',
        ]);
        $transferHistory = CopyHistory::query()->where('copy_id', $copy->id)->where('event_type', 'transfer_received')->sole();
        $this->assertSame($source->id, data_get($transferHistory->details, 'from_branch_id'));
        $this->assertSame($destination->id, data_get($transferHistory->details, 'to_branch_id'));
        $this->assertSame($sourceFund->id, data_get($transferHistory->details, 'old.fund_id'));
        $this->assertNull(data_get($transferHistory->details, 'new.fund_id'));
        $transferAudit = ActivityLog::query()->where('action_type', 'transfer.received')
            ->where('entity_type', 'copy_transfer')->where('entity_id', (string) $transfer->id)->sole();
        $this->assertSame($source->id, data_get($transferAudit->old_values, 'branch_id'));
        $this->assertSame($destination->id, data_get($transferAudit->new_values, 'branch_id'));
        $this->assertSame($sourceFund->id, data_get($transferAudit->old_values, 'fund_id'));
        $this->assertNull(data_get($transferAudit->new_values, 'fund_id'));
        $transferRequestedAudit = ActivityLog::query()->where('action_type', 'transfer.requested')
            ->where('entity_type', 'copy_transfer')->where('entity_id', (string) $transfer->id)->sole();
        $this->assertNull($transferRequestedAudit->old_values);
        $this->assertSame($source->id, data_get($transferRequestedAudit->new_values, 'source_branch_id'));
        $this->assertSame($destination->id, data_get($transferRequestedAudit->new_values, 'destination_branch_id'));
        $this->assertSame($this->adminUser->id, $transferRequestedAudit->actor_id);
        foreach ([
            ['transfer.approved', 'requested', 'approved'],
            ['transfer.sent', 'approved', 'in_transit'],
        ] as [$action, $oldStatus, $newStatus]) {
            $audit = ActivityLog::query()->where('action_type', $action)
                ->where('entity_type', 'copy_transfer')->where('entity_id', (string) $transfer->id)->sole();
            $this->assertSame($oldStatus, data_get($audit->old_values, 'status'));
            $this->assertSame($newStatus, data_get($audit->new_values, 'status'));
        }

        $destinationInventory = app(InventoryService::class);
        $destinationSession = $destinationInventory->start($destinationInventory->create([
            'branch_id' => $destination->id,
            'fund_id' => $destinationFund->id,
            'room' => 'Destination room',
            'section' => 'DST',
            'shelf_range' => 'DST-01',
            'pilot_limit' => 10,
            'inventory_date' => today(),
        ], $this->adminUser), $this->adminUser);
        $destinationScan = $destinationInventory->verifyPhysical(
            $destinationSession,
            $copy->inventory_number,
            'visible',
            $this->adminUser,
        );
        $this->assertSame('misplaced', $destinationScan->classification);
        $destinationCorrection = $destinationInventory->confirmLocation(
            $destinationSession,
            $copy,
            $this->adminUser,
            true,
        );
        $this->assertTrue($destinationCorrection['corrected']);
        $this->assertGreaterThanOrEqual(1, $destinationCorrection['resolved']);
        $this->assertSame($destinationFund->id, $copy->fresh()->fund_id);
        $this->assertSame('Destination room', $copy->fresh()->room);
        $this->assertSame('DST', $copy->fresh()->section);
        $this->assertSame('DST-01', $copy->fresh()->shelf_location);
        $this->assertDatabaseHas('data_quality_issues', [
            'entity_type' => 'book_copy', 'entity_id' => (string) $copy->id,
            'rule_code' => 'copy.location.missing', 'status' => 'resolved',
        ]);
        $destinationLocationHistory = CopyHistory::query()->where('copy_id', $copy->id)
            ->where('event_type', 'physical_location_corrected')->sole();
        $this->assertNull(data_get($destinationLocationHistory->details, 'old.fund_id'));
        $this->assertSame($destinationFund->id, data_get($destinationLocationHistory->details, 'new.fund_id'));

        $withdrawn = BookCopy::factory()->create(['status' => 'available']);
        $this->post(route('librarian.copies.status', $withdrawn), [
            'action' => 'write_off',
            'comment' => 'Физический износ, восстановление невозможно',
        ])->assertRedirect();
        $this->assertSame('written_off', $withdrawn->fresh()->status);
        $writeOffHistory = CopyHistory::query()->where('copy_id', $withdrawn->id)->where('event_type', 'write_off')->sole();
        $this->assertSame('available', data_get($writeOffHistory->details, 'old.status'));
        $this->assertSame('written_off', data_get($writeOffHistory->details, 'new.status'));
        $this->assertStringContainsString('Физический износ', (string) data_get($writeOffHistory->details, 'new.defect_description'));
        $writeOffAudit = ActivityLog::query()->where('action_type', 'copies.status_change')
            ->where('entity_type', 'book_copy')->where('entity_id', (string) $withdrawn->id)->sole();
        $this->assertSame('available', data_get($writeOffAudit->old_values, 'status'));
        $this->assertSame('written_off', data_get($writeOffAudit->new_values, 'status'));
        $this->assertSame('Физический износ, восстановление невозможно', $writeOffAudit->reason);
        $this->assertSame($this->adminUser->id, $writeOffAudit->actor_id);
    }
}
