<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\DigitalMaterialAccessLog;
use App\Models\Catalog\ElectronicMaterial;
use App\Models\Catalog\LibraryVisit;
use App\Models\Catalog\Loan;
use App\Models\Catalog\ReaderProfile;
use App\Models\ExternalResource;
use App\Models\ExternalResourceEvent;
use App\Models\Fund;
use App\Models\User;
use App\Services\Reports\LibraryReportService;
use App\Services\Reports\ReportFilters;
use App\Services\Reports\ReportLimitExceeded;
use App\Services\Reports\ReportRegistry;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;
use ZipArchive;

class LibraryReportsFullStackTest extends TestCase
{
    use BuildsAdminControlPlane;

    private User $librarian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        $this->librarian = $this->makeControlPlaneUser('librarian');
    }

    public function test_screen_exposes_four_reports_and_the_complete_filter_contract(): void
    {
        $response = $this->signInToLibraryAs($this->librarian)
            ->get(route('librarian.reports.index', ['report' => 'users', 'preset' => 'year']));

        $response->assertOk();
        foreach (['acquisitions', 'fund-usage', 'users', 'electronic-resources'] as $type) {
            $response->assertSee(__('analytics.reports.'.$type.'.short'));
        }
        foreach (['preset', 'date_from', 'date_to', 'branch_id', 'fund_id', 'resource_type', 'user_segment', 'language', 'udc', 'status', 'subject', 'access_type', 'operation', 'acquisition_source'] as $filter) {
            $response->assertSee('name="'.$filter.'"', false);
        }
        foreach (ReportRegistry::OPERATIONAL_CODES as $type) {
            if (app(ReportRegistry::class)->allows($this->librarian, $type)) {
                $response->assertSee('data-report-code="'.$type.'"', false);
            }
        }

        $director = $this->makeControlPlaneUser('director');
        $directorScreen = $this->signInToLibraryAs($director)
            ->get(route('librarian.reports.index', ['report' => 'users', 'preset' => 'year']))
            ->assertOk();
        $registry = app(ReportRegistry::class);
        $this->assertCount(
            count(ReportRegistry::OFFICIAL_CODES) + count(ReportRegistry::OPERATIONAL_CODES),
            $registry->codes(),
        );
        $this->assertCount(22, ReportRegistry::OPERATIONAL_CODES);
        foreach ($registry->all() as $definition) {
            $this->assertNotEmpty($definition->columns);
            $this->assertNotEmpty($definition->defaultSort);
            $this->assertNotEmpty($definition->totals);
            $this->assertNotEmpty($definition->charts);
            $this->assertContains($definition->sensitivityClass, ['internal', 'restricted_aggregate']);
        }
        foreach ($registry->codes() as $type) {
            $this->assertTrue($registry->allows($director, $type), "Director must be allowed to view {$type}.");
            $directorScreen->assertSee('data-report-code="'.$type.'"', false);
        }
    }

    public function test_all_four_reports_read_canonical_operational_fixtures(): void
    {
        $fixture = $this->fixture();
        $query = ['preset' => 'custom', 'from' => now()->subDay()->toDateString(), 'to' => now()->addDay()->toDateString()];

        $this->signInToLibraryAs($this->librarian)
            ->get(route('librarian.reports.index', $query + ['report' => 'acquisitions']))
            ->assertOk()->assertSee('1 234,5')->assertSee(__('analytics.sources.purchase'))
            ->assertSee(__('analytics.columns.supplier'))->assertSee(__('analytics.columns.ksu_number'));

        $this->signInToLibraryAs($this->librarian)
            ->get(route('librarian.reports.index', $query + ['report' => 'fund-usage']))
            ->assertOk()->assertSee($fixture['fund']->name)->assertSee(__('analytics.metrics.issued'))
            ->assertSee(__('analytics.metrics.renewals'))->assertSee(__('analytics.metrics.reservations'));

        $this->signInToLibraryAs($this->librarian)
            ->get(route('librarian.reports.index', $query + ['report' => 'users']))
            ->assertOk()->assertSee(__('analytics.segments.student'))->assertSee(__('analytics.metrics.visits'));
        $userDataset = app(LibraryReportService::class)->dataset(
            'users',
            ReportFilters::fromRequest(Request::create('/reports', 'GET', $query)),
        );
        $activeMetric = collect($userDataset['metrics'])->firstWhere('key', 'active_users');
        $this->assertSame(
            (int) collect($userDataset['rows'])->sum('active_users'),
            (int) data_get($activeMetric, 'value'),
        );

        $this->signInToLibraryAs($this->librarian)
            ->get(route('librarian.reports.index', $query + ['report' => 'electronic-resources']))
            ->assertOk()->assertSee('Digital fixture')->assertSee('External fixture')->assertSee(__('analytics.metrics.downloads'));
    }

    public function test_exports_are_real_csv_pdf_xlsx_and_docx_and_are_audited(): void
    {
        $this->fixture();
        $query = ['preset' => 'custom', 'from' => now()->subDay()->toDateString(), 'to' => now()->addDay()->toDateString()];

        $csv = $this->signInToLibraryAs($this->librarian)->get(route('librarian.reports.export', $query + ['type' => 'acquisitions', 'format' => 'csv']));
        $csv->assertOk()->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
        $this->assertStringContainsString('1234.5', $csv->streamedContent());

        $pdf = $this->signInToLibraryAs($this->librarian)->get(route('librarian.reports.export', $query + ['type' => 'acquisitions', 'format' => 'pdf']));
        $pdf->assertOk();
        $this->assertStringStartsWith('%PDF', $pdf->getContent());

        foreach (['xlsx' => 'xl/worksheets/sheet1.xml', 'docx' => 'word/document.xml'] as $format => $entry) {
            $response = $this->signInToLibraryAs($this->librarian)->get(route('librarian.reports.export', $query + ['type' => 'acquisitions', 'format' => $format]));
            $response->assertOk();
            $path = $response->baseResponse->getFile()->getPathname();
            $zip = new ZipArchive;
            $this->assertTrue($zip->open($path) === true);
            $this->assertNotFalse($zip->locateName('[Content_Types].xml'));
            $this->assertNotFalse($zip->locateName($entry));
            $zip->close();
            @unlink($path);
        }

        $this->assertSame(4, ActivityLog::query()->where('entity_id', 'librarian:acquisitions')->where('action_type', 'export')->count());
    }

    public function test_print_invalid_paths_and_permissions_are_enforced(): void
    {
        $query = ['preset' => 'day'];

        $this->signInToLibraryAs($this->librarian)
            ->get(route('librarian.reports.print', $query + ['type' => 'users']))
            ->assertOk()->assertSee('<!doctype html>', false)->assertSee(__('analytics.reports.users.title'));

        $this->signInToLibraryAs($this->librarian)->get('/librarian/reports/not-a-report/export/csv')->assertNotFound();
        $this->signInToLibraryAs($this->librarian)->get('/librarian/reports/users/export/json')->assertNotFound();
        $this->signInToLibraryAs($this->librarian)->get('/librarian/reports?report=not-a-report')->assertRedirect();

        $member = $this->makeControlPlaneUser('member');
        $this->signInToLibraryAs($member)->get('/librarian/reports')->assertForbidden();
        $this->signInToLibraryAs($member)->get('/librarian/reports/users/export/csv')->assertForbidden();
    }

    public function test_presets_use_almaty_business_days_and_utc_sql_boundaries(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-12 01:15:00', 'Asia/Almaty'));
        config(['app.library_timezone' => 'Asia/Almaty']);

        $filters = ReportFilters::fromRequest(Request::create('/reports', 'GET', ['preset' => 'day']));

        $this->assertSame('2026-08-11 19:00:00', $filters->from->format('Y-m-d H:i:s'));
        $this->assertSame('UTC', $filters->from->timezoneName);
        $this->assertSame('2026-08-12', $filters->toArray()['from']);
        Carbon::setTestNow();
    }

    public function test_previous_period_presets_use_closed_almaty_business_intervals(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-12 01:15:00', 'Asia/Almaty'));
        config(['app.library_timezone' => 'Asia/Almaty']);

        $yesterday = ReportFilters::fromRequest(Request::create('/reports', 'GET', ['preset' => 'yesterday']));
        $this->assertSame('2026-08-10 19:00:00', $yesterday->from->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-11 18:59:59', $yesterday->to->format('Y-m-d H:i:s'));

        $week = ReportFilters::fromRequest(Request::create('/reports', 'GET', ['preset' => 'previous_week']));
        $this->assertSame('2026-08-02 19:00:00', $week->from->format('Y-m-d H:i:s'));
        $this->assertSame('2026-08-09 18:59:59', $week->to->format('Y-m-d H:i:s'));

        $month = ReportFilters::fromRequest(Request::create('/reports', 'GET', ['preset' => 'previous_month']));
        $this->assertSame('2026-06-30 19:00:00', $month->from->format('Y-m-d H:i:s'));
        $this->assertSame('2026-07-31 18:59:59', $month->to->format('Y-m-d H:i:s'));
        Carbon::setTestNow();
    }

    public function test_specialist_report_permissions_are_least_privilege(): void
    {
        $acquisitions = $this->makeControlPlaneUser('acquisitions');
        $this->assertTrue($acquisitions->can('reports.view_acquisitions'));
        $this->assertTrue($acquisitions->can('reports.export'));
        $this->assertFalse($acquisitions->can('reports.view_ops'));

        $this->signInToLibraryAs($acquisitions)
            ->get(route('librarian.reports.index'))
            ->assertOk()
            ->assertSee('data-report-code="acquisitions"', false)
            ->assertDontSee('data-report-code="users"', false)
            ->assertDontSee('data-report-code="loans"', false)
            ->assertDontSee('attendance-check-title', false)
            ->assertDontSee('/reports/visits/export/', false);
        $this->signInToLibraryAs($acquisitions)
            ->get(route('librarian.reports.index', ['report' => 'users', 'preset' => 'day']))
            ->assertForbidden();
        $this->signInToLibraryAs($acquisitions)
            ->get(route('librarian.reports.export', ['type' => 'acquisitions', 'format' => 'csv', 'preset' => 'day']))
            ->assertOk();
        $this->signInToLibraryAs($acquisitions)
            ->get(route('librarian.reports.export', ['type' => 'fines', 'format' => 'csv', 'preset' => 'day']))
            ->assertForbidden();
        foreach (['popular', 'dynamics', 'udc-fund', 'circulation'] as $legacyType) {
            $this->signInToLibraryAs($acquisitions)
                ->get(route('librarian.reports.export', ['type' => $legacyType, 'format' => 'csv', 'preset' => 'day']))
                ->assertForbidden();
        }

        $cataloguer = $this->makeControlPlaneUser('cataloguer');
        $this->signInToLibraryAs($cataloguer)
            ->get(route('librarian.reports.index'))
            ->assertOk()
            ->assertSee('data-report-code="data-quality"', false)
            ->assertDontSee('data-report-code="fines"', false);
        $this->signInToLibraryAs($cataloguer)
            ->get(route('librarian.reports.index', ['report' => 'fines', 'preset' => 'day']))
            ->assertForbidden();
        $this->signInToLibraryAs($cataloguer)
            ->get(route('librarian.reports.index', ['report' => 'data-quality', 'preset' => 'day']))
            ->assertOk();
        $this->signInToLibraryAs($cataloguer)
            ->get(route('librarian.reports.export', ['type' => 'popular', 'format' => 'csv', 'preset' => 'day']))
            ->assertForbidden();
    }

    public function test_operational_screen_supports_sorting_pagination_and_localized_statuses(): void
    {
        foreach (['active', 'returned', 'overdue'] as $status) {
            Loan::factory()->create(['status' => $status, 'issued_at' => now()]);
        }
        foreach (range(1, 30) as $number) {
            Loan::factory()->create(['status' => 'test_status_'.$number, 'issued_at' => now()]);
        }
        $response = $this->signInToLibraryAs($this->librarian)
            ->get(route('librarian.reports.index', [
                'report' => 'loans', 'preset' => 'day', 'sort' => 'total',
                'direction' => 'desc', 'per_page' => 25,
            ]));

        $response->assertOk()
            ->assertSee('data-report-pagination', false)
            ->assertSee('aria-sort="descending"', false)
            ->assertSee('data-report-total', false);
        foreach (['active', 'returned', 'overdue'] as $status) {
            $response->assertSee(__("analytics.statuses.{$status}"));
        }
    }

    public function test_optional_event_tables_can_be_absent_without_taking_reports_down(): void
    {
        Schema::dropIfExists('external_resource_events');
        Schema::dropIfExists('digital_material_access_logs');

        $this->signInToLibraryAs($this->librarian)
            ->get(route('librarian.reports.index', ['report' => 'electronic-resources', 'preset' => 'day']))
            ->assertOk()
            ->assertSee(__('analytics.reports.electronic-resources.title'))
            ->assertSee(__('analytics.empty'));
    }

    public function test_validated_filters_affect_canonical_data_and_reject_injection(): void
    {
        $fixture = $this->fixture();
        $branchId = Branch::query()->firstOrFail()->getKey();
        $fundId = $fixture['fund']->getKey();
        $matching = ReportFilters::fromRequest(Request::create('/reports', 'GET', [
            'preset' => 'custom', 'from' => now()->subDay()->toDateString(), 'to' => now()->addDay()->toDateString(),
            'branch_id' => $branchId, 'fund_id' => $fundId, 'resource_type' => 'book',
            'language' => 'ru', 'udc' => '004', 'status' => 'issued', 'acquisition_source' => 'purchase',
        ]));
        $data = app(LibraryReportService::class)->dataset('acquisitions', $matching);
        $this->assertSame(1, data_get(collect($data['metrics'])->firstWhere('key', 'copies'), 'value'));

        $missing = ReportFilters::fromRequest(Request::create('/reports', 'GET', [
            'preset' => 'day', 'branch_id' => 999999, 'fund_id' => $fundId,
        ]));
        $empty = app(LibraryReportService::class)->dataset('acquisitions', $missing);
        $this->assertSame(0, data_get(collect($empty['metrics'])->firstWhere('key', 'copies'), 'value'));

        $this->expectException(ValidationException::class);
        ReportFilters::fromRequest(Request::create('/reports', 'GET', [
            'preset' => 'day', 'resource_type' => "book') OR 1=1 --",
        ]));
    }

    public function test_live_report_row_limit_fails_loudly_instead_of_truncating(): void
    {
        config(['library.reports.max_live_rows' => 100]);
        foreach (range(1, 101) as $number) {
            Loan::factory()->create(['status' => 'limit_status_'.$number, 'issued_at' => now()]);
        }

        $this->expectException(ReportLimitExceeded::class);
        app(LibraryReportService::class)->dataset(
            'loans',
            ReportFilters::fromRequest(Request::create('/reports', 'GET', ['preset' => 'day'])),
        );
    }

    /** @return array{fund: Fund} */
    private function fixture(): array
    {
        $branch = Branch::query()->firstOrFail();
        $fund = Fund::query()->firstOrFail();
        $reader = $this->makeControlPlaneUser('member');
        ReaderProfile::forUser($reader)->update(['category' => 'student', 'preferred_branch_id' => $branch->getKey()]);
        $record = BibliographicRecord::factory()->create(['title' => 'Canonical fixture', 'resource_type' => 'book', 'language' => 'ru', 'udc_code' => '004']);
        $copy = BookCopy::factory()->create([
            'bibliographic_record_id' => $record->getKey(), 'branch_id' => $branch->getKey(), 'fund_id' => $fund->getKey(),
            'price' => 1234.50, 'acquisition_source' => 'purchase', 'supplier_name' => 'Canonical supplier',
            'ksu_number' => 'KSU-2026-1', 'registration_date' => today(), 'status' => 'issued',
        ]);
        Loan::factory()->create(['user_id' => $reader->getKey(), 'copy_id' => $copy->getKey(), 'issued_at' => now(), 'status' => 'active', 'renewal_count' => 2]);
        LibraryVisit::query()->create(['user_id' => $reader->getKey(), 'branch_id' => $branch->getKey(), 'scanned_at' => now(), 'source' => 'desk']);

        $material = ElectronicMaterial::query()->create([
            'bibliographic_record_id' => $record->getKey(), 'title' => 'Digital fixture', 'file_type' => 'pdf', 'material_type' => 'book_pdf',
            'language' => 'ru', 'access_level' => 'authenticated', 'allow_download' => true, 'is_active' => true,
            'workflow_status' => 'published', 'download_policy' => 'allowed',
        ]);
        DigitalMaterialAccessLog::query()->create(['electronic_material_id' => $material->getKey(), 'user_id' => $reader->getKey(), 'action' => 'download', 'allowed' => true, 'created_at' => now()]);

        $external = ExternalResource::query()->create([
            'slug' => 'external-fixture-'.str()->random(6), 'title' => 'External fixture', 'resource_type' => 'licensed', 'description' => 'Test',
            'available_roles' => ['member'], 'is_active' => true, 'url' => 'https://example.test', 'access_type' => 'remote_auth',
            'category' => 'research_database', 'publication_status' => 'published',
        ]);
        ExternalResourceEvent::query()->create(['external_resource_id' => $external->getKey(), 'user_id' => $reader->getKey(), 'event_type' => 'outbound_click', 'role_name' => 'student', 'created_at' => now()]);

        return ['fund' => $fund];
    }
}
