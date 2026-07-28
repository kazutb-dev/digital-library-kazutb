<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReviewIssuesSummaryDbTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'pgsql');
        DB::purge('pgsql');
    }

    public function test_review_issues_summary_endpoint_returns_compact_summary(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL is not available for read-only review summary endpoint test.');
        }

        $response = $this->getJson('/api/v1/review/issues-summary?top_limit=3');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'total',
                    'bySeverity',
                    'byStatus',
                    'topIssueCodes' => [
                        '*' => ['issueCode', 'count'],
                    ],
                    'criticalCount',
                    'highCount',
                    'openCount',
                ],
                'source',
            ])
            ->assertJsonPath('source', 'review.quality_issues');

        $this->assertIsArray($response->json('data.bySeverity'));
        $this->assertIsArray($response->json('data.byStatus'));
        $this->assertIsArray($response->json('data.topIssueCodes'));
        $this->assertLessThanOrEqual(3, count($response->json('data.topIssueCodes', [])));
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
}
