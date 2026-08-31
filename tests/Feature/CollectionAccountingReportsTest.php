<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Fund;
use App\Models\Ksu\KsuBook;
use App\Models\Ksu\KsuConflict;
use App\Models\Ksu\KsuEntry;
use App\Models\Operations\AcquisitionBatch;
use App\Models\User;
use App\Services\Reports\CollectionAccountingReportService;
use App\Services\Reports\DirectorAnalyticsService;
use App\Services\Reports\LibraryReportService;
use App\Services\Reports\OperationalDashboardService;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportLimitExceeded;
use App\Services\Reports\ReportRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsAcquisitionOperations;
use Tests\TestCase;

class CollectionAccountingReportsTest extends TestCase
{
    use BuildsAcquisitionOperations;

    private ReportFilters $filters;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-29 12:00:00', 'Asia/Almaty'));
        $this->setUpAcquisitionOperations();
        $this->seedAccountingFixture();
        $this->filters = ReportFilters::fromRequest(Request::create('/reports', 'GET', [
            'preset' => 'custom',
            'from' => '2026-01-01',
            'to' => '2026-12-31',
        ]));
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_registry_exposes_every_accounting_form_with_localized_columns_and_permissions(): void
    {
        $registry = app(ReportRegistry::class);

        foreach (CollectionAccountingReportService::CODES as $code) {
            $definition = $registry->get($code);
            $this->assertNotEmpty($definition->columns, $code);
            $this->assertNotEmpty($definition->totals, $code);
            $this->assertStringContainsString('reports.view_', $definition->permission, $code);

            foreach (['kk', 'ru', 'en'] as $locale) {
                app()->setLocale($locale);
                $this->assertTrue(trans()->has("analytics.reports.{$code}.title"), "{$locale}:{$code}");
                foreach ($definition->columns as $column) {
                    $this->assertTrue(trans()->has("analytics.columns.{$column}"), "{$locale}:{$code}:{$column}");
                }
            }
        }
    }

    public function test_ksu_parts_register_and_fund_movement_have_reconciled_totals(): void
    {
        $reports = app(LibraryReportService::class);
        $partOne = $reports->dataset('ksu-part-1', $this->filters);
        $partTwo = $reports->dataset('ksu-part-2', $this->filters);
        $partThree = $reports->dataset('ksu-part-3', $this->filters);
        $register = $reports->dataset('ksu-register', $this->filters);

        $this->assertSame(3, $this->metric($partOne, 'copies'));
        $this->assertSame(225.0, $this->metric($partOne, 'value'));
        $this->assertSame(1, $this->metric($partTwo, 'copies'));
        $this->assertSame(75.0, $this->metric($partTwo, 'value'));
        $this->assertSame(3, $this->metric($partThree, 'arrivals_copies'));
        $this->assertSame(1, $this->metric($partThree, 'writeoff_copies'));
        $this->assertSame(2, $this->metric($partThree, 'net_copies'));
        $this->assertSame(150.0, $this->metric($partThree, 'net_value'));
        $this->assertSame(2, $this->metric($register, 'entries'));
        $this->assertSame(1, $this->metric($register, 'conflicts'));
    }

    public function test_acquisition_inventory_facet_and_writeoff_reports_use_real_copy_totals(): void
    {
        $reports = app(LibraryReportService::class);

        $expectations = [
            'acquisition-act' => ['batches' => 1, 'copies' => 3, 'value' => 225.0],
            'inventory-book' => ['copies' => 2, 'value' => 175.0],
            'non-inventory-book' => ['copies' => 1, 'value' => 50.0],
            'new-arrivals' => ['copies' => 3, 'value' => 225.0],
            'acquisitions-by-source-value' => ['copies' => 3, 'value' => 225.0],
            'writeoffs' => ['acts' => 1, 'copies' => 1, 'value' => 75.0],
        ];
        foreach ($expectations as $code => $metrics) {
            $dataset = $reports->dataset($code, $this->filters);
            foreach ($metrics as $key => $expected) {
                $this->assertSame($expected, $this->metric($dataset, $key), "{$code}:{$key}");
            }
        }

        foreach (['fund-by-sigla', 'fund-by-language', 'fund-by-type', 'fund-by-udc'] as $code) {
            $dataset = $reports->dataset($code, $this->filters);
            $this->assertSame(2, $this->metric($dataset, 'copies'), $code);
            $this->assertSame(150.0, $this->metric($dataset, 'value'), $code);
        }

        $sources = $reports->dataset('acquisitions-by-source-value', $this->filters)['rows'];
        $purchase = collect($sources)->firstWhere('acquisition_source', __('analytics.sources.purchase'));
        $this->assertSame(2, $purchase['copies']);
        $this->assertSame(150.0, $purchase['total_value']);
    }

