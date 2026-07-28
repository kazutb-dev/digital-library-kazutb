<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class ExternalResourceApiTest extends TestCase
{
    public function test_external_resources_index_returns_all_resources(): void
    {
        $response = $this->getJson('/api/v1/external-resources');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data' => [
                    '*' => [
                        'slug',
                        'title',
                        'provider',
                        'description',
                        'access_type',
                        'status',
                        'category',
                    ],
                ],
                'meta' => [
                    'total',
                    'categories',
                    'access_types',
                ],
            ]);

        $this->assertGreaterThan(0, $response->json('meta.total'));
    }

    public function test_external_resources_index_includes_known_resources(): void
    {
        $response = $this->getJson('/api/v1/external-resources');

        $response->assertOk();

        $slugs = collect($response->json('data'))->pluck('slug')->all();

        $this->assertContains('ipr-smart', $slugs);
        $this->assertContains('rmeb', $slugs);
        $this->assertContains('elibrary', $slugs);
        $this->assertContains('polpred', $slugs);
    }

    public function test_external_resources_filter_by_category(): void
    {
        $response = $this->getJson('/api/v1/external-resources?category=open_access');

        $response->assertOk();

        $categories = collect($response->json('data'))->pluck('category')->unique()->all();

        $this->assertCount(1, $categories);
        $this->assertEquals('open_access', $categories[0]);
    }

    public function test_external_resources_filter_by_access_type(): void
    {
        $response = $this->getJson('/api/v1/external-resources?access_type=campus');

        $response->assertOk();

        $accessTypes = collect($response->json('data'))->pluck('access_type')->unique()->all();

        foreach ($accessTypes as $type) {
            $this->assertEquals('campus', $type);
        }
    }

    public function test_external_resources_filter_by_status(): void
    {
        $response = $this->getJson('/api/v1/external-resources?status=active');

        $response->assertOk();

        $statuses = collect($response->json('data'))->pluck('status')->unique()->all();

        foreach ($statuses as $status) {
            $this->assertEquals('active', $status);
        }
    }

    public function test_external_resources_filter_returns_empty_for_unknown_category(): void
    {
        $response = $this->getJson('/api/v1/external-resources?category=nonexistent');

        $response
            ->assertOk()
            ->assertJsonPath('meta.total', 0)
            ->assertJsonPath('data', []);
    }

    public function test_external_resources_show_returns_resource_by_slug(): void
    {
        $response = $this->getJson('/api/v1/external-resources/ipr-smart');

        $response
            ->assertOk()
            ->assertJsonPath('data.slug', 'ipr-smart')
            ->assertJsonPath('data.title', 'IPR SMART')
            ->assertJsonStructure([
                'data' => [
                    'slug',
                    'title',
                    'provider',
                    'description',
                    'access_type',
                    'status',
                    'url',
                    'category',
                    'category_label',
                    'access_type_label',
                ],
            ]);
    }

    public function test_external_resources_show_returns_404_for_unknown_slug(): void
    {
        $response = $this->getJson('/api/v1/external-resources/nonexistent-slug');

        $response->assertNotFound();
    }

    public function test_external_resources_meta_includes_categories(): void
    {
        $response = $this->getJson('/api/v1/external-resources');

        $response->assertOk();

        $categories = $response->json('meta.categories');

        $this->assertArrayHasKey('electronic_library', $categories);
        $this->assertArrayHasKey('research_database', $categories);
        $this->assertArrayHasKey('open_access', $categories);
        $this->assertArrayHasKey('analytics', $categories);
    }

    public function test_external_resources_meta_includes_access_types(): void
    {
        $response = $this->getJson('/api/v1/external-resources');

        $response->assertOk();

        $accessTypes = $response->json('meta.access_types');

        $this->assertArrayHasKey('campus', $accessTypes);
        $this->assertArrayHasKey('remote_auth', $accessTypes);
        $this->assertArrayHasKey('open', $accessTypes);
    }

    public function test_ipr_smart_has_expiry_date(): void
    {
        $response = $this->getJson('/api/v1/external-resources/ipr-smart');

        $response->assertOk();

        $this->assertNotNull($response->json('data.expiry_date'));
        $this->assertEquals('2026-09-30', $response->json('data.expiry_date'));
    }

    public function test_open_access_resources_have_no_expiry(): void
    {
        $response = $this->getJson('/api/v1/external-resources?category=open_access');

        $response->assertOk();

        foreach ($response->json('data') as $resource) {
            $this->assertNull($resource['expiry_date']);
        }
    }
}
