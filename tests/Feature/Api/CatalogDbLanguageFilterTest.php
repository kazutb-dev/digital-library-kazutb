<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogDbLanguageFilterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'pgsql');
        DB::purge('pgsql');
    }

    public function test_catalog_db_language_filter_maps_russian_ui_code_to_catalog_data(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL not available.');
        }

        $response = $this->getJson('/api/v1/catalog-db?language=ru&limit=5');

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'));

        foreach ($response->json('data') as $item) {
            $this->assertSame('ru', mb_strtolower((string) ($item['language']['code'] ?? '')));
        }
    }

    public function test_catalog_db_language_filter_supports_all_catalog_language_chips(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL not available.');
        }

        foreach (['ru', 'kk', 'en'] as $language) {
            $response = $this->getJson("/api/v1/catalog-db?language={$language}&limit=3");

            $response->assertOk();
            $this->assertGreaterThan(
                0,
                $response->json('meta.total'),
                "Expected catalog results for language filter [{$language}]"
            );
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
}