    public function test_dashboard_services_publish_arrival_writeoff_ksu_value_and_facet_kpis(): void
    {
        $operational = app(OperationalDashboardService::class)->build('acquisitions');
        $this->assertSame(3, $operational['cards']['arrivals_current_month']);
        $this->assertSame(225.0, $operational['cards']['acquisition_value_month']);
        $this->assertSame(1, $operational['cards']['writeoffs_year']);
        $this->assertSame(2, $operational['cards']['ksu_entries_year']);
        $this->assertSame(1, $operational['cards']['ksu_conflicts']);
        foreach (['sources', 'value_by_source', 'languages', 'udc', 'sigla'] as $dimension) {
            $this->assertNotEmpty($operational['distributions'][$dimension], $dimension);
        }

        $director = app(DirectorAnalyticsService::class)->build(['period' => 'year']);
        $this->assertSame(3, $director['cards']['arrivals_current_month']);
        $this->assertSame(1, $director['cards']['writeoffs_year']);
        $this->assertSame(2, $director['cards']['ksu_entries_year']);
        $this->assertSame(1, $director['cards']['ksu_conflicts']);
        $this->assertSame(225.0, $director['cards']['acquisition_value_period']);
        foreach (['fund_types', 'languages', 'udc', 'sigla', 'acquisition_sources', 'acquisition_value_by_source'] as $dimension) {
            $this->assertNotEmpty($director['distributions'][$dimension], $dimension);
        }
    }

    public function test_large_copy_reports_probe_the_limit_before_hydration_while_facets_keep_canonical_totals(): void
    {
        config(['library.reports.max_live_rows' => 100]);
        $record = BibliographicRecord::query()->firstOrFail();
        $branch = Branch::query()->firstOrFail();
        $fund = Fund::query()->firstOrFail();
        $timestamp = now('UTC')->toDateTimeString();

        DB::table('book_copies')->insert(collect(range(1, 99))->map(fn (int $number): array => [
            'bibliographic_record_id' => $record->getKey(),
            'inventory_number' => sprintf('LIMIT-%03d', $number),
            'barcode' => sprintf('LIMIT-BC-%03d', $number),
            'accounting_type' => 'inventory',
            'storage_sigla' => 'SIG-A',
            'branch_id' => $branch->getKey(),
            'fund_id' => $fund->getKey(),
            'price' => 1,
            'acquisition_source' => 'purchase',
            'acquisition_date' => '2026-08-29',
            'registration_date' => '2026-08-29',
            'condition' => 'new',
            'status' => 'available',
            'access_restriction' => 'free',
            'issue_count' => 0,
            'created_at' => $timestamp,
            'updated_at' => $timestamp,
        ])->all());

        $queries = [];
        DB::listen(function ($query) use (&$queries): void {
            if (str_contains($query->sql, 'from "book_copies" as "copies"')) {
                $queries[] = $query->sql;
            }
        });

        try {
            app(LibraryReportService::class)->dataset('inventory-book', $this->filters);
            $this->fail('An oversized detail report must fail before hydrating copy rows.');
        } catch (ReportLimitExceeded) {
            $this->assertNotEmpty($queries);
            $this->assertTrue(collect($queries)->contains(
                fn (string $sql): bool => str_contains($sql, 'bounded_report_rows') && str_contains($sql, 'limit 101'),
            ));
            $this->assertFalse(collect($queries)->contains(
                fn (string $sql): bool => str_contains($sql, 'select "copies"."id"'),
            ));
        }

        $queries = [];
        $facet = app(LibraryReportService::class)->dataset('fund-by-sigla', $this->filters);
        $this->assertSame(101, $this->metric($facet, 'copies'));
        $this->assertSame(249.0, $this->metric($facet, 'value'));
        $this->assertSame(1, $this->metric($facet, 'titles'));
        $this->assertCount(1, $facet['rows']);
        $this->assertTrue(collect($queries)->contains(fn (string $sql): bool => str_contains($sql, 'group by')));
        $this->assertFalse(collect($queries)->contains(fn (string $sql): bool => str_contains($sql, 'select "copies"."id"')));
    }

    /** @param array<string,mixed> $dataset */
    private function metric(array $dataset, string $key): int|float
    {
        $value = collect($dataset['metrics'])->firstWhere('key', $key)['value'] ?? null;
        $this->assertIsNumeric($value, $key);

        return $value;
    }

