<?php

namespace Tests\Feature;

use App\Models\ExternalResource;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class ResourcesPageTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        ExternalResource::query()->forceDelete();
    }

    /** @param array<string, mixed> $overrides */
    private function createPublishedResource(array $overrides = []): ExternalResource
    {
        $slug = (string) ($overrides['slug'] ?? 'persisted-'.str()->lower(str()->random(8)));
        $title = (string) ($overrides['title'] ?? 'Persisted academic resource');
        $type = (string) ($overrides['resource_type'] ?? 'open_access');
        $agreementEnd = in_array($type, ['licensed', 'partner'], true)
            ? now('UTC')->addYears(2)->toDateString()
            : null;

        return ExternalResource::query()->create(array_replace([
            'slug' => $slug,
            'title' => $title,
            'resource_type' => $type,
            'description' => 'Persisted public description from the authoritative resource table.',
            'name_translations' => ['ru' => $title, 'kk' => $title, 'en' => $title],
            'description_translations' => [
                'ru' => 'Описание опубликованного ресурса.',
                'kk' => 'Жарияланған ресурс сипаттамасы.',
                'en' => 'Published resource description.',
            ],
            'available_roles' => ExternalResource::AUDIENCES,
            'content_types' => ['scientific_articles'],
            'access_instructions' => 'Use the verified public access link.',
            'instructions_translations' => [
                'ru' => 'Используйте проверенную публичную ссылку.',
                'kk' => 'Тексерілген ашық сілтемені пайдаланыңыз.',
                'en' => 'Use the verified public link.',
            ],
            'url' => $type === 'internal' ? '/catalog' : 'https://example.org/'.$slug,
            'provider' => 'Persisted provider',
            'access_type' => 'open',
            'access_method' => 'public_url',
            'category' => 'research_database',
            'guest_access' => true,
            'campus_only' => false,
            'login_required' => false,
            'contract_ends_at' => $agreementEnd,
            'license_expires_at' => $agreementEnd,
            'health_status' => 'healthy',
            'is_active' => true,
            'publication_status' => 'published',
            'published_at' => now('UTC'),
            'sort_order' => 100,
        ], $overrides));
    }

    public function test_resources_page_and_supported_locales_render(): void
    {
        $this->get('/resources')->assertOk();
        $this->get('/resources?lang=ru')->assertOk()->assertSee('Внешние и электронные ресурсы', false);
        $this->get('/resources?lang=en')->assertOk()->assertSee('External and electronic resources', false);
        $this->get('/resources?lang=kk')->assertOk()->assertSee('Сыртқы және электрондық ресурстар', false);
    }

    public function test_resources_page_preserves_public_directory_landmarks_and_type_order(): void
    {
        $this->createPublishedResource(['slug' => 'persisted-licensed', 'title' => 'Persisted Licensed Index', 'resource_type' => 'licensed']);
        $this->createPublishedResource(['slug' => 'persisted-open', 'title' => 'Persisted Open Journal', 'resource_type' => 'open_access']);
        $this->createPublishedResource(['slug' => 'persisted-partner', 'title' => 'Persisted Partner Library', 'resource_type' => 'partner']);
        $this->createPublishedResource(['slug' => 'persisted-internal', 'title' => 'Persisted KazUTB Catalog', 'resource_type' => 'internal']);

        $content = $this->get('/resources?lang=ru')->assertOk()->getContent();
        foreach ([
            'data-section="resources-canonical-hero"',
            'data-section="resources-canonical-main"',
            'data-section="resources-canonical-premium"',
            'data-section="resources-canonical-open-access"',
            'data-section="resources-canonical-sidebar"',
        ] as $marker) {
            $this->assertStringContainsString($marker, $content);
        }

        $this->assertLessThan(strpos($content, 'data-resource-group="open_access"'), strpos($content, 'data-resource-group="licensed"'));
        $this->assertLessThan(strpos($content, 'data-resource-group="partner"'), strpos($content, 'data-resource-group="open_access"'));
        $this->assertLessThan(strpos($content, 'data-resource-group="internal"'), strpos($content, 'data-resource-group="partner"'));
    }

    public function test_resources_page_renders_persisted_open_and_internal_cards_with_safe_actions(): void
    {
        $this->createPublishedResource(['slug' => 'persisted-open', 'title' => 'Persisted Open Journal', 'resource_type' => 'open_access']);
        $this->createPublishedResource(['slug' => 'persisted-internal', 'title' => 'Persisted KazUTB Catalog', 'resource_type' => 'internal']);

        $this->get('/resources?lang=ru')
            ->assertOk()
            ->assertSee('Persisted Open Journal', false)
            ->assertSee('Persisted KazUTB Catalog', false)
            ->assertSee('data-resource-slug="persisted-open"', false)
            ->assertSee('data-resource-slug="persisted-internal"', false)
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertDontSee('href="#"', false);
    }

    public function test_login_required_ipr_card_routes_guests_to_sign_in_instead_of_the_open_endpoint(): void
    {
        $this->createPublishedResource([
            'slug' => 'ipr-smart',
            'title' => 'IPR SMART',
            'resource_type' => 'licensed',
            'url' => 'https://www.iprbookshop.ru/',
            'available_roles' => ['student', 'teacher', 'library_staff'],
            'guest_access' => false,
            'login_required' => true,
            'access_type' => 'remote_auth',
            'access_method' => 'personal_account',
        ]);

        $response = $this->get('/resources?lang=en');

        $response->assertOk()
            ->assertSee('data-resource-slug="ipr-smart"', false)
            ->assertSee('data-test-id="resources-sign-in-ipr-smart"', false)
            ->assertSee('Sign in to access', false)
            ->assertDontSee('data-test-id="resources-link-ipr-smart"', false)
            ->assertDontSee('/resources/1/open', false);
    }

    public function test_resources_page_has_required_filters_and_public_card_metadata(): void
    {
        $this->createPublishedResource(['slug' => 'persisted-licensed', 'resource_type' => 'licensed']);
        $this->createPublishedResource(['slug' => 'persisted-open', 'resource_type' => 'open_access']);
        $this->createPublishedResource(['slug' => 'persisted-partner', 'resource_type' => 'partner']);
        $this->createPublishedResource(['slug' => 'persisted-internal', 'resource_type' => 'internal']);

        $this->get('/resources?lang=ru')
            ->assertOk()
            ->assertSee('data-resource-search', false)
            ->assertSee('data-resource-filter="licensed"', false)
            ->assertSee('data-resource-filter="open_access"', false)
            ->assertSee('data-resource-filter="partner"', false)
            ->assertSee('data-resource-filter="internal"', false)
            ->assertSee('data-resource-facet="audience"', false)
            ->assertSee('data-resource-facet="accessScope"', false)
            ->assertSee('data-resource-facet="content"', false)
            ->assertSee('data-resource-facet="status"', false)
            ->assertSee('Кому доступно', false)
            ->assertSee('Как пользоваться', false)
            ->assertSee('Срок доступа', false);
    }

    public function test_config_only_resources_do_not_publish_without_authoritative_rows(): void
    {
        $this->get('/resources?lang=ru')
            ->assertOk()
            ->assertSee('data-test-id="resources-canonical-empty"', false)
            ->assertDontSee('<article class="external-resource-card"', false)
            ->assertDontSee('IPR SMART', false)
            ->assertDontSee('КиберЛенинка', false)
            ->assertDontSee('Directory of Open Access Journals (DOAJ)', false)
            ->assertDontSee('Научная библиотека АТУ', false)
            ->assertDontSee('Республиканская научно-техническая библиотека', false)
            ->assertDontSee('internal_notes', false)
            ->assertDontSee('licence_file_path', false)
            ->assertDontSee('contract_number', false);
    }

    public function test_help_cta_and_legacy_teacher_redirect_remain_valid(): void
    {
        $this->createPublishedResource(['slug' => 'persisted-help-resource']);

        $this->get('/resources?lang=en')
            ->assertOk()
            ->assertSee('data-test-id="resources-canonical-off-campus-cta"', false)
            ->assertSee('href="/contacts?lang=en"', false);

        $this->get('/for-teachers')->assertStatus(301)->assertRedirect('/resources');
    }
}
