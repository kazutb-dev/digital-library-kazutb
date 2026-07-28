<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class StewardshipMetricsTest extends TestCase
{
    private bool $useLivePgsql = false;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->canUseLivePgsql()) {
            $this->useLivePgsql = true;
            Config::set('database.default', 'pgsql');
            DB::purge('pgsql');
        }
    }

    private function canUseLivePgsql(): bool
    {
        try {
            DB::connection('pgsql')->getPdo();
            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    private function requireLivePgsql(): void
    {
        if (! $this->useLivePgsql) {
            $this->markTestSkipped('Live PostgreSQL not available.');
        }
    }

    private function staffSession(): array
    {
        return [
            'library.user' => [
                'id' => 'aaaaaaaa-0000-0000-0000-111111111111',
                'role' => 'librarian',
                'email' => 'staff@digital-library.test',
                'name' => 'Test Staff',
            ],
        ];
    }

    public function test_stewardship_metrics_route_exists(): void
    {
        $response = $this->withSession($this->staffSession())
            ->getJson('/api/v1/internal/review/stewardship-metrics');

        $this->assertNotEquals(404, $response->status(), 'Route should exist');
        $this->assertNotEquals(405, $response->status(), 'Route should accept GET');
    }

    public function test_stewardship_metrics_returns_overall_health(): void
    {
        $this->requireLivePgsql();

        $response = $this->withSession($this->staffSession())
            ->getJson('/api/v1/internal/review/stewardship-metrics');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'overallHealth' => ['totalEntities', 'cleanEntities', 'unresolvedEntities', 'healthPercent'],
            ],
            'source',
        ]);

        $health = $response->json('data.overallHealth');
        $this->assertIsInt($health['totalEntities']);
        $this->assertGreaterThan(0, $health['totalEntities']);
        $this->assertGreaterThanOrEqual(0, $health['healthPercent']);
        $this->assertLessThanOrEqual(100, $health['healthPercent']);
    }

    public function test_stewardship_metrics_returns_entity_breakdown(): void
    {
        $this->requireLivePgsql();

        $response = $this->withSession($this->staffSession())
            ->getJson('/api/v1/internal/review/stewardship-metrics');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'byEntity' => [
                    'copies' => ['total', 'needsReview', 'clean', 'healthPercent'],
                    'documents' => ['total', 'needsReview', 'clean', 'healthPercent'],
                    'readers' => ['total', 'needsReview', 'clean', 'healthPercent'],
                ],
            ],
        ]);

        $copies = $response->json('data.byEntity.copies');
        $this->assertIsInt($copies['total']);
        $this->assertIsInt($copies['needsReview']);
        $this->assertEquals($copies['total'] - $copies['needsReview'], $copies['clean']);
    }

    public function test_stewardship_metrics_returns_review_task_stats(): void
    {
        $this->requireLivePgsql();

        $response = $this->withSession($this->staffSession())
            ->getJson('/api/v1/internal/review/stewardship-metrics');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'reviewTasks' => ['total', 'open', 'completed', 'cancelled'],
            ],
        ]);

        $tasks = $response->json('data.reviewTasks');
        $this->assertIsInt($tasks['total']);
        $this->assertGreaterThanOrEqual($tasks['completed'], $tasks['total']);
    }

    public function test_stewardship_metrics_returns_dq_flag_stats(): void
    {
        $this->requireLivePgsql();

        $response = $this->withSession($this->staffSession())
            ->getJson('/api/v1/internal/review/stewardship-metrics');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'dataQualityFlags' => [
                    'total',
                    'byStatus' => ['open', 'resolved', 'rejected'],
                    'bySeverity' => ['high', 'medium', 'low'],
                    'byEntity' => ['book_copy', 'document', 'reader'],
                ],
            ],
        ]);
    }

    public function test_stewardship_metrics_returns_top_issues(): void
    {
        $this->requireLivePgsql();

        $response = $this->withSession($this->staffSession())
            ->getJson('/api/v1/internal/review/stewardship-metrics');

        $response->assertOk();
        $this->assertArrayHasKey('topIssues', $response->json('data'));

        $issues = $response->json('data.topIssues');
        $this->assertIsArray($issues);
        if (! empty($issues)) {
            $this->assertArrayHasKey('reasonCode', $issues[0]);
            $this->assertArrayHasKey('count', $issues[0]);
        }
    }

    public function test_stewardship_page_renders_dashboard_sections(): void
    {
        $response = $this->withSession($this->staffSession())
            ->get('/internal/stewardship');

        $response->assertOk();
        $response->assertSee('Общее здоровье данных');
        $response->assertSee('Задачи проверки');
        $response->assertSee('Флаги качества');
        $response->assertSee('Прогресс по типу сущности');
        $response->assertSee('stewardship-metrics');
    }
}
