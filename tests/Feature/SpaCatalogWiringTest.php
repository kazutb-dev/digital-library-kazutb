<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpaCatalogWiringTest extends TestCase
{
    public function test_spa_catalog_component_uses_canonical_catalog_db_api_path(): void
    {
        $source = file_get_contents(base_path('resources/js/spa/pages/CatalogPage.jsx'));

        $this->assertIsString($source);
        $this->assertStringContainsString("api(`/catalog-db?", $source);
        $this->assertStringNotContainsString('/catalog/search?', $source);
    }

    public function test_spa_catalog_component_wires_language_and_year_filters_into_query_params(): void
    {
        $source = file_get_contents(base_path('resources/js/spa/pages/CatalogPage.jsx'));

        $this->assertIsString($source);
        $this->assertStringContainsString("searchParams.get('language')", $source);
        $this->assertStringContainsString("searchParams.get('year_from')", $source);
        $this->assertStringContainsString("searchParams.get('year_to')", $source);
        $this->assertStringContainsString("params.set('language'", $source);
        $this->assertStringContainsString("params.set('year_from'", $source);
        $this->assertStringContainsString("params.set('year_to'", $source);
    }

    public function test_catalog_blade_wires_institution_and_physical_filters_into_query_params(): void
    {
        $source = file_get_contents(base_path('resources/views/catalog.blade.php'));

        $this->assertIsString($source);
        // URL → PHP (server-side) → JS state bootstrap → API params
        $this->assertStringContainsString("params.set('institution'", $source);
        $this->assertStringContainsString("params.set('physical_only'", $source);
        $this->assertStringContainsString("institution: @json(\$institution)", $source);
        $this->assertStringContainsString("physicalOnly: @json(\$physicalOnly)", $source);
    }

    public function test_spa_catalog_component_resets_pagination_when_filters_change(): void
    {
        $source = file_get_contents(base_path('resources/js/spa/pages/CatalogPage.jsx'));

        $this->assertIsString($source);
        $this->assertStringContainsString("updateParams({ language:", $source);
        $this->assertStringContainsString("page: 1", $source);
    }

    public function test_spa_catalog_component_links_to_canonical_book_route(): void
    {
        $source = file_get_contents(base_path('resources/js/spa/pages/CatalogPage.jsx'));

        $this->assertIsString($source);
        $this->assertStringContainsString('/book/${encodeURIComponent(identifier)}', $source);
    }
}
