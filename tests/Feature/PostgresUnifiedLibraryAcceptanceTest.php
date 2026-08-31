<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\ReaderProfile;
use App\Models\Fund;
use App\Models\Ksu\KsuBook;
use App\Models\Ksu\KsuEntry;
use App\Models\Operations\AcquisitionBatch;
use App\Models\Setting;
use App\Models\User;
use App\Services\Catalog\CirculationService;
use App\Services\Catalog\CopyWriteOffService;
use App\Services\Catalog\FundMovementService;
use App\Services\Catalog\InventoryService;
use App\Services\Catalog\LibraryVisitService;
use App\Services\Catalog\ReservationQueueService;
use App\Services\Library\BookDetailReadService;
use App\Services\Operations\AcquisitionService;
use App\Services\Reports\DirectorAnalyticsService;
use App\Services\Reports\LibraryReportService;
use App\Services\Reports\ReportFilters;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

/**
 * A real PostgreSQL acceptance chain. The canonical wrapper points this test
 * at a disposable database whose name ends in _test. Every mutation remains
 * under this method's outer transaction and is rolled back before returning.
 */
class PostgresUnifiedLibraryAcceptanceTest extends TestCase
{
    private const PREFIX = 'ACCEPTANCE-20260829-FINALGATE';

    public function test_real_postgresql_business_chain_is_atomic_and_leaves_no_residue(): void
    {
        if (DB::connection()->getDriverName() !== 'pgsql') {
            $this->markTestSkipped('Requires the isolated PostgreSQL acceptance database.');
        }

        $database = (string) DB::selectOne('select current_database() as name')->name;
        $this->assertStringEndsWith('_test', $database);
        $this->assertNotSame('digital_library_recovered', $database);
        $this->assertSame(0, $this->residualCount());

        Carbon::setTestNow(Carbon::parse('2026-08-29 14:00:00', 'Asia/Almaty'));
        DB::beginTransaction();

        try {
            $sql = [];
            DB::listen(static function ($query) use (&$sql): void {
                $sql[] = mb_strtolower((string) $query->sql);
            });
            Setting::query()->updateOrCreate(
                ['key' => 'ksu_numbering_enabled'],
                ['value' => true, 'type' => 'boolean', 'group' => 'library_operations'],
            );
            Setting::query()->updateOrCreate(
                ['key' => 'inventory_numbering_enabled'],
                ['value' => true, 'type' => 'boolean', 'group' => 'library_operations'],
            );

            $operator = $this->user('operator');
            $reader = $this->user('reader');
            ReaderProfile::forUser($reader);
            $sourceBranch = $this->branch('SRC');
            $sourceFund = $this->fund($sourceBranch, 'SRC');
            $destinationBranch = $this->branch('DST');
            $destinationFund = $this->fund($destinationBranch, 'DST');

            $record = BibliographicRecord::query()->create([
                'title' => self::PREFIX.' Catalogue Title',
                'primary_author' => 'Acceptance Author',
                'language' => 'en',
                'resource_type' => 'book',
                'udc_code' => '02:004.6',
                'is_draft' => false,
            ]);
            $book = KsuBook::query()->firstOrCreate(
                ['code' => 'KSU-1'],
                [
                    'name' => 'KSU Part 1',
                    'auto_numbering_enabled' => true,
                    'requires_manual_decision' => false,
                    'is_active' => true,
                ],
            );
            KsuEntry::query()->create([
                'ksu_book_id' => $book->getKey(),
                'entry_number' => '9/2026',
                'number' => 9,
                'year' => 2026,
                'entry_date' => '2026-01-01',
                'status' => 'legacy',
            ]);

            $batch = app(AcquisitionService::class)->createDraft($operator, [
                'batch_number' => self::PREFIX.'-BATCH',
                'received_at' => '2026-08-29',
                'acquisition_source' => 'purchase',
                'supplier_name' => self::PREFIX.' Supplier',
                'currency' => 'KZT',
                'branch_id' => $sourceBranch->getKey(),
                'fund_id' => $sourceFund->getKey(),
                'items' => [[
                    'bibliographic_record_id' => $record->getKey(),
                    'quantity' => 2,
                    'unit_price' => '1500.00',
                    'accounting_type' => 'inventory',
                    'condition' => 'new',
                    'access_restriction' => 'free',
                    'storage_sigla' => self::PREFIX.'-SIGLA',
                    'inventory_number_mode' => 'auto',
                    'inventory_prefix' => 'ACCEPT829',
                    'barcode_mode' => 'auto',
                    'barcode_prefix' => 'ACCEPT829',
                ]],
            ]);
            $batch = app(AcquisitionService::class)->confirm($operator, $batch);
            $copies = BookCopy::query()
                ->where('acquisition_batch_id', $batch->getKey())
                ->orderBy('id')
                ->get();

            $this->assertSame('confirmed', $batch->status);
            $this->assertSame('10/2026', $batch->ksuEntry?->entry_number);
            $this->assertCount(2, $copies);
            $this->assertSame(self::PREFIX.' Catalogue Title', app(BookDetailReadService::class)
                ->findByIdentifier((string) $record->getKey())['title']['display']);

            $reservation = app(ReservationQueueService::class)->create($reader, $record, $operator, $sourceBranch->getKey());
            $this->assertSame('ready_for_pickup', $reservation->status);
            $reservedCopy = BookCopy::query()->findOrFail($reservation->assigned_copy_id);
            $loan = app(CirculationService::class)->issue($reader, $reservedCopy, $operator);
            $this->assertSame('active', $loan->status);
            $this->assertSame('fulfilled', $reservation->fresh()->status);
            $returned = app(CirculationService::class)->returnCopy($reservedCopy, $operator, 'unchanged');
            $this->assertSame('returned', $returned->status);
            $this->assertSame('available', $reservedCopy->fresh()->status);

            $visit = app(LibraryVisitService::class)->record($reader, $sourceBranch->getKey(), $operator);
            $this->assertFalse($visit['duplicate']);

            $inventory = app(InventoryService::class)->create([
                'scope_type' => 'fund',
                'branch_id' => $sourceBranch->getKey(),
                'fund_id' => $sourceFund->getKey(),
                'pilot_limit' => 10,
                'inventory_date' => '2026-08-29',
            ], $operator);
            $inventory = app(InventoryService::class)->start($inventory, $operator);
            app(InventoryService::class)->scan($inventory, (string) $reservedCopy->barcode, $operator);
            $inventory = app(InventoryService::class)->complete($inventory, $operator);
            $inventory = app(InventoryService::class)->approve($inventory, $operator);
            $this->assertSame('approved', $inventory->status);

            app(FundMovementService::class)->move(
                [(string) $reservedCopy->inventory_number],
                [
                    'branch_id' => $destinationBranch->getKey(),
                    'fund_id' => $destinationFund->getKey(),
                    'storage_sigla' => self::PREFIX.'-DST',
                ],
                'Acceptance relocation after physical inventory.',
                $operator,
            );
            $this->assertSame($destinationFund->getKey(), $reservedCopy->fresh()->fund_id);

            $writeoffCopy = $copies->firstWhere('id', '!=', $reservedCopy->getKey()) ?? $copies->last();
            app(CopyWriteOffService::class)->writeOffByCodes(
                [(string) $writeoffCopy->inventory_number],
                '2026-08-29',
                self::PREFIX.'-ACT',
                'Acceptance write-off after commission review.',
                $operator,
            );
            $this->assertSame('written_off', $writeoffCopy->fresh()->status);

            $filters = ReportFilters::fromRequest(Request::create('/reports', 'GET', [
                'preset' => 'custom',
                'from' => '2026-01-01',
                'to' => '2026-12-31',
            ]));
            $reports = app(LibraryReportService::class);
            $this->assertSame(2, $this->metric($reports->dataset('acquisition-act', $filters), 'copies'));
            $this->assertSame(2, $this->metric($reports->dataset('ksu-part-1', $filters), 'copies'));
            $this->assertSame(1, $this->metric($reports->dataset('ksu-part-2', $filters), 'copies'));
            $this->assertSame(1, $this->metric($reports->dataset('writeoffs', $filters), 'copies'));

            $analytics = app(DirectorAnalyticsService::class)->build([
                'period' => 'custom',
                'from' => '2026-08-29',
                'to' => '2026-08-29',
            ]);
            $this->assertGreaterThanOrEqual(2, $analytics['cards']['arrivals_current_month']);
            $this->assertGreaterThanOrEqual(1, $analytics['cards']['writeoffs_year']);
            $this->assertGreaterThanOrEqual(1, $analytics['cards']['visits_month']);

            foreach ([
                'acquisition_batch.confirmed', 'reservation.create', 'circulation.issue',
                'circulation.return', 'visit.record', 'inventory.started',
                'inventory.approved', 'copies.movement', 'ksu.withdrawal.created',
            ] as $action) {
                $this->assertTrue(
                    ActivityLog::query()->where('action_type', $action)->exists(),
                    "Missing acceptance audit action {$action}.",
                );
            }
            $this->assertTrue(collect($sql)->contains(
                fn (string $query): bool => str_contains($query, 'from "users"') && str_contains($query, 'for update'),
            ), 'Reader-wide loan/reservation mutex did not execute.');
            $this->assertTrue(collect($sql)->contains(
                fn (string $query): bool => str_contains($query, 'from "ksu_sequences"') && str_contains($query, 'for update'),
            ), 'KSU sequence row was not locked.');
            $this->assertTrue(collect($sql)->contains(
                fn (string $query): bool => str_contains($query, 'from "inventory_sequences"') && str_contains($query, 'for update'),
            ), 'Inventory/barcode sequence row was not locked.');
            $this->assertTrue(collect($sql)->contains(
                fn (string $query): bool => str_contains($query, 'pg_advisory_xact_lock'),
            ), 'Inventory start overlap gate did not execute.');
            $this->assertGreaterThan(0, $this->residualCount());
        } finally {
            DB::rollBack();
            Carbon::setTestNow();
        }

        $this->assertSame(0, $this->residualCount());
    }

