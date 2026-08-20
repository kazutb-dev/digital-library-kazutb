<?php

namespace Tests\Feature;

use App\Models\ExternalResource;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class ExternalResourcePageTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        ExternalResource::query()->forceDelete();
    }

    private function createPublishedResource(): ExternalResource
    {
        return ExternalResource::query()->create([
            'slug' => 'persisted-card-contract',
            'title' => 'Persisted card contract',
            'resource_type' => 'open_access',
            'description' => 'A complete persisted public resource description.',
            'name_translations' => ['en' => 'Persisted card contract'],
            'description_translations' => ['en' => 'A complete persisted public resource description.'],
            'available_roles' => ExternalResource::AUDIENCES,
            'content_types' => ['scientific_articles'],
            'access_instructions' => 'Open the verified public resource.',
            'instructions_translations' => ['en' => 'Open the verified public resource.'],
            'url' => 'https://example.org/persisted-card-contract',
            'provider' => 'Persisted provider',
            'access_type' => 'open',
            'access_method' => 'public_url',
            'category' => 'research_database',
            'guest_access' => true,
            'campus_only' => false,
            'login_required' => false,
            'health_status' => 'healthy',
            'is_active' => true,
            'publication_status' => 'published',
            'published_at' => now('UTC'),
        ]);
    }

    public function test_external_resource_cards_have_logo_status_content_audiences_and_instructions(): void
    {
        $this->createPublishedResource();

        $this->get('/resources?lang=en')
            ->assertOk()
            ->assertSee('data-resource-slug="persisted-card-contract"', false)
            ->assertSee('Persisted card contract', false)
            ->assertSee('external-resource-card__logo', false)
            ->assertSee('external-resource-card__status', false)
            ->assertSee('external-resource-card__chips', false)
            ->assertSee('external-resource-card__audiences', false)
            ->assertSee('external-resource-card__instructions', false)
            ->assertSee('View details', false)
            ->assertSee('Open resource', false);
    }

    public function test_config_without_a_persisted_public_row_renders_the_canonical_empty_state(): void
    {
        $this->get('/resources?lang=en')
            ->assertOk()
            ->assertSee('data-test-id="resources-canonical-empty"', false)
            ->assertDontSee('<article class="external-resource-card"', false)
            ->assertDontSee('IPR SMART', false)
            ->assertDontSee('CyberLeninka', false);
    }

    public function test_public_shell_and_adjacent_pages_still_render(): void
    {
        $this->get('/shortlist')->assertOk()->assertSee('data-shortlist-page', false);
        $this->get('/catalog')->assertOk();
        $this->get('/')->assertOk();
    }

    public function test_for_teachers_redirects_to_resource_directory(): void
    {
        $this->get('/for-teachers')->assertStatus(301)->assertRedirect('/resources');
    }
}
