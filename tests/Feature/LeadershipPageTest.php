<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Tests\TestCase;

/**
 * Phase 3 Cluster B.1 — Public Leadership page (/leadership).
 *
 * /leadership renders resources/views/leadership.blade.php extending
 * layouts.public. Only claims confirmed by the official library source are
 * rendered; unsupported mandate, reporting-line, and biography copy stays out.
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
        $response = $this->get('/leadership?lang=kk');

        $response->assertOk();
        $response->assertSee('Кітапхана басшылығы', false);
    }

    public function test_leadership_page_renders_all_core_section_markers(): void
    {
        $response = $this->get('/leadership?lang=ru');

        $response->assertOk();
        $response->assertSee('data-section="leadership-header"', false);
        $response->assertSee('data-section="leadership-directory"', false);
        $response->assertSee('data-section="leadership-support-cta"', false);
        $response->assertDontSee('data-section="leadership-mandate"', false);
        $response->assertDontSee('data-test-id="leadership-reports-to"', false);
        $response->assertDontSee('data-test-id="leadership-last-reviewed"', false);
        $response->assertDontSee('2026-04-22', false);
    }

    public function test_leadership_directory_renders_role_profile_cards(): void
    {
        $response = $this->get('/leadership?lang=ru');

        $response->assertOk();
        // Only the profile confirmed by the official library page is public.
        $response->assertSee('data-leadership-slug="director"', false);
        $this->assertSame(1, substr_count($response->getContent(), 'data-leadership-slug='));
        $response->assertSee('Директор библиотеки', false);
        $response->assertSee('Панкей Ж.', false);
        $response->assertSee('https://www.kaztbu.edu.kz/biblioteka', false);
        $response->assertSee('Официальный источник', false);
        $response->assertDontSee('Корпешова', false);
        $response->assertDontSee('Сайлаубек', false);
        $response->assertDontSee('/images/staff/', false);
    }

    public function test_leadership_page_renders_russian_locale_by_default(): void
    {
        $response = $this->get('/leadership?lang=ru');

        $response->assertOk();
        $response->assertSee('Руководство библиотеки', false);
        $response->assertSee('сведения, подтверждённые официальной страницей', false);
        $response->assertDontSee('Ответственность библиотеки', false);
        $response->assertDontSee('Администрация университета', false);
        $response->assertSee('Общие обращения и академические запросы', false);
    }

    public function test_leadership_page_renders_kazakh_locale_variant(): void
    {
        $response = $this->get('/leadership?lang=kk');

        $response->assertOk();
        $response->assertSee('Кітапхана басшылығы', false);
        $response->assertSee('Кітапхана директоры', false);
        $response->assertSee('Ресми дереккөз', false);
        $response->assertDontSee('Университет әкімшілігі', false);
        $response->assertSee('Байланыс бетіне өту', false);
    }

    public function test_leadership_page_renders_english_locale_variant(): void
    {
        $response = $this->get('/leadership?lang=en');

        $response->assertOk();
        $response->assertSee('Library leadership', false);
        $response->assertSee('Library Director', false);
        $response->assertSee('Панкей Ж.', false);
        $response->assertSee('Official source', false);
        $response->assertDontSee('Pankey Zh.', false);
        $response->assertDontSee('Lead Librarian', false);
        $response->assertDontSee('Librarian, Technology Faculty Reading Room', false);
        $response->assertDontSee('University administration', false);
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
        $this->get('/leadership?lang=ru')->assertOk()->assertSee('/leadership?lang=ru', false);

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
        $response = $this->withSession(['library.user' => [
            'id' => 'qa-reader-001',
            'name' => 'QA Reader',
            'role' => 'reader',
        ]])->get('/leadership?lang=en');

        $response->assertOk();
        $response->assertSee('Library leadership', false);
        $response->assertSee('Sign out', false);
    }

    public function test_librarian_can_view_leadership_page(): void
    {
        $this->loginAs('librarian');

        $this->get('/leadership?lang=en')
            ->assertOk()
            ->assertSee('Library leadership', false);
    }

    public function test_admin_can_view_leadership_page(): void
    {
        $this->loginAs('admin');

        $this->get('/leadership?lang=en')
            ->assertOk()
            ->assertSee('Library leadership', false);
    }
}
