<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Tests\TestCase;

/**
 * Phase 3 Cluster B.6 — canonical-exact /contacts page.
 *
 * Mirrors docs/design-exports/contacts_canonical + integrates the three
 * v1 fund rooms (1/200, 1/202, 1/203) as public wayfinding truth. The
 * previous shared-view activePage='contacts' branch on about.blade.php
 * and the B.3 contacts-location/contacts-fund-rooms/contacts-visit-notes
 * markers are retired.
 */
class ContactsCanonicalPageTest extends TestCase
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

    public function test_guest_can_view_canonical_contacts_page(): void
    {
        $response = $this->get('/contacts?lang=en');

        $response->assertOk();
        $response->assertSee('KazUTB Smart Library', false);
        $response->assertSee('Direct Inquiries', false);
        $response->assertSee('Academic Support', false);
        $response->assertSee('Support Channels', false);
        $response->assertSee('Submit an Inquiry', false);
        $response->assertSee('Physical Location', false);
        $response->assertSee('Fund Guidance &amp; Wayfinding', false);
    }

    public function test_contacts_page_renders_all_canonical_sections(): void
    {
        $response = $this->get('/contacts?lang=en');

        $response->assertOk();
        $response->assertSee('data-section="contacts-canonical-hero"', false);
        $response->assertSee('data-section="contacts-canonical-support"', false);
        $response->assertSee('data-section="contacts-canonical-inquiry-form"', false);
        $response->assertSee('data-section="contacts-canonical-location"', false);
        $response->assertSee('data-section="contacts-canonical-fund-guidance"', false);
        $response->assertSee('data-section="contacts-canonical-visit-rules"', false);
    }

    public function test_contacts_page_renders_canonical_sections_in_order(): void
    {
        $response = $this->get('/contacts?lang=en');

        $response->assertOk();
        $response->assertSeeInOrder([
            'data-section="contacts-canonical-hero"',
            'data-section="contacts-canonical-support"',
            'data-section="contacts-canonical-inquiry-form"',
            'data-section="contacts-canonical-location"',
            'data-section="contacts-canonical-fund-guidance"',
            'data-section="contacts-canonical-visit-rules"',
        ], false);
    }

    public function test_contacts_page_does_not_render_legacy_shell_markers(): void
    {
        $response = $this->get('/contacts?lang=en');

        $response->assertOk();
        $response->assertDontSee('data-section="contacts-summary"', false);
        $response->assertDontSee('data-section="about-mission"', false);
        $response->assertDontSee('data-section="librarian-on-duty"', false);
        $response->assertDontSee('data-section="contacts-location"', false);
        $response->assertDontSee('data-section="contacts-fund-rooms"', false);
        $response->assertDontSee('data-section="contacts-visit-notes"', false);
        $response->assertDontSee('data-section="catalog-cta"', false);
        $response->assertDontSee('data-section="about-hero"', false);
        $response->assertDontSee('Athenaeum', false);
        $response->assertDontSee('KazUTB Digital Library', false);
    }

    public function test_contacts_page_renders_three_v1_fund_rooms_in_order(): void
    {
        $response = $this->get('/contacts?lang=en');

        $response->assertOk();
        $response->assertSee('data-room-code="1/200"', false);
        $response->assertSee('data-room-code="1/202"', false);
        $response->assertSee('data-room-code="1/203"', false);
        $response->assertSeeInOrder([
            'data-room-code="1/200"',
            'data-room-code="1/202"',
            'data-room-code="1/203"',
        ], false);
        $response->assertSee('Technology fund', false);
        $response->assertSee('College fund', false);
        $response->assertSee('Economics fund', false);
    }

    public function test_contacts_page_renders_both_support_channel_emails(): void
    {
        $response = $this->get('/contacts?lang=en');

        $response->assertOk();
        $response->assertSee('data-test-id="contacts-canonical-channel-email-research"', false);
        $response->assertSee('data-test-id="contacts-canonical-channel-email-technical"', false);
        $response->assertSee('library@kazutb.edu.kz', false);
        $response->assertSee('support@kazutb.edu.kz', false);
    }

    public function test_contacts_page_renders_inquiry_form_with_mailto_action(): void
    {
        $response = $this->get('/contacts?lang=en');

        $response->assertOk();
        $response->assertSee('data-test-id="contacts-canonical-inquiry-form"', false);
        $response->assertSee('data-test-id="contacts-canonical-inquiry-submit"', false);
        $response->assertSee('action="mailto:library@kazutb.edu.kz"', false);
    }

    public function test_contacts_page_renders_directions_link_and_map_placeholder(): void
    {
        $response = $this->get('/contacts?lang=en');

        $response->assertOk();
        $response->assertSee('data-test-id="contacts-canonical-directions"', false);
        $response->assertSee('https://www.google.com/maps/search/?api=1&amp;query=', false);
        $response->assertSee('data-test-id="contacts-canonical-map"', false);
    }

    public function test_contacts_page_renders_cross_links_preserving_lang(): void
    {
        $response = $this->get('/contacts?lang=en');

        $response->assertOk();
        $response->assertSee('data-test-id="contacts-canonical-link-rules"', false);
        $response->assertSee('data-test-id="contacts-canonical-link-leadership"', false);
        $response->assertSee('href="/rules?lang=en"', false);
        $response->assertSee('href="/leadership?lang=en"', false);
    }

    public function test_contacts_page_renders_address_and_hours(): void
    {
        $response = $this->get('/contacts?lang=en');

        $response->assertOk();
        $response->assertSee('37A Kayym Mukhamedkhanov Street, Astana', false);
        $response->assertSee('+7 (7172) 64-58-58', false);
        $response->assertSee('Opening hours', false);
        $response->assertSee('Mon – Fri', false);
    }

    public function test_contacts_ru_variant_renders_canonical_copy(): void
    {
        $response = $this->get('/contacts?lang=ru');

        $response->assertOk();
        $response->assertSee('Прямые обращения', false);
        $response->assertSee('Каналы поддержки', false);
        $response->assertSee('Отправить запрос', false);
        $response->assertSee('Технологический фонд', false);
        $response->assertSee('Фонд колледжа', false);
        $response->assertSee('Экономический фонд', false);
        $response->assertSee('href="/rules"', false);
        $response->assertSee('href="/leadership"', false);
    }

    public function test_contacts_kk_variant_renders_canonical_copy_and_preserves_lang(): void
    {
        $response = $this->get('/contacts?lang=kk');

        $response->assertOk();
        $response->assertSee('Тікелей сұраулар', false);
        $response->assertSee('Қолдау арналары', false);
        $response->assertSee('Технологиялық қор', false);
        $response->assertSee('Колледж қоры', false);
        $response->assertSee('Экономикалық қор', false);
        $response->assertSee('href="/rules?lang=kk"', false);
        $response->assertSee('href="/leadership?lang=kk"', false);
    }

    public function test_contacts_page_guest_navbar_shows_sign_in(): void
    {
        $response = $this->get('/contacts?lang=en');

        $response->assertOk();
        $response->assertSee('Sign in', false);
    }

    public function test_contacts_page_authenticated_reader_navbar_shows_sign_out(): void
    {
        $this->loginAs('student');

        $response = $this->get('/contacts?lang=en');

        $response->assertOk();
        $response->assertSee('KazUTB Smart Library', false);
        $response->assertSee('Sign out', false);
    }
}
