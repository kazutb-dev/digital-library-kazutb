<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LibraryHealthSummaryDbTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'pgsql');
        DB::purge('pgsql');
    }

    public function test_library_health_summary_endpoint_returns_compact_aggregates(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL is not available for read-only library health endpoint test.');
        }

        $response = $this->getJson('/api/v1/library/health-summary');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'totalDocuments',
                    'totalCopies',
                    'totalReaders',
                    'totalQualityIssues',
                    'documentsNeedsReview',
                    'copiesNeedsReview',
                    'readersNeedsReview',
                    'documentsWithoutIsbn',
                    'documentsWithoutAuthor',
                    'orphanCopies',
                    'documentsWithoutPublisher',
                    'documentsWithoutSubject',
                    'duplicateIsbnGroups',
                ],
                'source',
            ]);

        $this->assertIsInt($response->json('data.totalDocuments'));
        $this->assertIsInt($response->json('data.totalQualityIssues'));
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
