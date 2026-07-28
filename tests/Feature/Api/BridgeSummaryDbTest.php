<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BridgeSummaryDbTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'pgsql');
        DB::purge('pgsql');
    }

    public function test_bridge_summary_endpoint_returns_bridge_metrics_structure(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL is not available for read-only bridge summary endpoint test.');
        }

        $response = $this->getJson('/api/v1/bridge/summary');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'publicUsersTotal',
                    'appReadersTotal',
                    'matchedUsersByEmail',
                    'unmatchedPublicUsers',
                    'unmatchedAppReaders',
                    'publicBooksTotal',
                    'appDocumentsTotal',
                    'matchedBooksByIsbn',
                    'unmatchedPublicBooks',
                    'unmatchedAppDocuments',
                    'publicBookCopiesTotal',
                    'appBookCopiesTotal',
                    'matchedCopiesByInventory',
                    'unmatchedPublicCopies',
                    'unmatchedAppCopies',
                ],
                'warnings',
                'notes',
                'source',
            ]);

        $this->assertIsInt($response->json('data.publicUsersTotal'));
        $this->assertIsInt($response->json('data.matchedUsersByEmail'));
        $this->assertIsString($response->json('source'));
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
