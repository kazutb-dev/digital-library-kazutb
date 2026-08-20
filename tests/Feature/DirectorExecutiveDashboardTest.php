<?php

namespace Tests\Feature;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\LibraryVisit;
use App\Models\Catalog\Loan;
use App\Models\DataQualityIssue;
use App\Models\User;
use App\Services\Reports\DirectorAnalyticsService;
use App\Services\Reports\OperationalDashboardService;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class DirectorExecutiveDashboardTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-12 10:00:00', 'Asia/Almaty'));
        config(['demo_users.enabled' => true]);
        $this->setUpAdminControlPlane();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_director_receives_complete_privacy_safe_executive_aggregates(): void
    {
        $director = $this->makeControlPlaneUser('director');
        $reader = User::factory()->create(['name' => 'Sensitive Reader Name', 'is_active' => true]);
        $record = BibliographicRecord::factory()->create([
            'title' => 'Aggregate dashboard title',
            'resource_type' => 'book',
            'udc_code' => '004.9',
        ]);
        $copy = BookCopy::factory()->create([
            'bibliographic_record_id' => $record->getKey(),
            'registration_date' => now()->toDateString(),
            'acquisition_date' => now()->toDateString(),
            'status' => 'issued',
            'issue_count' => 1,
        ]);

        Loan::factory()->create([
            'user_id' => $reader->getKey(),
            'copy_id' => $copy->getKey(),
            'status' => 'active',
            'issued_at' => now()->subHour(),
            'due_at' => now()->addDays(14),
        ]);
        LibraryVisit::query()->create([
            'user_id' => $reader->getKey(),
            'scanned_at' => now()->subMinutes(30),
            'source' => 'desk',
        ]);

        $analytics = app(DirectorAnalyticsService::class)->build();

        $this->assertSame(1, $analytics['cards']['issued_today']);
        $this->assertSame(1, $analytics['cards']['issued_week']);
        $this->assertSame(1, $analytics['cards']['issued_month']);
        $this->assertSame(1, $analytics['cards']['active_readers_month']);
        $this->assertSame(1, $analytics['cards']['visits_month']);
        $this->assertSame(1, collect($analytics['distributions']['fund_types'])->firstWhere('label', 'book')['value']);
        $this->assertSame(1, collect($analytics['distributions']['udc'])->firstWhere('label', '0')['value']);
        $this->assertArrayHasKey('digital', $analytics['trends']);
        $this->assertArrayHasKey('acquisitions', $analytics['trends']);

        $this->signInToLibraryAs($director)
            ->get(route('librarian.overview'))
            ->assertOk()
            ->assertSee('data-section="director-executive-dashboard"', false)
            ->assertSee(__('librarian.overview.director.charts.active_readers'))
            ->assertSee(__('librarian.overview.director.charts.fund_types'))
            ->assertSee(__('librarian.overview.director.charts.message_sla'))
            ->assertDontSee('Sensitive Reader Name');
    }

    public function test_executive_dashboard_is_not_rendered_for_non_director_staff(): void
    {
        $librarian = $this->makeControlPlaneUser('librarian');

        $this->signInToLibraryAs($librarian)
            ->get(route('librarian.overview'))
            ->assertOk()
            ->assertDontSee('data-section="director-executive-dashboard"', false);
    }

    public function test_acquisitions_and_cataloguer_receive_role_specific_aggregate_dashboards(): void
    {
        BookCopy::factory()->create([
            'registration_date' => now()->toDateString(),
            'acquisition_source' => 'purchase',
            'status' => 'in_processing',
        ]);
        BibliographicRecord::factory()->create([
            'is_draft' => true,
            'needs_manual_review' => true,
            'udc_code' => null,
            'resource_type' => 'book',
        ]);

        $acquisitions = $this->makeControlPlaneUser('acquisitions');
        $this->signInToLibraryAs($acquisitions)
            ->get(route('librarian.overview'))
            ->assertOk()
            ->assertSee('data-section="acquisitions-operational-dashboard"', false)
            ->assertSee(__('librarian.overview.roles.acquisitions.cards.received_month'))
            ->assertDontSee('data-section="director-executive-dashboard"', false);

        $cataloguer = $this->makeControlPlaneUser('cataloguer');
        $this->signInToLibraryAs($cataloguer)
            ->get(route('librarian.overview'))
            ->assertOk()
            ->assertSee('data-section="cataloguer-operational-dashboard"', false)
            ->assertSee(__('librarian.overview.roles.cataloguer.cards.without_udc'))
            ->assertDontSee('data-section="director-executive-dashboard"', false);
    }

    public function test_management_dashboards_count_affected_objects_instead_of_findings(): void
    {
        $record = BibliographicRecord::factory()->create();
        $copy = BookCopy::factory()->create(['bibliographic_record_id' => $record->getKey()]);

        foreach ([
            ['bibliographic_record', (string) $record->getKey(), 'high'],
            ['bibliographic_record', (string) $record->getKey(), 'high'],
            ['bibliographic_record', (string) $record->getKey(), 'medium'],
            ['book_copy', (string) $copy->getKey(), 'critical'],
        ] as $index => [$entityType, $entityId, $severity]) {
            DataQualityIssue::query()->create([
                'issue_number' => 'DQ-DASH-'.$index,
                'entity_type' => $entityType,
                'entity_id' => $entityId,
                'rule_code' => 'dashboard.object-count.'.$index,
                'category' => 'required_fields',
                'severity' => $severity,
                'status' => 'open',
                'description' => 'Dashboard object-count fixture',
                'fingerprint' => hash('sha256', 'dashboard-object-count-'.$index),
                'first_detected_at' => now('UTC'),
                'last_detected_at' => now('UTC'),
            ]);
        }

        $director = app(DirectorAnalyticsService::class)->build();
        $operational = app(OperationalDashboardService::class);

        $this->assertSame(1, $director['cards']['data_quality_open']);
        $this->assertSame(1, $operational->build('cataloguer')['cards']['data_quality_open']);
        $this->assertSame(2, $operational->build('senior_librarian')['cards']['quality_issues']);
    }
}
