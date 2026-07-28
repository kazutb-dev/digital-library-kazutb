<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class BridgeBooksDocumentsDiagnosticsDbTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'pgsql');
        DB::purge('pgsql');
    }

    public function test_bridge_books_diagnostics_endpoint_returns_paginated_diagnostics_structure(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL is not available for bridge books diagnostics endpoint test.');
        }

        $response = $this->getJson('/api/v1/bridge/books?page=1&limit=5');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    'items' => [
                        '*' => [
                            'publicBookId',
                            'title',
                            'isbn' => ['raw', 'normalized', 'isEmpty'],
                            'bridge' => ['matched', 'matchedAppDocumentId', 'candidateCount', 'ambiguity', 'reason'],
                            'normalization' => ['warning'],
                        ],
                    ],
                ],
                'meta' => ['page', 'per_page', 'total', 'total_pages', 'totalPages'],
                'warnings',
                'source',
            ])
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.per_page', 5)
            ->assertJsonPath('source', 'public."Book", app.documents');

        $this->assertIsArray($response->json('data.items'));
        $this->assertIsArray($response->json('warnings'));
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