    private function user(string $suffix): User
    {
        return User::query()->create([
            'name' => self::PREFIX.' '.$suffix,
            'email' => strtolower(self::PREFIX).'.'.$suffix.'@example.test',
            'password' => 'acceptance-test-password',
            'locale' => 'en',
            'is_active' => true,
        ]);
    }

    private function branch(string $suffix): Branch
    {
        return Branch::query()->create([
            'code' => 'ACC-'.$suffix.'-829',
            'name' => self::PREFIX.' '.$suffix.' Branch',
            'type' => 'library',
            'is_active' => true,
        ]);
    }

    private function fund(Branch $branch, string $suffix): Fund
    {
        return Fund::query()->create([
            'branch_id' => $branch->getKey(),
            'code' => 'ACC-'.$suffix.'-FUND-829',
            'name' => self::PREFIX.' '.$suffix.' Fund',
            'fund_type' => 'main',
            'institutional_scope' => 'general',
            'is_active' => true,
        ]);
    }

    /** @param array<string,mixed> $dataset */
    private function metric(array $dataset, string $key): int|float
    {
        $value = collect($dataset['metrics'])->firstWhere('key', $key)['value'] ?? null;
        $this->assertIsNumeric($value, $key);

        return $value;
    }

    private function residualCount(): int
    {
        return (int) AcquisitionBatch::query()->where('batch_number', 'like', self::PREFIX.'%')->count()
            + (int) BibliographicRecord::query()->where('title', 'like', self::PREFIX.'%')->count()
            + (int) BookCopy::query()->where('inventory_number', 'like', 'ACCEPT829%')->count()
            + (int) User::query()->where('email', 'like', strtolower(self::PREFIX).'%')->count();
    }
}
