<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Tests\TestCase;

/**
 * Phase 3.h — Public homepage (welcome.blade.php) canonical-exact feature coverage.
 *
 * Homepage is rebuilt from `docs/design-exports/homepage_canonical/` — 3 sections:
 *   Hero → Curated Collections Bento → Scholarly Services.
 * These tests lock down:
 *   - guest access and canonical section markers
 *   - canonical hero/search/bento/services structure
 *   - tri-lingual (ru/kk/en) content rendering
 *   - authenticated session state (Member Workspace → /dashboard)
 *   - old Enhanced Homepage shell markers are absent
 */
class PublicHomepagePageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('demo_auth.enabled', true);
        $this->withoutMiddleware([VerifyCsrfToken::class, ValidateCsrfToken::class]);
    }

    private function loginAs(string $identitySlug): void
    {
        $identity = config("demo_auth.identities.{$identitySlug}");

        $this->get('/login');
        $this->post('/login', [
            '_token' => csrf_token(),
            'login' => $identity['login'],
            'password' => $identity['password'],
            'device_name' => 'phpunit',
        ]);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Basic access
    // ─────────────────────────────────────────────────────────────────────────

    public function test_guest_can_view_homepage(): void
    {
        $response = $this->get('/?lang=en');

        $response->assertOk();
        $response->assertSee('KazUTB Smart Library', false);
        // Canonical section markers from homepage_canonical export.
        $response->assertSee('data-section="homepage-canonical-hero"', false);
        $response->assertSee('data-section="homepage-canonical-collections"', false);
        $response->assertSee('data-section="homepage-canonical-services"', false);
    }

    public function test_homepage_returns_200(): void
    {
        $this->get('/')->assertOk();
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Canonical structure markers
    // ─────────────────────────────────────────────────────────────────────────

    public function test_homepage_canonical_page_wrapper_present(): void
    {
        $response = $this->get('/');

        $response->assertSee('data-section="homepage-canonical-page"', false);
    }

    public function test_homepage_canonical_hero_section_present(): void
    {
        $response = $this->get('/?lang=en');

        $response->assertOk();
        $response->assertSee('data-section="homepage-canonical-hero"', false);
        $response->assertSee('data-test-id="homepage-canonical-kicker"', false);
        $response->assertSee('data-test-id="homepage-canonical-search"', false);
        $response->assertSee('data-test-id="homepage-canonical-hero-stats"', false);
    }

    public function test_homepage_canonical_collections_section_present(): void
    {
        $response = $this->get('/?lang=en');

        $response->assertOk();
        $response->assertSee('data-section="homepage-canonical-collections"', false);
        $response->assertSee('data-test-id="homepage-canonical-collections-heading"', false);
        $response->assertSee('data-test-id="homepage-canonical-bento-featured"', false);
        $response->assertSee('data-test-id="homepage-canonical-bento-tile-1"', false);
        $response->assertSee('data-test-id="homepage-canonical-bento-tile-2"', false);
    }

    public function test_homepage_canonical_services_section_present(): void
    {
        $response = $this->get('/?lang=en');

        $response->assertOk();
        $response->assertSee('data-section="homepage-canonical-services"', false);
        $response->assertSee('data-test-id="homepage-canonical-services-heading"', false);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Hero content
    // ─────────────────────────────────────────────────────────────────────────

    public function test_homepage_hero_search_posts_to_catalog(): void
    {
        $response = $this->get('/?lang=en');

        $response->assertOk();
        $response->assertSee('action="/catalog?lang=en"', false);
        $response->assertSee('name="q"', false);
    }

    public function test_homepage_hero_stats_card_renders(): void
    {
        $response = $this->get('/?lang=en');

        $response->assertOk();
        $response->assertSee('120,000+', false);
        $response->assertSee('8,400+', false);
        $response->assertSee('Archived Materials', false);
        $response->assertSee('Active Readers', false);
    }

    public function test_homepage_topic_quick_links_render(): void
    {
        $response = $this->get('/?lang=en');

        $response->assertOk();
        $response->assertSee('id="hero-quick-links"', false);
        $response->assertSee('Economic Reform', false);
        $response->assertSee('Sustainable Tech', false);
        $response->assertSee('Central Asian History', false);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Bento collection tiles
    // ─────────────────────────────────────────────────────────────────────────

    public function test_homepage_bento_featured_tile_links_to_discover(): void
    {
        $response = $this->get('/?lang=en');

        $response->assertOk();
        $response->assertSee('href="/discover?lang=en"', false);
        $response->assertSee('Core Collection', false);
        $response->assertSee('Academic Resources', false);
    }

    public function test_homepage_bento_tile1_links_to_applied_sciences(): void
    {
        $response = $this->get('/?lang=en');

        $response->assertOk();
        $response->assertSee('href="/catalog?udc=5&amp;lang=en"', false);
        $response->assertSee('Applied Sciences', false);
        $response->assertSee('UDC 5', false);
    }

    public function test_homepage_bento_tile2_links_to_economics_law(): void
    {
        $response = $this->get('/?lang=en');

        $response->assertOk();
        $response->assertSee('href="/catalog?udc=33&amp;lang=en"', false);
        $response->assertSee('UDC 33', false);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Services section
    // ─────────────────────────────────────────────────────────────────────────

    public function test_homepage_services_render_for_guest(): void
    {
        $response = $this->get('/?lang=en');

        $response->assertOk();
        $response->assertSee('Scholarly Services', false);
        $response->assertSee('Reference Services', false);
        $response->assertSee('Course Collections', false);
        $response->assertSee('Member Workspace', false);
    }

    public function test_guest_service_workspace_cta_links_to_login(): void
    {
        $response = $this->get('/?lang=en');

        $response->assertOk();
        $response->assertSee('href="/login?lang=en"', false);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Authentication-aware routing
    // ─────────────────────────────────────────────────────────────────────────

    public function test_authenticated_reader_sees_member_workspace_routing_to_dashboard(): void
    {
        $this->loginAs('student');

        $response = $this->get('/?lang=en');

        $response->assertOk();
        $response->assertSee('KazUTB Smart Library', false);
        $response->assertSee('Sign out', false);
        // Member Workspace card routes to /dashboard for authenticated users.
        $response->assertSee('href="/dashboard?lang=en"', false);
    }

    public function test_authenticated_teacher_can_view_homepage(): void
    {
        $this->loginAs('teacher');

        $response = $this->get('/?lang=en');

        $response->assertOk();
        $response->assertSee('KazUTB Smart Library', false);
        $response->assertSee('Sign out', false);
    }

    public function test_librarian_can_view_public_homepage(): void
    {
        $this->loginAs('librarian');

        $response = $this->get('/?lang=en');

        $response->assertOk();
        $response->assertSee('KazUTB Smart Library', false);
    }

    public function test_admin_can_view_public_homepage(): void
    {
        $this->loginAs('admin');

        $response = $this->get('/?lang=en');

        $response->assertOk();
        $response->assertSee('KazUTB Smart Library', false);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Tri-lingual support
    // ─────────────────────────────────────────────────────────────────────────

    public function test_homepage_renders_russian_locale(): void
    {
        $response = $this->get('/?lang=ru');

        $response->assertOk();
        $response->assertSee('data-section="homepage-canonical-hero"', false);
        $response->assertSee('Цифровой куратор', false);
        $response->assertSee('Избранные коллекции', false);
        $response->assertSee('Научные сервисы', false);
        $response->assertSee('Актуальные темы:', false);
    }

    public function test_homepage_renders_kazakh_locale(): void
    {
        $response = $this->get('/?lang=kk');

        $response->assertOk();
        $response->assertSee('data-section="homepage-canonical-hero"', false);
        $response->assertSee('Цифрлық куратор', false);
        $response->assertSee('Таңдаулы жинақтар', false);
        $response->assertSee('Ғылыми сервистер', false);
    }

    public function test_homepage_default_locale_is_russian(): void
    {
        $response = $this->get('/');

        $response->assertOk();
        $response->assertSee('Цифровой куратор', false);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Brand correctness
    // ─────────────────────────────────────────────────────────────────────────

    public function test_homepage_never_reintroduces_athenaeum_or_old_brand(): void
    {
        $response = $this->get('/?lang=en');

        $response->assertOk();
        $response->assertDontSee('Athenaeum', false);
        $response->assertDontSee('KazUTB Digital Library', false);
    }

    // ─────────────────────────────────────────────────────────────────────────
    // Old Enhanced Homepage shell markers are absent
    // ─────────────────────────────────────────────────────────────────────────

    public function test_old_homepage_shell_sections_are_absent(): void
    {
        $response = $this->get('/?lang=en');

        $response->assertOk();
        // These sections existed in the old Enhanced Homepage shell but are NOT
        // present in the canonical 3-section export.
        $response->assertDontSee('Operational Hours', false);
        $response->assertDontSee('News &amp; Announcements', false);
        $response->assertDontSee('Pulse of', false);
        $response->assertDontSee('Institutional Repository', false);
        $response->assertDontSee('Getting Started', false);
        $response->assertDontSee('Research Tools', false);
        $response->assertDontSee('Latest Arrivals', false);
        $response->assertDontSee('data-test-id="latest-arrivals-grid"', false);
        $response->assertDontSee('data-homepage-stitch-reset', false);
    }
}
