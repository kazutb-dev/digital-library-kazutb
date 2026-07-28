<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Tests\TestCase;

/**
 * Phase 3 Cluster B.5 — canonical-exact /about + preserved /contacts variant.
 *
 * /about now follows docs/design-exports/about_library_canonical exactly:
 *   - hero (eyebrow "Institution" + two-line display + lead + framed media
 *     with institutional badge)
 *   - mission + stats bento (2/3 light + 1/3 dark)
 *   - Collection Profile (4-column icon grid)
 *   - Institutional Directory (3 asymmetric rows with NE arrow circles)
 * Contacts variant is intentionally left on the pre-canonical shell (shared
 * page-hero with aside, librarian-on-duty, mission narrative, B.3 location
 * blocks, catalog-cta) and is NOT in scope for this rebuild.
 */
class PublicAboutPageTest extends TestCase
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

    public function test_guest_can_view_about_page(): void
    {
        $response = $this->get('/about?lang=en');

        $response->assertOk();
        $response->assertSee('KazUTB Smart Library', false);
        $response->assertSee('data-section="about-canonical-hero"', false);
        $response->assertSee('data-section="about-canonical-mission-stats"', false);
        $response->assertSee('data-section="about-canonical-collection"', false);
        $response->assertSee('data-section="about-canonical-directory"', false);
        $response->assertSee('Institution', false);
        $response->assertSee('Preserving Knowledge.', false);
        $response->assertSee('Supporting Research.', false);
        $response->assertSee('Our Mission', false);
        $response->assertSee('The Collection Profile', false);
        $response->assertSee('Institutional Directory', false);
    }

    public function test_about_page_does_not_render_old_about_shell_markers(): void
    {
        $response = $this->get('/about?lang=en');

        $response->assertOk();
        $response->assertDontSee('data-section="contacts-summary"', false);
        $response->assertDontSee('data-section="about-mission"', false);
        $response->assertDontSee('data-section="librarian-on-duty"', false);
        $response->assertDontSee('data-section="about-collection-profile"', false);
        $response->assertDontSee('data-section="about-institutional-directory"', false);
        $response->assertDontSee('data-section="catalog-cta"', false);
    }

    public function test_about_page_renders_canonical_sections_in_order(): void
    {
        $response = $this->get('/about?lang=en');

        $response->assertOk();
        $response->assertSeeInOrder([
            'data-section="about-canonical-hero"',
            'data-section="about-canonical-mission-stats"',
            'data-section="about-canonical-collection"',
            'data-section="about-canonical-directory"',
        ], false);
    }

    public function test_about_page_renders_four_collection_areas(): void
    {
        $response = $this->get('/about?lang=en');

        $response->assertOk();
        $response->assertSee('data-area-slug="technology"', false);
        $response->assertSee('data-area-slug="economy"', false);
        $response->assertSee('data-area-slug="humanities"', false);
        $response->assertSee('data-area-slug="college"', false);
    }

    public function test_about_directory_links_to_rules_leadership_and_contacts(): void
    {
        $response = $this->get('/about?lang=en');

        $response->assertOk();
        $response->assertSee('data-test-id="about-canonical-directory-link-rules"', false);
        $response->assertSee('data-test-id="about-canonical-directory-link-leadership"', false);
        $response->assertSee('data-test-id="about-canonical-directory-link-contacts"', false);
        $response->assertSee('href="/rules?lang=en"', false);
        $response->assertSee('href="/leadership?lang=en"', false);
        $response->assertSee('href="/contacts?lang=en"', false);
    }

    public function test_about_ru_variant_renders_canonical_copy(): void
    {
        $response = $this->get('/about?lang=ru');

        $response->assertOk();
        $response->assertSee('Учреждение', false);
        $response->assertSee('Сохраняем знание.', false);
        $response->assertSee('Поддерживаем исследования.', false);
        $response->assertSee('Наша миссия', false);
        $response->assertSee('Профиль коллекции', false);
        $response->assertSee('Институциональный справочник', false);
        $response->assertSee('href="/rules"', false);
        $response->assertSee('href="/leadership"', false);
        $response->assertSee('href="/contacts"', false);
    }

    public function test_about_kk_variant_renders_canonical_copy_and_preserves_lang(): void
    {
        $response = $this->get('/about?lang=kk');

        $response->assertOk();
        $response->assertSee('Мекеме', false);
        $response->assertSee('Білімді сақтаймыз.', false);
        $response->assertSee('Зерттеуді қолдаймыз.', false);
        $response->assertSee('Біздің миссиямыз', false);
        $response->assertSee('Қор профилі', false);
        $response->assertSee('href="/rules?lang=kk"', false);
        $response->assertSee('href="/leadership?lang=kk"', false);
        $response->assertSee('href="/contacts?lang=kk"', false);
    }

    public function test_about_surface_never_reintroduces_athenaeum_or_legacy_brand(): void
    {
        $about = $this->get('/about?lang=en');
        $contacts = $this->get('/contacts?lang=en');

        $about->assertOk();
        $contacts->assertOk();

        foreach ([$about, $contacts] as $response) {
            $response->assertDontSee('Athenaeum', false);
            $response->assertDontSee('KazUTB Digital Library', false);
        }
    }

    public function test_guest_can_view_about_page_with_canonical_brand(): void
    {
        $response = $this->get('/about?lang=en');
        $response->assertOk()->assertSee('KazUTB Smart Library', false);
    }

    public function test_authenticated_reader_can_view_about_page(): void
    {
        $this->loginAs('student');

        $response = $this->get('/about?lang=en');

        $response->assertOk();
        $response->assertSee('KazUTB Smart Library', false);
        $response->assertSee('Sign out', false);
        $response->assertSee('data-test-id="about-canonical-mission-cta"', false);
        $response->assertSee('href="/catalog?lang=en"', false);
    }

    public function test_librarian_can_view_public_about_and_contacts(): void
    {
        $this->loginAs('librarian');

        $this->get('/about?lang=en')->assertOk()->assertSee('KazUTB Smart Library', false);
        $this->get('/contacts?lang=en')->assertOk()->assertSee('KazUTB Smart Library', false);
    }

    public function test_admin_can_view_public_about_and_contacts(): void
    {
        $this->loginAs('admin');

        $this->get('/about?lang=en')->assertOk()->assertSee('KazUTB Smart Library', false);
        $this->get('/contacts?lang=en')->assertOk()->assertSee('KazUTB Smart Library', false);
    }
}