    private function seedAccountingFixture(): void
    {
        $actor = User::query()->create([
            'name' => 'Accounting Reports Operator',
            'email' => 'accounting-reports@example.test',
            'password' => 'test-password',
            'locale' => 'ru',
        ]);
        $branch = Branch::query()->create(['code' => 'MAIN', 'name' => 'Main branch', 'type' => 'library']);
        $fund = Fund::query()->create([
            'branch_id' => $branch->getKey(),
            'code' => 'MAIN-FUND',
            'name' => 'Main fund',
            'fund_type' => 'main',
        ]);
        $record = BibliographicRecord::query()->create([
            'title' => 'Accounting report fixture',
            'primary_author' => 'Fixture Author',
            'language' => 'kk',
            'resource_type' => 'book',
            'udc_code' => '004.9',
        ]);
        $partOne = KsuBook::query()->create(['code' => 'KSU-1', 'name' => 'KSU Part 1']);
        $partTwo = KsuBook::query()->create(['code' => 'KSU-2', 'name' => 'KSU Part 2']);
        $arrival = KsuEntry::query()->create([
            'ksu_book_id' => $partOne->getKey(),
            'entry_number' => '9/2026',
            'number' => 9,
            'year' => 2026,
            'entry_date' => '2026-08-29',
            'operation_type' => 'arrival',
            'acquisition_source' => 'purchase',
            'supplier_name' => 'Fixture Supplier',
            'title_count' => 1,
            'copy_count' => 3,
            'total_cost' => 225,
            'branch_id' => $branch->getKey(),
            'fund_id' => $fund->getKey(),
            'status' => 'posted',
            'created_by' => $actor->getKey(),
        ]);
        KsuEntry::query()->create([
            'ksu_book_id' => $partTwo->getKey(),
            'entry_number' => '1/2026',
            'number' => 1,
            'year' => 2026,
            'entry_date' => '2026-08-29',
            'operation_type' => 'withdrawal',
            'act_number' => 'WO-2026-001',
            'operation_reason' => 'Damaged beyond repair',
            'title_count' => 1,
            'copy_count' => 1,
            'total_cost' => 75,
            'branch_id' => $branch->getKey(),
            'fund_id' => $fund->getKey(),
            'status' => 'posted',
            'created_by' => $actor->getKey(),
        ]);
        KsuConflict::query()->create([
            'ksu_book_id' => $partOne->getKey(),
            'kind' => 'missing_number',
            'ksu_number_raw' => '8/2026',
            'reason' => 'Fixture conflict',
            'status' => 'open',
        ]);
        $batch = AcquisitionBatch::query()->create([
            'batch_number' => 'ACT-2026-001',
            'status' => 'confirmed',
            'received_at' => '2026-08-29',
            'acquisition_source' => 'purchase',
            'supplier_name' => 'Fixture Supplier',
            'currency' => 'KZT',
            'branch_id' => $branch->getKey(),
            'fund_id' => $fund->getKey(),
            'ksu_entry_id' => $arrival->getKey(),
            'title_count' => 1,
            'copy_count' => 3,
            'total_amount' => 225,
            'created_by' => $actor->getKey(),
            'confirmed_by' => $actor->getKey(),
            'confirmed_at' => now('UTC'),
        ]);

        foreach ([
            ['INV-001', 'BC-001', 'inventory', 'purchase', 100, 'active', 'available', null, null, null],
            ['NON-001', 'BC-002', 'non_inventory', 'purchase', 50, 'active', 'available', null, null, null],
            ['INV-002', 'BC-003', 'inventory', 'donation', 75, 'written_off', 'unavailable', '2026-08-29', 'WO-2026-001', 'Damaged beyond repair'],
        ] as [$inventory, $barcode, $accounting, $source, $price, $inventoryStatus, $circulationStatus, $writeoffDate, $writeoffAct, $writeoffReason]) {
            BookCopy::query()->forceCreate([
                'bibliographic_record_id' => $record->getKey(),
                'inventory_number' => $inventory,
                'barcode' => $barcode,
                'accounting_type' => $accounting,
                'ksu_number' => $arrival->entry_number,
                'storage_sigla' => 'SIG-A',
                'sigla_code' => 'SIG-A',
                'branch_id' => $branch->getKey(),
                'fund_id' => $fund->getKey(),
                'price' => $price,
                'acquisition_source' => $source,
                'acquisition_date' => '2026-08-29',
                'registration_date' => '2026-08-29',
                'condition' => 'new',
                'status' => $inventoryStatus === 'written_off' ? 'written_off' : 'available',
                'inventory_status' => $inventoryStatus,
                'circulation_status' => $circulationStatus,
                'access_restriction' => 'free',
                'acquisition_batch_id' => $batch->getKey(),
                'ksu_entry_id' => $arrival->getKey(),
                'writeoff_date' => $writeoffDate,
                'writeoff_act' => $writeoffAct,
                'writeoff_reason' => $writeoffReason,
            ]);
        }
    }
}
