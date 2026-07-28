<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogDbSearchTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'pgsql');
        DB::purge('pgsql');
    }

    public function test_catalog_db_returns_paginated_results(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL not available.');
        }

        $response = $this->getJson('/api/v1/catalog-db?limit=3');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [[
                    'id',
                    'title' => ['display', 'raw'],
                    'primaryAuthor',
                    'publisher',
                    'publicationYear',
                    'language',
                    'isbn',
                    'copies',
                    'udc' => ['raw', 'source'],
                ]],
                'meta' => ['page', 'per_page', 'total', 'totalPages'],
            ]);

        $this->assertLessThanOrEqual(3, count($response->json('data')));
        $this->assertSame(1, $response->json('meta.page'));
        $this->assertSame(3, $response->json('meta.per_page'));
    }

    public function test_catalog_db_language_filter(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL not available.');
        }

        $response = $this->getJson('/api/v1/catalog-db?language=ru&limit=5');
        $response->assertOk();

        foreach ($response->json('data') as $item) {
            $this->assertEqualsIgnoringCase('ru', $item['language']['code']);
        }
    }

    public function test_catalog_db_year_filter(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL not available.');
        }

        $response = $this->getJson('/api/v1/catalog-db?year_from=2020&year_to=2023&limit=5');
        $response->assertOk();

        foreach ($response->json('data') as $item) {
            if ($item['publicationYear'] !== null) {
                $this->assertGreaterThanOrEqual(2020, $item['publicationYear']);
                $this->assertLessThanOrEqual(2023, $item['publicationYear']);
            }
        }
    }

    public function test_catalog_db_available_only_filter(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL not available.');
        }

        $response = $this->getJson('/api/v1/catalog-db?available_only=1&limit=5');
        $response->assertOk();

        foreach ($response->json('data') as $item) {
            $this->assertGreaterThan(0, $item['copies']['available']);
        }
    }

    public function test_catalog_db_physical_only_filter(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL not available.');
        }

        $response = $this->getJson('/api/v1/catalog-db?physical_only=1&limit=5');
        $response->assertOk();

        foreach ($response->json('data') as $item) {
            $this->assertGreaterThan(0, $item['copies']['total']);
        }
    }

    public function test_catalog_db_institution_filter(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL not available.');
        }

        $response = $this->getJson('/api/v1/catalog-db?institution=technology_library&limit=5');

        $response->assertOk();
        $this->assertIsArray($response->json('data'));
    }

    public function test_catalog_db_sort_options(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL not available.');
        }

        foreach (['popular', 'newest', 'title', 'author'] as $sort) {
            $response = $this->getJson("/api/v1/catalog-db?sort={$sort}&limit=2");
            $response->assertOk();
        }
    }

    public function test_catalog_db_search_query(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL not available.');
        }

        $first = DB::table('app.document_detail_v')
            ->whereNotNull('title_display')
            ->where('title_display', '!=', '')
            ->value('title_display');

        if (! $first) {
            $this->markTestSkipped('No documents in view.');
        }

        $keyword = mb_substr($first, 0, 8);
        $response = $this->getJson('/api/v1/catalog-db?q='.urlencode($keyword).'&limit=3');
        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'));
    }

    public function test_catalog_db_no_result_query_returns_empty_payload(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL not available.');
        }

        $needle = '__A2_NO_MATCH__'.uniqid();

        $response = $this->getJson('/api/v1/catalog-db?q='.urlencode($needle).'&limit=5');

        $response->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('meta.per_page', 5);

        $this->assertSame([], $response->json('data'));
    }

    public function test_catalog_db_validates_bad_params(): void
    {
        $this->getJson('/api/v1/catalog-db?sort=invalid')->assertUnprocessable();
        $this->getJson('/api/v1/catalog-db?limit=999')->assertUnprocessable();
        $this->getJson('/api/v1/catalog-db?year_from=abc')->assertUnprocessable();
        $this->getJson('/api/v1/catalog-db?physical_only=maybe')->assertUnprocessable();
        $this->getJson('/api/v1/catalog-db?institution=unknown')->assertUnprocessable();
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
