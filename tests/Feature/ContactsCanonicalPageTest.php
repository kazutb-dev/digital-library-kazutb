<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Tests\TestCase;

/**
 * Phase 3 Cluster B.6 — canonical-exact /contacts page.
 *
 * Locks the public-shell redesign, source-backed official contact data, the
 * one verified staff profile, and the authenticated inquiry flow. Unverified
 * rooms, people, and invented research/technical channels must stay absent.
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
        $response->assertSee('Kazakh University of Technology and Business named after K. Kulazhanov', false);
        $response->assertSee('Official University Contact Details.', false);
        $response->assertSee('Contact channels', false);
        $response->assertSee('Scientific Library', false);
        $response->assertSee('General university contact', false);
        $response->assertSee('Submit an official request', false);
        $response->assertSee('Physical Location', false);
        $response->assertSee('info@kaztbu.edu.kz', false);
        $response->assertSee('Library staff', false);
        $response->assertSee('Панкей Ж.', false);
    }

    public function test_contacts_page_renders_all_canonical_sections(): void
    {
        $response = $this->get('/contacts?lang=en');

        $response->assertOk();
        $response->assertSee('data-section="contacts-canonical-hero"', false);
        $response->assertSee('data-section="contacts-canonical-support"', false);
        $response->assertSee('data-section="contacts-canonical-inquiry-form"', false);
        $response->assertSee('data-section="contacts-canonical-location"', false);
        $response->assertSee('data-section="contacts-canonical-staff"', false);
        $response->assertSee('data-section="contacts-canonical-visit-rules"', false);
        $response->assertDontSee('data-section="contacts-canonical-fund-rooms"', false);
    }

    public function test_contacts_page_renders_canonical_sections_in_order(): void
    {
        $response = $this->get('/contacts?lang=en');

        $response->assertOk();
        $response->assertSeeInOrder([
            'data-section="contacts-canonical-hero"',
            'data-section="contacts-canonical-support"',
            'data-section="contacts-canonical-location"',
            'data-section="contacts-canonical-inquiry-form"',
            'data-section="contacts-canonical-staff"',
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

    public function test_contacts_page_renders_one_verified_staff_profile_and_no_unverified_rooms(): void
    {
        $response = $this->get('/contacts?lang=en');

        $response->assertOk();
        $response->assertDontSee('data-section="contacts-canonical-fund-rooms"', false);
        $response->assertDontSee('data-room-slot', false);
        $response->assertDontSee('Room 1/200', false);
        $response->assertDontSee('Room 1/202', false);
        $response->assertDontSee('Room 1/203', false);
        $response->assertSee('data-section="contacts-canonical-staff"', false);
        $response->assertSee('data-staff-slug="pankey-zh"', false);
        $response->assertSee('Панкей Ж.', false);
        $response->assertDontSee('Корпешова Эльмира Мауткановна', false);
        $response->assertDontSee('Сайлаубек Айман Бастарбекқызы', false);
        $this->assertSame(1, substr_count($response->getContent(), 'data-staff-slot'));
    }

    public function test_contacts_page_uses_only_the_two_source_backed_contact_channels(): void
    {
        $response = $this->get('/contacts?lang=en');
        $content = $response->getContent();

        $response->assertOk();
        $response->assertSee('data-test-id="contacts-canonical-channel-email-library"', false);
        $response->assertSee('zh.pankey@kaztbu.edu.kz', false);
        $response->assertSee('data-test-id="contacts-canonical-channel-email-general"', false);
        $response->assertSee('info@kaztbu.edu.kz', false);
        $response->assertSee('+7 (7172) 69-70-60', false);
        $response->assertDontSee('contacts-canonical-channel-email-research', false);
        $response->assertDontSee('contacts-canonical-channel-email-technical', false);
        $response->assertDontSee('library@kazutb.edu.kz', false);
        $response->assertDontSee('support@kazutb.edu.kz', false);
        $response->assertDontSee('+7 (7172) 64-58-58', false);
        $this->assertSame(2, substr_count($content, 'data-support-channel'));

        $this->assertSame(1, preg_match(
            '/<article[^>]*data-channel-slug="library"[^>]*>.*?<\/article>/s',
            $content,
            $libraryCard,
        ));
        $this->assertStringContainsString('zh.pankey@kaztbu.edu.kz', $libraryCard[0]);
        $this->assertStringNotContainsString('tel:', $libraryCard[0]);
    }

    public function test_guest_inquiry_uses_the_existing_authenticated_message_flow(): void
    {
        $response = $this->get('/contacts?lang=en');

        $response->assertOk();
        $response->assertSee('data-test-id="contacts-canonical-inquiry-cta"', false);
        $response->assertSee('Sign in to submit a request', false);
        $response->assertSee('/login?redirect=', false);
        $response->assertDontSee('action="mailto:', false);
    }

    public function test_contacts_page_renders_directions_link(): void
    {
        $response = $this->get('/contacts?lang=en');

        $response->assertOk();
        $response->assertSee('data-test-id="contacts-canonical-directions"', false);
        $response->assertSee('https://www.google.com/maps/search/?api=1&amp;query=', false);
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
        $response->assertSee('+7 (7172) 69-70-60', false);
        $response->assertSee('info@kaztbu.edu.kz', false);
        $response->assertSee('Opening hours', false);
        $response->assertSee('Confirmed time window', false);
        $response->assertSee('08:30 – 17:30', false);
        $response->assertSee('Confirm working days on the official page', false);
        $response->assertSee('data-test-id="contacts-official-hours-source"', false);
        $response->assertSee('https://www.kaztbu.edu.kz/biblioteka', false);
        $response->assertDontSee('Mon – Fri', false);
        $response->assertDontSee('+7 (7172) 64-58-58', false);
    }

    public function test_contacts_ru_variant_renders_canonical_copy(): void
    {
        $response = $this->get('/contacts?lang=ru');

        $response->assertOk();
        $response->assertSee('Официальные контакты', false);
        $response->assertSee('Контактные каналы', false);
        $response->assertSee('Научная библиотека', false);
        $response->assertSee('zh.pankey@kaztbu.edu.kz', false);
        $response->assertSee('Общий контакт университета', false);
        $response->assertSee('Отправить официальный запрос', false);
        $response->assertSee('info@kaztbu.edu.kz', false);
        $response->assertSee('Сотрудники библиотеки', false);
        $response->assertSee('Панкей Ж.', false);
        $response->assertSee('href="/rules?lang=ru"', false);
        $response->assertSee('href="/leadership?lang=ru"', false);
    }

    public function test_contacts_kk_variant_renders_canonical_copy_and_preserves_lang(): void
    {
        $response = $this->get('/contacts?lang=kk');

        $response->assertOk();
        $response->assertSee('ресми байланыс арналары', false);
        $response->assertSee('Байланыс арналары', false);
        $response->assertSee('Ғылыми кітапхана', false);
        $response->assertSee('zh.pankey@kaztbu.edu.kz', false);
        $response->assertSee('Университеттің жалпы байланыс арнасы', false);
        $response->assertSee('info@kaztbu.edu.kz', false);
        $response->assertSee('Кітапхана қызметкерлері', false);
        $response->assertSee('Панкей Ж.', false);
        $response->assertSee('href="/rules"', false);
        $response->assertSee('href="/leadership"', false);
    }

    public function test_contacts_page_guest_navbar_shows_sign_in(): void
    {
        $response = $this->get('/contacts?lang=en');

        $response->assertOk();
        $response->assertSee('Sign in', false);
    }

    public function test_contacts_page_authenticated_reader_navbar_shows_sign_out(): void
    {
        $response = $this->withSession(['library.user' => [
            'id' => 'qa-reader-001',
            'name' => 'QA Reader',
            'role' => 'reader',
        ]])->get('/contacts?lang=en');

        $response->assertOk();
        $response->assertSee('Open requests', false);
        $response->assertSee('href="/dashboard/messages?lang=en"', false);
        $response->assertSee('Sign out', false);
    }
}
