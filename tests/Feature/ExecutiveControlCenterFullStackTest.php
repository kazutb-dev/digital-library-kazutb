<?php

namespace Tests\Feature;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\Loan;
use App\Models\User;
use App\Services\Reports\DirectorAnalyticsService;
use Illuminate\Support\Carbon;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class ExecutiveControlCenterFullStackTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        Carbon::setTestNow(Carbon::parse('2026-08-13 12:00:00', 'Asia/Almaty'));
        config(['demo_users.enabled' => true]);
        $this->setUpAdminControlPlane();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_global_periods_and_previous_period_comparison_drive_real_kpis(): void
    {
        $reader = User::factory()->create(['is_active' => true]);
        $copy = BookCopy::factory()->create(['registration_date' => now()->toDateString(), 'status' => 'issued']);
        Loan::factory()->create(['user_id' => $reader->getKey(), 'copy_id' => $copy->getKey(), 'issued_at' => now()->subHour(), 'due_at' => now()->subDay(), 'status' => 'overdue']);
        Loan::factory()->create(['user_id' => $reader->getKey(), 'copy_id' => BookCopy::factory(), 'issued_at' => now()->subMonth(), 'status' => 'returned', 'returned_at' => now()->subMonth()->addDay()]);

        $analytics = app(DirectorAnalyticsService::class);
        foreach (['today', 'week', 'month', 'quarter', 'year'] as $period) {
            $result = $analytics->build(['period' => $period, 'compare' => true]);
            $this->assertSame($period, $result['period']['key']);
            $this->assertArrayHasKey('issued_month', $result['comparison']);
        }
        $custom = $analytics->build(['period' => 'custom', 'from' => '2026-08-13', 'to' => '2026-08-13']);
        $this->assertSame(1, $custom['cards']['issued_month']);
        $this->assertSame('2026-08-13', $custom['period']['from']);
        $this->assertSame('2026-08-13', $custom['period']['to']);

        foreach ($custom['alerts'] as $alert) {
            $this->assertContains($alert['severity'], ['info', 'warning', 'high', 'critical']);
            $this->assertSame(1, $alert['threshold']);
            $this->assertNotSame('', $alert['recommendation']);
        }
    }

    public function test_dashboard_is_localized_privacy_safe_and_honest_about_finance(): void
    {
        $director = $this->makeControlPlaneUser('director');
        $reader = User::factory()->create(['name' => 'PRIVATE READING HISTORY', 'is_active' => true]);
        $record = BibliographicRecord::factory()->create(['title' => 'Aggregate-only title']);
        $copy = BookCopy::factory()->create(['bibliographic_record_id' => $record->getKey(), 'status' => 'issued']);
        Loan::factory()->create(['user_id' => $reader->getKey(), 'copy_id' => $copy->getKey(), 'status' => 'overdue', 'issued_at' => now()->subDays(20), 'due_at' => now()->subDays(5)]);

        foreach (['kk', 'ru', 'en'] as $locale) {
            $this->signInToLibraryAs($director)
                ->withSession(['locale' => $locale])
                ->get(route('librarian.overview', ['lang' => $locale, 'period' => 'month', 'compare' => 1]))
                ->assertOk()
                ->assertSee(__('librarian.overview.director.title', locale: $locale))
                ->assertSee(__('librarian.overview.director.budget_unavailable', locale: $locale))
                ->assertSee('data-section="director-executive-dashboard"', false)
                ->assertDontSee('PRIVATE READING HISTORY');
        }
    }

    public function test_director_can_acknowledge_and_assign_alert_while_admin_cannot_inherit_authority(): void
    {
        $director = $this->makeControlPlaneUser('director');
        $assignee = $this->makeControlPlaneUser('librarian');
        $scope = hash('sha256', 'test-scope');

        $this->signInToLibraryAs($director)->post(route('librarian.executive.alerts.acknowledge'), [
            'alert_key' => 'overdue', 'scope_hash' => $scope,
        ])->assertRedirect();
        $this->assertDatabaseHas('executive_alert_acknowledgements', ['alert_key' => 'overdue', 'scope_hash' => $scope, 'acknowledged_by' => $director->getKey()]);

        $this->signInToLibraryAs($director)->post(route('librarian.executive.alerts.assign'), [
            'alert_key' => 'overdue', 'assigned_to' => $assignee->getKey(), 'due_at' => now()->addWeek()->toDateString(), 'priority' => 'high',
        ])->assertRedirect();
        $this->assertDatabaseHas('library_tasks', ['related_entity_type' => 'executive_alert', 'related_entity_id' => 'overdue', 'assigned_to' => $assignee->getKey()]);

        $admin = $this->adminUser;
        $this->signInToLibraryAs($admin)->post(route('librarian.executive.alerts.acknowledge'), [
            'alert_key' => 'overdue', 'scope_hash' => $scope,
        ])->assertForbidden();
    }

    public function test_executive_exports_are_real_private_files_in_all_required_formats(): void
    {
        $director = $this->makeControlPlaneUser('director');
        foreach ([
            'csv' => 'text/csv; charset=UTF-8',
            'pdf' => 'application/pdf',
            'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        ] as $format => $mime) {
            $response = $this->signInToLibraryAs($director)->get(route('librarian.executive.export', ['format' => $format, 'period' => 'month']));
            $response->assertOk()->assertHeader('content-type', $mime);
            $this->assertStringContainsString('private', (string) $response->headers->get('cache-control'));
            $this->assertStringContainsString('no-store', (string) $response->headers->get('cache-control'));
            $content = $response->streamedContent();
            $this->assertNotSame('', $content);
            if ($format === 'pdf') {
                $this->assertStringStartsWith('%PDF-', $content);
            }
            if (in_array($format, ['xlsx', 'docx'], true)) {
                $this->assertStringStartsWith('PK', $content);
            }
        }
    }
}
