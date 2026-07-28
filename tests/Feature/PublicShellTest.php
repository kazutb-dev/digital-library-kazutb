<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicShellTest extends TestCase
{
    public function test_homepage_exposes_canonical_sections(): void
    {
        $response = $this->get('/');

        $response
            ->assertOk()
            ->assertSee('data-section="homepage-canonical-hero"', false)
            ->assertSee('data-section="homepage-canonical-collections"', false)
            ->assertSee('data-section="homepage-canonical-services"', false);
    }

    public function test_resources_page_uses_accessible_shared_public_shell(): void
    {
        $response = $this->get('/resources?lang=ru');

        $response
            ->assertOk()
            ->assertSee('class="site-shell"', false)
            ->assertSee('href="#main-content"', false)
            ->assertSee('id="main-content"', false)
            ->assertSee('aria-label="Основная навигация сайта"', false);
    }

    public function test_resources_page_exposes_language_switcher(): void
    {
        $response = $this->get('/resources');

        $response
            ->assertOk()
            ->assertSee('data-locale-switcher', false)
            ->assertSee('?lang=kk', false)
            ->assertSee('?lang=en', false);
    }

    public function test_contacts_page_can_render_in_english(): void
    {
        // Post-Cluster-B.6 cleanup: /contacts is now a standalone canonical view
        // (resources/views/contacts.blade.php). The legacy brand string
        // "KazTBU Digital Library" has been replaced by the canonical
        // "KazUTB Smart Library" brand used consistently across the public layer,
        // and the legacy "How to reach the library" helper copy was superseded by
        // the canonical "Support Channels" section heading.
        $response = $this->get('/contacts?lang=en');

        $response
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('KazUTB Smart Library')
            ->assertSee('Support Channels');
    }

    public function test_public_shell_localizes_navbar_actions_in_all_supported_languages(): void
    {
        $cases = [
            'ru' => ['/resources?lang=ru', ['Главная', 'Каталог', 'Ресурсы', 'Подборка', 'Войти', 'Открыть кабинет']],
            'kk' => ['/resources?lang=kk', ['Басты бет', 'Каталог', 'Ресурстар', 'Іріктеме', 'Кіру', 'Кабинетті ашу']],
            'en' => ['/resources?lang=en', ['Home', 'Catalog', 'Resources', 'Shortlist', 'Sign in', 'Open portal']],
        ];

        foreach ($cases as [$url, $expectedStrings]) {
            $response = $this->get($url);

            $response->assertOk();

            foreach ($expectedStrings as $expected) {
                $response->assertSee($expected);
            }
        }
    }

    public function test_shortlist_page_hero_copy_is_fully_localized(): void
    {
        $this->get('/shortlist?lang=kk')
            ->assertOk()
            ->assertSee('Зерттеу іріктемесі')
            ->assertDontSee('Исследовательская подборка');

        $this->get('/shortlist?lang=en')
            ->assertOk()
            ->assertSee('Research Shortlist')
            ->assertDontSee('Исследовательская подборка');
    }

    public function test_authenticated_account_page_renders_localized_shell_for_each_language(): void
    {
        $session = [
            'library.user' => [
                'id' => 'qa-reader-001',
                'name' => 'QA Faculty',
                'email' => 'qa-faculty@digital-library.demo',
                'login' => 'qa_reader',
                'ad_login' => 'qa_reader',
                'role' => 'reader',
                'profile_type' => 'teacher',
            ],
        ];

        $cases = [
            'ru' => ['/account?lang=ru', 'Мои книги', 'Кабинет'],
            'kk' => ['/account?lang=kk', 'Менің кітаптарым', 'Кабинет'],
            'en' => ['/account?lang=en', 'My books', 'Account'],
        ];

        foreach ($cases as $locale => [$url, $heading, $navLabel]) {
            $response = $this->withSession($session)->get($url);

            $response
                ->assertOk()
                ->assertSee('<html lang="'.$locale.'">', false)
                ->assertSee($heading)
                ->assertSee($navLabel);
        }
    }
}
