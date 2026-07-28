<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Tests\TestCase;

/**
 * Phase 3 Cluster B.1 — Public Leadership page (/leadership).
 *
 * /leadership renders resources/views/leadership.blade.php extending
 * layouts.public. Content is driven by the $leadershipSeedProvider closure
 * in routes/web.php, with trilingual ru/kk/en parity.
 *
 * Per Cluster B Content Contract §8 the route is NOT added to the primary
 * navbar; footer exposes a "Руководство / Басшылық / Leadership" link.
 */
class LeadershipPageTest extends TestCase
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

    public function test_guest_can_access_leadership_page(): void
    {
        $response = $this->get('/leadership');

        $response->assertOk();
        $response->assertSee('KazUTB Smart Library', false);
    }

    public function test_leadership_page_renders_all_core_section_markers(): void
    {
        $response = $this->get('/leadership');

        $response->assertOk();
        // Frozen section IDs per Cluster B Content Contract §1.
        $response->assertSee('data-section="leadership-header"', false);
        $response->assertSee('data-section="leadership-mandate"', false);
        $response->assertSee('data-section="leadership-directory"', false);
        $response->assertSee('data-section="leadership-support-cta"', false);
        // "Reports to" metadata row required by contract §1 §2.
        $response->assertSee('data-test-id="leadership-reports-to"', false);
        // last_reviewed_at rendered as a <time> element.
        $response->assertSee('data-test-id="leadership-last-reviewed"', false);
    }

    public function test_leadership_directory_renders_role_profile_cards(): void
    {
        $response = $this->get('/leadership');

        $response->assertOk();
        // All three seeded leadership slots present and addressable.
        $response->assertSee('data-leadership-slug="director"', false);
        $response->assertSee('data-leadership-slug="digital-collections"', false);
        $response->assertSee('data-leadership-slug="reader-services"', false);
        // Default (ru) role titles rendered.
        $response->assertSee('Директор библиотеки', false);
        $response->assertSee('Заведующий электронными коллекциями', false);
        $response->assertSee('Координатор читательских сервисов', false);
    }

    public function test_leadership_page_renders_russian_locale_by_default(): void
    {
        $response = $this->get('/leadership');

        $response->assertOk();
        $response->assertSee('Руководство KazUTB Smart Library', false);
        $response->assertSee('Ответственность библиотеки', false);
        $response->assertSee('Администрация КазУТБ', false);
        $response->assertSee('Общие обращения и академические запросы', false);
    }

    public function test_leadership_page_renders_kazakh_locale_variant(): void
    {
        $response = $this->get('/leadership?lang=kk');

        $response->assertOk();
        $response->assertSee('KazUTB Smart Library басшылығы', false);
        $response->assertSee('Кітапхана директоры', false);
        $response->assertSee('КазУТБ әкімшілігі', false);
        $response->assertSee('Байланыс бетіне өту', false);
    }

    public function test_leadership_page_renders_english_locale_variant(): void
    {
        $response = $this->get('/leadership?lang=en');

        $response->assertOk();
        $response->assertSee('Leadership of KazUTB Smart Library', false);
        $response->assertSee('Library Director', false);
        $response->assertSee('Head of Digital Collections', false);
        $response->assertSee('Reader Services Coordinator', false);
        $response->assertSee('KazUTB university administration', false);
        $response->assertSee('Open contacts', false);
    }

    public function test_support_cta_points_to_contacts_and_preserves_lang(): void
    {
        $response = $this->get('/leadership?lang=en');

        $response->assertOk();
        // Per contract §1 §4 CTA must target /contacts.
        $response->assertSee('href="/contacts?lang=en"', false);
    }

    public function test_footer_exposes_leadership_link_in_all_locales(): void
    {
        $this->get('/leadership')->assertOk()->assertSee('/leadership', false);

        $kkResponse = $this->get('/leadership?lang=kk');
        $kkResponse->assertOk();
        $kkResponse->assertSee('Басшылық', false);

        $enResponse = $this->get('/leadership?lang=en');
        $enResponse->assertOk();
        $enResponse->assertSee('>Leadership<', false);
    }

    public function test_leadership_page_does_not_use_external_portrait_urls(): void
    {
        // Cluster B Content Contract §9 R-B1.1 — no external CDN URLs for portraits.
        foreach (['ru', 'kk', 'en'] as $locale) {
            $response = $this->get('/leadership?lang=' . $locale);
            $response->assertOk();
            $response->assertDontSee('lh3.googleusercontent.com', false);
            $response->assertDontSee('aida-public', false);
        }
    }

    public function test_leadership_page_does_not_reintroduce_legacy_brand(): void
    {
        $response = $this->get('/leadership?lang=en');

        $response->assertOk();
        $response->assertDontSee('Athenaeum', false);
        $response->assertDontSee('Curator Archive', false);
        $response->assertDontSee('KazTBU Digital Library', false);
        $response->assertDontSee('KazUTB Digital Library', false);
    }

    public function test_primary_navbar_does_not_gain_leadership_item(): void
    {
        // Per Cluster B Content Contract §8: primary navbar stays flat with
        // the existing 5 items. /leadership is surfaced via the footer only.
        $response = $this->get('/leadership?lang=en');

        $response->assertOk();
        $response->assertDontSee(
            '<a href="/leadership?lang=en" class="px-3 py-2',
            false
        );
    }

    public function test_authenticated_reader_can_view_leadership_page(): void
    {
        $this->loginAs('student');

        $response = $this->get('/leadership?lang=en');

        $response->assertOk();
        $response->assertSee('Leadership of KazUTB Smart Library', false);
        $response->assertSee('Sign out', false);
    }

    public function test_librarian_can_view_leadership_page(): void
    {
        $this->loginAs('librarian');

        $this->get('/leadership?lang=en')
            ->assertOk()
            ->assertSee('Leadership of KazUTB Smart Library', false);
    }

    public function test_admin_can_view_leadership_page(): void
    {
        $this->loginAs('admin');

        $this->get('/leadership?lang=en')
            ->assertOk()
            ->assertSee('Leadership of KazUTB Smart Library', false);
    }
}
