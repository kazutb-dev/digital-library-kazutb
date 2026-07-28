<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * Wave 1 — Public Shell IA / Localization / Account-cleanup assertions.
 *
 * Locks in the Wave 1 contract:
 *  - /account is no longer linked from the public shell (navbar/footer).
 *  - /dashboard is the canonical authenticated user landing.
 *  - All major public surfaces are discoverable through navbar + footer.
 *  - Global language switcher is visible (not sr-only) and trilingual.
 *  - "Institution" disclosure groups About / Leadership / Rules / Contacts.
 */
class PublicShellIATest extends TestCase
{
    public function test_navbar_exposes_full_primary_ia_on_a_public_page(): void
    {
        $response = $this->get('/?lang=ru');
        $response->assertOk();

        // Primary nav links — every major public surface must be reachable.
        foreach (['/catalog', '/discover', '/resources', '/news', '/events'] as $href) {
            $response->assertSee('href="'.$href.'"', false);
        }

        // Institution disclosure hosts About / Leadership / Rules / Contacts.
        foreach (['/about', '/leadership', '/rules', '/contacts'] as $href) {
            $response->assertSee('href="'.$href.'"', false);
        }

        // Authenticated landing route.
        $response->assertSee('href="/dashboard"', false);
    }

    public function test_navbar_no_longer_links_to_legacy_account_route(): void
    {
        $response = $this->get('/?lang=ru');
        $response->assertOk()
            ->assertDontSee('href="/account"', false)
            ->assertDontSee('href="/account?', false);
    }

    public function test_footer_exposes_four_column_information_architecture(): void
    {
        $response = $this->get('/?lang=ru');
        $response->assertOk()
            ->assertSee('Навигация')
            ->assertSee('Обновления')
            ->assertSee('Институт')
            ->assertSee('Поддержка');
    }

    public function test_locale_switcher_is_visibly_rendered_in_navbar(): void
    {
        $response = $this->get('/?lang=ru');
        $response->assertOk()
            ->assertSee('data-locale-switcher', false)
            // sr-only marker must not be on the switcher anymore.
            ->assertDontSee('class="sr-only" data-locale-switcher', false);
    }

    public function test_locale_switcher_offers_all_three_languages_with_route_preservation(): void
    {
        $response = $this->get('/about?lang=ru');
        $response->assertOk()
            // fullUrlWithQuery must preserve the current path (/about) for every locale.
            ->assertSee('/about?lang=kk', false)
            ->assertSee('/about?lang=en', false)
            ->assertSee('/about?lang=ru', false);
    }

    public function test_post_login_destination_for_member_is_dashboard(): void
    {
        // already-authenticated user hitting /login is bounced to /dashboard, not /account.
        $session = [
            'library.user' => [
                'id' => 'qa-reader-001',
                'name' => 'QA Reader',
                'email' => 'qa-reader@digital-library.demo',
                'login' => 'qa_reader',
                'ad_login' => 'qa_reader',
                'role' => 'reader',
                'profile_type' => 'student',
            ],
        ];

        $this->withSession($session)->get('/login')->assertRedirect('/dashboard');
        $this->withSession($session)->get('/login?lang=en')->assertRedirect('/dashboard?lang=en');
        $this->withSession($session)->get('/login?lang=kk')->assertRedirect('/dashboard?lang=kk');
    }

    public function test_legacy_account_route_remains_for_backward_compatibility(): void
    {
        // Wave 1 retains /account as a hidden compat surface for tests + bookmarks.
        // It must still respond, but it must NOT be advertised by the shell.
        $session = [
            'library.user' => [
                'id' => 'qa-reader-001',
                'name' => 'QA Reader',
                'email' => 'qa-reader@digital-library.demo',
                'login' => 'qa_reader',
                'ad_login' => 'qa_reader',
                'role' => 'reader',
                'profile_type' => 'student',
            ],
        ];

        $this->withSession($session)->get('/account')->assertOk();
    }

    public function test_navbar_localizes_institution_label_in_all_three_languages(): void
    {
        $cases = [
            'ru' => ['/about?lang=ru', 'Об институте'],
            'kk' => ['/about?lang=kk', 'Институт туралы'],
            'en' => ['/about?lang=en', 'Institution'],
        ];

        foreach ($cases as $locale => [$url, $label]) {
            $response = $this->get($url);
            $response->assertOk()->assertSee($label);
        }
    }

    public function test_footer_localizes_column_headings_in_all_three_languages(): void
    {
        $kk = $this->get('/?lang=kk');
        $kk->assertOk()
            ->assertSee('Жаңартулар')
            ->assertSee('Институт');

        $en = $this->get('/?lang=en');
        $en->assertOk()
            ->assertSee('Updates')
            ->assertSee('Institution');
    }
}
