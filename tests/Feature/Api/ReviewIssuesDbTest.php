<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReviewIssuesDbTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'pgsql');
        DB::purge('pgsql');
    }

    public function test_review_issues_endpoint_returns_paginated_payload(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL is not available for read-only review endpoint test.');
        }

        $response = $this->getJson('/api/v1/review/issues?limit=2');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'id',
                        'issueCode',
                        'severity',
                        'status',
                        'sourceSchema',
                        'sourceTable',
                        'sourceKey',
                        'summary',
                        'createdAt',
                        'updatedAt',
                        'source',
                    ],
                ],
                'meta' => ['page', 'per_page', 'total', 'total_pages', 'totalPages'],
                'filters' => ['severity', 'status', 'issue_code'],
            ])
            ->assertJsonPath('meta.per_page', 2);
    }

    public function test_review_issues_endpoint_filters_by_severity(): void
    {
        $severity = $this->firstExistingSeverity();

        $response = $this->getJson('/api/v1/review/issues?severity=' . urlencode($severity) . '&limit=5');

        $response->assertOk();

        foreach ($response->json('data', []) as $item) {
            $this->assertSame(strtoupper($severity), $item['severity']);
        }
    }

    private function firstExistingSeverity(): string
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL is not available for read-only review endpoint test.');
        }

        $severity = DB::table('review.quality_issues')->value('severity');

        if (! is_string($severity) || $severity === '') {
            $this->markTestSkipped('No severity found in review.quality_issues for endpoint test.');
        }

        return $severity;
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
