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
        foreach (['/catalog', '/discover', '/resources', '/repository', '/news', '/events'] as $href) {
            $response->assertSee('href="'.$href.'?lang=ru"', false);
        }

        // Institution disclosure hosts About / Leadership / Rules / Contacts.
        foreach (['/about', '/leadership', '/rules', '/contacts'] as $href) {
            $response->assertSee('href="'.$href.'?lang=ru"', false);
        }

        // A guest is offered sign-in; the dashboard link is rendered only
        // after authentication and must not be advertised as a public route.
        $response->assertSee('href="/login?lang=ru"', false);
        $response->assertDontSee('href="/dashboard?lang=ru"', false);
    }

    public function test_header_contacts_menu_exposes_contacts_rules_and_leadership(): void
    {
        $response = $this->get('/rules?lang=ru');

        $response->assertOk()
            ->assertSee('class="hdr-disclosure hdr-contact-nav"', false)
            ->assertSee('Контакты и информация о библиотеке', false)
            ->assertSee('href="/contacts?lang=ru"', false)
            ->assertSee('href="/rules?lang=ru"', false)
            ->assertSee('href="/leadership?lang=ru"', false)
            ->assertSee('data-library-info-link="rules"', false);
    }

    public function test_library_information_pages_have_sound_document_structure_in_every_locale(): void
    {
        foreach (['contacts', 'rules', 'leadership'] as $page) {
            foreach (['ru', 'kk', 'en'] as $locale) {
                $response = $this->get('/'.$page.'?lang='.$locale);
                $response->assertOk();

                $document = new \DOMDocument();
                $previous = libxml_use_internal_errors(true);
                $document->loadHTML($response->getContent());
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
                $xpath = new \DOMXPath($document);

                $this->assertSame(1, $xpath->query('//h1')->length, $page.' '.$locale.' must expose exactly one h1.');
                $this->assertSame(0, $xpath->query('//*[self::h1 or self::h2 or self::h3][not(normalize-space())]')->length, $page.' '.$locale.' must not expose empty headings.');

                $ids = [];
                foreach ($xpath->query('//*[@id]') as $node) {
                    $id = $node->getAttribute('id');
                    $this->assertArrayNotHasKey($id, $ids, $page.' '.$locale.' contains duplicate id #'.$id.'.');
                    $ids[$id] = true;
                }

                foreach ($xpath->query('//a[starts-with(@href, "#")]') as $link) {
                    $target = ltrim($link->getAttribute('href'), '#');
                    if ($target !== '') {
                        $this->assertArrayHasKey($target, $ids, $page.' '.$locale.' links to missing anchor #'.$target.'.');
                    }
                }
            }
        }
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
            ->assertSee('О библиотеке')
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
            ->assertSee('action="'.route('locale.update').'"', false)
            ->assertSee('name="return_to" value="http://localhost/about?lang=ru"', false);

        foreach (['kk', 'ru', 'en'] as $locale) {
            $response->assertSee('name="locale" value="'.$locale.'"', false);
        }
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
        $this->withSession($session)->get('/login?lang=kk')->assertRedirect('/dashboard');
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
