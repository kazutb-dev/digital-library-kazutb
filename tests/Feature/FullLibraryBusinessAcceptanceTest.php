<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\InventorySession;
use App\Models\Catalog\Loan;
use App\Models\Catalog\ReaderProfile;
use App\Models\Fund;
use App\Models\Ksu\KsuAuditEvent;
use App\Models\Ksu\KsuBook;
use App\Models\Ksu\KsuEntry;
use App\Models\Operations\AcquisitionBatch;
use App\Services\Catalog\ReservationQueueService;
use App\Services\Library\BookDetailReadService;
use App\Services\Reports\DirectorAnalyticsService;
use App\Services\Reports\LibraryReportService;
use App\Services\Reports\ReportFilters;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

/**
 * Stateful proof of the business acceptance chain from acquisition to audit.
 *
 * The explicit SQLite :memory: control plane and selected additive migrations
 * keep this test physically isolated from the runtime PostgreSQL database.
 */
class FullLibraryBusinessAcceptanceTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-29 12:00:00', 'Asia/Almaty'));
        $this->setUpAdminControlPlane();

        foreach ([
            'database/migrations/2026_08_28_100000_create_marc_recovery_model.php',
            'database/migrations/2026_08_28_100100_extend_catalogue_for_marc_recovery.php',
            'database/migrations/2026_08_29_120000_create_acquisition_batches_and_safe_number_sequences.php',
        ] as $path) {
            (require base_path($path))->up();
        }
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_acquisition_to_ksu_catalogue_circulation_inventory_reports_director_and_audit_is_one_chain(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        $acquisitions = $this->makeControlPlaneUser('acquisitions');
        $cataloguer = $this->makeControlPlaneUser('cataloguer');
        $librarian = $this->makeControlPlaneUser('librarian');
        $senior = $this->makeControlPlaneUser('senior_librarian');
        $director = $this->makeControlPlaneUser('director');
        $firstReader = $this->makeControlPlaneUser('member', ['name' => 'Acceptance Reader One']);
        $secondReader = $this->makeControlPlaneUser('member', ['name' => 'Acceptance Reader Two']);
        $waitingReader = $this->makeControlPlaneUser('member', ['name' => 'Acceptance Waiting Reader']);
        $firstProfile = ReaderProfile::forUser($firstReader);
        ReaderProfile::forUser($secondReader);
        ReaderProfile::forUser($waitingReader);

        $branch = Branch::query()->where('code', 'SCIENTIFIC-LIBRARY')->firstOrFail();
        $fund = Fund::query()->where('branch_id', $branch->getKey())->where('code', 'MAIN')->firstOrFail();
        $ksuBook = KsuBook::query()->create([
            'code' => 'KSU-1',
            'name' => 'KSU Part 1',
            'auto_numbering_enabled' => true,
            'requires_manual_decision' => false,
            'is_active' => true,
        ]);
        KsuEntry::query()->create([
            'ksu_book_id' => $ksuBook->getKey(),
            'entry_number' => '9/2026',
            'number' => 9,
            'year' => 2026,
            'status' => 'legacy',
        ]);

        // The acquisitions role can create a safe draft and return to intake,
        // but cannot assume the cataloguer's edit/review authority.
        $this->signInToLibraryAs($acquisitions)
            ->get(route('librarian.acquisitions.index'))
            ->assertOk()
            ->assertSee(route('librarian.catalog.create', ['return_to' => 'acquisitions']), false);
        $this->get(route('librarian.catalog.create', ['return_to' => 'acquisitions']))
            ->assertOk()
            ->assertSee('name="return_to" value="acquisitions"', false);
        $draftResponse = $this->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.catalog.store'), [
                'return_to' => 'acquisitions',
                'title' => 'Сквозной цикл современной библиотеки',
                'language' => 'kk',
                'resource_type' => 'book',
            ]);

        $record = BibliographicRecord::query()->where('title', 'Сквозной цикл современной библиотеки')->sole();
        $draftResponse->assertRedirect(route('librarian.acquisitions.index', ['record_q' => $record->title]));
        $this->assertTrue($record->is_draft);
        $this->assertSame($acquisitions->getKey(), $record->responsible_librarian_id);
        $this->signInToLibraryAs($acquisitions)
            ->get(route('librarian.catalog.edit', $record))
            ->assertForbidden();

        $this->signInToLibraryAs($acquisitions)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.acquisitions.store'), [
                'batch_number' => 'ACC-2026-001',
                'received_at' => '2026-08-29',
                'acquisition_source' => 'purchase',
                'supplier_name' => 'Acceptance Supplier',
                'currency' => 'KZT',
                'branch_id' => $branch->getKey(),
                'fund_id' => $fund->getKey(),
                'items' => [[
                    'bibliographic_record_id' => $record->getKey(),
                    'quantity' => 2,
                    'unit_price' => '1250.50',
                    'accounting_type' => 'inventory',
                    'condition' => 'new',
                    'access_restriction' => 'free',
                    'storage_sigla' => 'ACC-SIGLA',
                    'service_point_code' => 'ACC-DESK',
                    'room' => 'Acceptance Hall',
                    'section' => 'Acceptance Section',
                    'shelf_location' => 'ACC-01',
                    'shelf_index' => 'UDC-02',
                    'inventory_number_mode' => 'manual_list',
                    'manual_inventory_numbers' => ['ACC-INV-001', 'ACC-INV-002'],
                    'barcode_mode' => 'manual_list',
                    'manual_barcodes' => ['ACCEPTBC001', 'ACCEPTBC002'],
                ]],
            ])
            ->assertRedirect();

        $batch = AcquisitionBatch::query()->where('batch_number', 'ACC-2026-001')->sole();
        $this->assertSame('draft', $batch->status);
        $this->post(route('librarian.acquisitions.confirm', $batch))->assertRedirect();

        $batch = $batch->fresh(['ksuEntry.items.copy']);
        $entry = $batch->ksuEntry;
        $copies = BookCopy::query()
            ->where('acquisition_batch_id', $batch->getKey())
            ->orderBy('inventory_number')
            ->get();
        $this->assertSame('confirmed', $batch->status);
        $this->assertSame('10/2026', $entry->entry_number);
        $this->assertSame('arrival', $entry->operation_type);
        $this->assertSame(2, $entry->copy_count);
        $this->assertSame('2501.00', $entry->total_cost);
        $this->assertCount(2, $copies);
        $this->assertSame(['ACC-INV-001', 'ACC-INV-002'], $copies->pluck('inventory_number')->all());
        $this->assertSame(['ACCEPTBC001', 'ACCEPTBC002'], $copies->pluck('barcode')->all());
        $this->assertSame(['purchase'], $copies->pluck('acquisition_source')->unique()->values()->all());
        $this->assertSame(['1250.50'], $copies->pluck('price')->unique()->values()->all());
        $this->assertSame(['ACC-SIGLA'], $copies->pluck('storage_sigla')->unique()->values()->all());
        $this->assertSame(['ACC-01'], $copies->pluck('shelf_location')->unique()->values()->all());
        $this->assertSame(['active'], $copies->pluck('inventory_status')->unique()->values()->all());
        $this->assertSame(['available'], $copies->pluck('circulation_status')->unique()->values()->all());
        $this->assertSame(2, $entry->items()->where('link_method', 'acquisition_batch')->count());

        // The cataloguer completes the same record; recovered fields then flow
        // to the public read model without granting edit rights to acquisitions.
        $catalogPayload = [
            'title' => $record->title,
            'subtitle' => 'Практическое руководство',
            'primary_author' => 'Қасымова А. А.',
            'publisher' => 'KazUTB Press',
            'publication_place' => 'Астана',
            'publication_year' => 2026,
            'language' => 'kk',
            'udc_code' => '02:004.6',
            'author_mark' => 'Қ25',
            'annotation' => 'Полный путь поступления, выдачи, возврата и учёта фонда.',
            'isbn' => '9783161484100',
            'resource_type' => 'book',
        ];
        $this->signInToLibraryAs($cataloguer)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->patch(route('librarian.catalog.update', $record), $catalogPayload)
            ->assertRedirect();
        $this->assertFalse($record->fresh()->is_draft);
        $detail = app(BookDetailReadService::class)->findByIdentifier((string) $record->getKey());
        $this->assertSame('Астана', $detail['publicationPlace']);
        $this->assertSame('Практическое руководство', $detail['title']['subtitle']);
        $this->get('/book/'.$record->getKey().'?lang=ru')
            ->assertOk()
            ->assertSee((string) json_encode('Астана'), false)
            ->assertSee((string) json_encode('Практическое руководство'), false);

        // The desk resolves canonical reader/copy identifiers, issues both
        // physical copies, and the first reader immediately sees the loan.
        $this->signInToLibraryAs($librarian)
            ->getJson(route('librarian.circulation.reader-lookup', ['q' => $firstProfile->barcode]))
            ->assertOk()
            ->assertJsonPath('data.0.id', $firstReader->getKey());
        foreach ($copies as $copy) {
            foreach ([$copy->inventory_number, $copy->barcode] as $code) {
                $this->getJson(route('librarian.circulation.copy-lookup', ['q' => $code]))
                    ->assertOk()
                    ->assertJsonPath('data.id', $copy->getKey());
            }
        }
        foreach ([
            [$firstReader, $copies[0], $copies[0]->inventory_number],
            [$secondReader, $copies[1], $copies[1]->barcode],
        ] as [$reader, $copy, $code]) {
            $this->withoutMiddleware(PreventRequestForgery::class)
                ->post(route('librarian.circulation.issue.store'), [
                    'reader_id' => $reader->getKey(),
                    'copy_code' => $code,
                ])
                ->assertRedirect();
        }
        $this->assertSame(2, Loan::query()->where('status', 'active')->count());
        $this->assertSame(['issued'], $copies->map(fn (BookCopy $copy): string => $copy->fresh()->status)->unique()->values()->all());
        $this->assertSame(['on_loan'], $copies->map(fn (BookCopy $copy): string => $copy->fresh()->circulation_status)->unique()->values()->all());
        $this->signInToLibraryAs($firstReader)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSee($record->title);

        // With every copy on loan, a new reservation queues. Returning the
        // exact first copy promotes it to the hold shelf instead of availability.
        $reservation = app(ReservationQueueService::class)->create($waitingReader, $record->fresh());
        $this->assertSame('queued', $reservation->status);
        $this->assertNull($reservation->assigned_copy_id);
        $this->signInToLibraryAs($librarian)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.circulation.return.store'), [
                'copy_code' => $copies[0]->barcode,
                'condition_on_return' => 'unchanged',
                'incident' => 'none',
            ])
            ->assertRedirect();
        $this->assertSame('returned', Loan::query()->where('copy_id', $copies[0]->getKey())->sole()->status);
        $this->assertSame('ready_for_pickup', $reservation->fresh()->status);
        $this->assertSame($copies[0]->getKey(), $reservation->fresh()->assigned_copy_id);
        $this->assertSame('reserved', $copies[0]->fresh()->status);
        $this->assertSame('reserved', $copies[0]->fresh()->circulation_status);

        // A leading librarian inventories that very returned/held copy through
        // the production routes and approves the immutable session snapshot.
        $this->signInToLibraryAs($senior)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.inventory.store'), [
                'scope_type' => 'fund',
                'branch_id' => $branch->getKey(),
                'fund_id' => $fund->getKey(),
                'pilot_limit' => 10,
                'inventory_date' => '2026-08-29',
            ])
            ->assertRedirect();
        $inventory = InventorySession::query()->sole();
        $this->post(route('librarian.inventory.start', $inventory))->assertRedirect();
        $this->post(route('librarian.inventory.scan', $inventory), ['code' => $copies[0]->barcode])->assertRedirect();
        $this->assertSame('found', $inventory->scans()->where('copy_id', $copies[0]->getKey())->sole()->classification);
        $this->post(route('librarian.inventory.complete', $inventory))->assertRedirect();
        $this->post(route('librarian.inventory.approve', $inventory))->assertRedirect();
        $this->assertSame('approved', $inventory->fresh()->status);
        $this->assertSame('reserved', $copies[0]->fresh()->status);

        // Accounting forms, operational circulation totals and the director's
        // aggregate dashboard consume the rows created above, not new fixtures.
        $filters = ReportFilters::fromRequest(Request::create('/reports', 'GET', [
            'preset' => 'custom',
            // SQLite stores immutable_date casts with a midnight suffix while
            // PostgreSQL uses a native date. A bounded three-day window keeps
            // this assertion portable without weakening the source linkage.
            'from' => '2026-08-28',
            'to' => '2026-08-30',
            'branch_id' => $branch->getKey(),
            'fund_id' => $fund->getKey(),
        ]));
        $reports = app(LibraryReportService::class);
        $acquisitionAct = $reports->dataset('acquisition-act', $filters);
        $ksuPartOne = $reports->dataset('ksu-part-1', $filters);
        $fundUsage = $reports->dataset('fund-usage', $filters);
        $this->assertSame(1, $this->metric($acquisitionAct, 'batches'));
        $this->assertSame(2, $this->metric($acquisitionAct, 'copies'));
        $this->assertSame(2501.0, (float) $this->metric($acquisitionAct, 'value'));
        $this->assertSame(2, $this->metric($ksuPartOne, 'copies'));
        $this->assertSame(2501.0, (float) $this->metric($ksuPartOne, 'value'));
        $this->assertSame(2, $this->metric($fundUsage, 'issued'));
        $this->assertSame(1, $this->metric($fundUsage, 'returned'));
        $this->assertSame(1, $this->metric($fundUsage, 'reservations'));

        $this->signInToLibraryAs($director)
            ->get(route('librarian.reports.index', [
                'report' => 'acquisition-act',
                'preset' => 'custom',
                'from' => '2026-08-28',
                'to' => '2026-08-30',
            ]))
            ->assertOk()
            ->assertSee('ACC-2026-001');
        $analytics = app(DirectorAnalyticsService::class)->build([
            'period' => 'custom', 'from' => '2026-08-28', 'to' => '2026-08-30',
        ]);
        $this->assertSame(2, $analytics['cards']['arrivals_current_month']);
        $this->assertSame(2, $analytics['cards']['issued_today']);
        $this->assertSame(1, $analytics['cards']['returned_today']);
        $this->assertSame(1, $analytics['cards']['copies_issued']);
        $this->assertSame(1, $analytics['cards']['reservations_ready']);
        $this->assertSame(2, $analytics['cards']['ksu_entries_year']);
        $this->assertSame(2501.0, (float) $analytics['cards']['acquisition_value_period']);
        $this->get(route('librarian.overview', ['lang' => 'ru', 'period' => 'custom', 'from' => '2026-08-28', 'to' => '2026-08-30']))
            ->assertOk()
            ->assertSee('data-section="director-executive-dashboard"', false);

        // Every material transition remains visible through both operational
        // ActivityLog and the KSU ledger audit, including the admin log UI.
        foreach ([
            'metadata.create',
            'acquisition_batch.draft_created',
            'acquisition_batch.confirmed',
            'metadata.update',
            'circulation.issue',
            'reservation.create',
            'reservation.ready',
            'circulation.return',
            'inventory.session_created',
            'inventory.started',
            'inventory.copy_scanned',
            'inventory.completed',
            'inventory.approved',
        ] as $action) {
            $this->assertTrue(ActivityLog::query()->where('action_type', $action)->exists(), "Missing audit action {$action}.");
        }
        $this->assertSame(2, ActivityLog::query()->where('action_type', 'circulation.issue')->count());
        $this->assertSame(2, KsuAuditEvent::query()->where('event_type', 'item.linked')->where('ksu_entry_id', $entry->getKey())->count());
        $this->assertDatabaseHas('ksu_audit_events', [
            'ksu_entry_id' => $entry->getKey(),
            'event_type' => 'entry.created',
        ]);
        $this->signInToLibraryAs($this->adminUser)
            ->get(route('admin.logs.index', ['action_type' => 'acquisition_batch.confirmed']))
            ->assertOk()
            ->assertSee('acquisition_batch.confirmed');
    }

    /** @param array<string,mixed> $dataset */
    private function metric(array $dataset, string $key): int|float
    {
        $value = collect($dataset['metrics'])->firstWhere('key', $key)['value'] ?? null;
        $this->assertIsNumeric($value, $key);

        return $value;
    }
}
