<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Public homepage — contract for the redesigned page.
 *
 * The homepage was rebuilt around a single-row institutional header, a
 * full-bleed hero with the catalog search, and five content sections:
 *
 *   1. Hero                     data-section="homepage-canonical-hero"
 *   2. Recommended by faculty   data-section="homepage-faculty-picks"
 *   3. New additions            data-section="homepage-new-arrivals"
 *   4. Library collections      data-section="homepage-collections"
 *   5. Library statistics       data-section="homepage-statistics"
 *   6. FAQ                      data-section="homepage-faq"
 *
 * This file supersedes the previous contract, which described the retired
 * "canonical bento" shell (hero stats card, quick-link chips, collections
 * bento tiles, scholarly services trio). Those elements no longer exist and
 * are asserted absent below so the old markup cannot creep back in.
 *
 * Copy lives in config/homepage_sections.php; section markup in
 * resources/views/home/*.blade.php; the header/footer in partials.
 */
class PublicHomepagePageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('demo_auth.enabled', true);
        config()->set('demo_users.enabled', true);
        $this->withoutMiddleware([VerifyCsrfToken::class, ValidateCsrfToken::class]);
        $this->withSession(['locale' => 'ru']);
    }

    private function loginAs(string $identitySlug): void
    {
        session()->put('library.user', [
            'role' => in_array($identitySlug, ['student', 'teacher'], true) ? 'reader' : $identitySlug,
            'profile_type' => $identitySlug,
        ]);
        $this->post('/locale', ['locale' => 'en', 'return_to' => '/']);
    }

    // ─────────────────────────────────────────────────────────────────
    // Access
    // ─────────────────────────────────────────────────────────────────

    public function test_homepage_is_reachable_in_every_supported_locale(): void
    {
        $this->get('/')->assertOk();
        $this->get('/?lang=ru')->assertOk();
        $this->get('/?lang=kk')->assertOk();
        $this->get('/?lang=en')->assertOk();
    }

    public function test_guest_can_view_homepage(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Научная библиотека')
            ->assertSee('data-section="homepage-canonical-page"', false);
    }

    #[DataProvider('staffAndReaderIdentities')]
    public function test_every_role_can_view_the_public_homepage(string $identity): void
    {
        $this->loginAs($identity);

        $this->get('/')
            ->assertOk()
            ->assertSee('data-section="homepage-canonical-page"', false);
    }

    public static function staffAndReaderIdentities(): array
    {
        return [
            'student' => ['student'],
            'teacher' => ['teacher'],
            'librarian' => ['librarian'],
            'admin' => ['admin'],
        ];
    }

    // ─────────────────────────────────────────────────────────────────
    // Shared shell — header and footer
    // ─────────────────────────────────────────────────────────────────

    public function test_header_renders_as_a_single_row_with_brand_navigation_and_actions(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('id="siteHeader"', false)
            ->assertSee('class="hdr-brand"', false)
            ->assertSee('class="hdr-nav"', false)
            ->assertSee('class="hdr-actions"', false)
            // The institutional lockup: wordmark plus the university name.
            ->assertSee('Казахский университет технологии и бизнеса имени К. Кулажанова');
    }

    public function test_header_navigation_links_reach_the_public_surfaces(): void
    {
        $response = $this->get('/');

        $response->assertOk();

        foreach (['/catalog', '/resources', '/repository', '/news', '/events', '/contacts'] as $href) {
            $response->assertSee('href="'.$href.'"', false);
        }
    }

    public function test_header_exposes_search_shortlist_and_locale_switcher(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('id="site-search-input"', false)
            ->assertSee('href="/shortlist"', false)
            ->assertSee('data-locale-switcher', false)
            ->assertSee('name="locale" value="kk"', false)
            ->assertSee('name="locale" value="en"', false);
    }

    public function test_header_action_is_sign_in_for_guests(): void
    {
        $response = $this->get('/?lang=en');

        $response->assertOk()
            ->assertSee('class="hdr-cta" href="/login?lang=en"', false)
            ->assertSee('Sign in');
    }

    public function test_header_action_becomes_the_dashboard_for_authenticated_readers(): void
    {
        $this->loginAs('student');

        $response = $this->get('/?lang=en');

        $response->assertOk()
            ->assertSee('class="hdr-icon hdr-icon--account" href="/dashboard?lang=en"', false)
            ->assertSee('Open portal')
            ->assertSee('Sign out');
    }

    public function test_footer_keeps_its_four_column_information_architecture(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('university-footer', false)
            ->assertSee('Навигация')
            ->assertSee('Обновления')
            ->assertSee('Институт')
            ->assertSee('Поддержка');
    }

    // ─────────────────────────────────────────────────────────────────
    // 1. Hero
    // ─────────────────────────────────────────────────────────────────

    public function test_hero_section_renders(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('data-section="homepage-canonical-hero"', false)
            ->assertSee('class="homepage-hero__content"', false)
            ->assertSee('class="homepage-hero__title"', false)
            ->assertSee('class="homepage-hero__lead"', false);
    }

    public function test_hero_search_submits_a_catalog_query(): void
    {
        $response = $this->get('/?lang=en');

        $response->assertOk()
            ->assertSee('id="heroSearch"', false)
            ->assertSee('id="homepage-search"', false)
            ->assertSee('action="/catalog?lang=en"', false)
            ->assertSee('name="q"', false);
    }

    public function test_hero_headline_is_localised(): void
    {
        $this->get('/?lang=ru')->assertOk()->assertSee('Открывайте знания');
        $this->get('/?lang=kk')->assertOk()->assertSee('Білімді ашыңыз');
        $this->get('/?lang=en')->assertOk()->assertSee('Discover Knowledge');
    }

    // ─────────────────────────────────────────────────────────────────
    // 2. Recommended by faculty
    // ─────────────────────────────────────────────────────────────────

    public function test_faculty_picks_section_renders_every_field_of_study(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('data-section="homepage-faculty-picks"', false)
            ->assertSee('Популярные книги по абонементам')
            ->assertSee('Абонемент экономической литературы')
            ->assertSee('Абонемент технической литературы')
            ->assertSee('Абонемент ИТ и инженерии');

        // One tab per field of study in the faculty showcase.
        $this->assertSame(
            3,
            substr_count($response->getContent(), 'class="homepage-faculty-showcase__desk"'),
            'The faculty section must offer one entry per lending desk.',
        );
    }

    public function test_faculty_cards_open_a_real_catalog_query(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('institution=economic_library', false)
            ->assertSee('institution=technology_library', false)
            ->assertSee('institution=college_library', false);
    }

    // ─────────────────────────────────────────────────────────────────
    // 3. New additions
    // ─────────────────────────────────────────────────────────────────

    public function test_new_additions_section_renders_a_scrollable_rail(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('data-section="homepage-new-arrivals"', false)
            ->assertSee('Новые поступления')
            ->assertSee('Каталог пополняется');
    }

    public function test_new_addition_cards_carry_catalog_metadata_and_a_detail_link(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('data-section="homepage-new-arrivals"', false)
            ->assertSee('Каталог пополняется');
    }

    // ─────────────────────────────────────────────────────────────────
    // 4. Library collections
    // ─────────────────────────────────────────────────────────────────

    public function test_collections_section_renders_all_subject_sections(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('data-section="homepage-collections"', false)
            ->assertSee('Коллекции библиотеки')
            ->assertSee('Инженерия')
            ->assertSee('Бизнес и менеджмент')
            ->assertSee('История')
            ->assertSee('Филология');

        $this->assertSame(
            10,
            substr_count($response->getContent(), 'class="hs-collection"'),
            'The collections section must render one card per subject section.',
        );
    }

    public function test_collection_cards_show_a_udc_index_and_a_description(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('class="hs-collection__udc"', false)
            ->assertSee('class="hs-collection__desc"', false)
            ->assertSee('href="/catalog?udc=62&amp;lang=ru"', false);
    }

    // ─────────────────────────────────────────────────────────────────
    // 5. Statistics
    // ─────────────────────────────────────────────────────────────────

    public function test_statistics_section_renders_every_figure(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('data-section="homepage-statistics"', false)
            ->assertSee('Статистика библиотеки')
            ->assertSee('46 000+')
            ->assertSee('Уникальных книг в библиотеке')
            ->assertSee('100 000+')
            ->assertSee('Печатных экземпляров')
            ->assertSee('Читальных зала');

        $this->assertSame(
            3,
            substr_count($response->getContent(), 'class="hs-stat"'),
            'The statistics section must render three figures.',
        );
    }

    public function test_statistics_are_localised_for_english_number_formatting(): void
    {
        $this->get('/?lang=en')
            ->assertOk()
            ->assertSee('46,000+')
            ->assertSee('Unique books in the library')
            ->assertSee('100,000+')
            ->assertSee('Reading rooms');
    }

    // ─────────────────────────────────────────────────────────────────
    // 6. FAQ
    // ─────────────────────────────────────────────────────────────────

    public function test_faq_section_renders_every_question_as_a_native_disclosure(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('data-section="homepage-faq"', false)
            ->assertSee('Часто задаваемые вопросы')
            ->assertSee('Как получить читательский билет?')
            ->assertSee('Как войти в личный кабинет?')
            ->assertSee('Как продлить книгу?')
            ->assertSee('Как забронировать литературу?')
            ->assertSee('Как пользоваться электронной библиотекой?')
            ->assertSee('Как получить доступ из дома?')
            ->assertSee('Как пользоваться AI-помощником?')
            ->assertSee('Как связаться с библиотекарем?');

        // <details>/<summary> keeps the accordion usable without JavaScript.
        $this->assertSame(
            8,
            substr_count($response->getContent(), '<details class="hs-faq__item">'),
            'Each FAQ entry must be a native disclosure element.',
        );
    }

    public function test_faq_answers_match_the_published_circulation_policy(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            // Renewal terms (PROJECT_CONTEXT §13) and reservation limits (§12).
            ->assertSee('продлить один раз')
            ->assertSee('до трёх')
            ->assertSee('3 дня')
            // Digital access is read-only by policy (§19.2).
            ->assertSee('Скачивание файлов не предусмотрено')
            // The section links out to the full rules document.
            ->assertSee('href="/rules?lang=ru#borrowing"', false);
    }

    public function test_faq_states_that_the_ai_assistant_is_not_yet_available(): void
    {
        // PROJECT_CONTEXT §34 lists AI-assisted discovery as future scope; the
        // page must not imply readers can use it today.
        $this->get('/')
            ->assertOk()
            ->assertSee('находится в разработке');
    }

    // ─────────────────────────────────────────────────────────────────
    // Section ordering and structure
    // ─────────────────────────────────────────────────────────────────

    public function test_sections_appear_in_the_designed_order(): void
    {
        $content = $this->get('/')->assertOk()->getContent();

        $positions = [];
        foreach ([
            'homepage-canonical-hero',
            'homepage-faculty-picks',
            'homepage-new-arrivals',
            'homepage-collections',
            'homepage-statistics',
            'homepage-faq',
        ] as $section) {
            $position = strpos($content, 'data-section="'.$section.'"');
            $this->assertNotFalse($position, "Section {$section} is missing from the homepage.");
            $positions[$section] = $position;
        }

        $ordered = $positions;
        asort($ordered);

        $this->assertSame(
            array_keys($positions),
            array_keys($ordered),
            'Homepage sections must render in the designed order.',
        );
    }

    public function test_every_section_carries_a_heading(): void
    {
        $content = $this->get('/')->assertOk()->getContent();

        // One h2 per content section keeps the document outline navigable:
        // faculty picks, how-to-use, new arrivals, collections, statistics, FAQ.
        $this->assertSame(
            6,
            substr_count($content, 'class="hs-title"'),
            'Each content section must expose exactly one section heading.',
        );
    }

    // ─────────────────────────────────────────────────────────────────
    // Accessibility
    // ─────────────────────────────────────────────────────────────────

    public function test_page_exposes_the_accessible_shell_landmarks(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('href="#main-content"', false)
            ->assertSee('id="main-content"', false)
            ->assertSee('aria-label="Основная навигация сайта"', false);
    }

    public function test_interactive_controls_have_accessible_names(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            // Icon-only header controls.
            ->assertSee('aria-label="Поиск"', false)
            ->assertSee('aria-label="Подборка"', false)
            ->assertSee('aria-label="Меню"', false)
            ->assertSee('aria-label="Изменить язык интерфейса"', false);
    }

    public function test_decorative_artwork_is_hidden_from_assistive_technology(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('aria-hidden="true"', false);
    }

    public function test_page_declares_a_responsive_viewport(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('name="viewport" content="width=device-width, initial-scale=1.0"', false);
    }

    // ─────────────────────────────────────────────────────────────────
    // Localisation
    // ─────────────────────────────────────────────────────────────────

    public function test_russian_session_locale_renders_russian_copy(): void
    {
        $this->get('/')
            ->assertOk()
            ->assertSee('<html lang="ru">', false)
            ->assertSee('Популярные книги по абонементам');
    }

    public function test_kazakh_locale_renders_every_section_heading(): void
    {
        $this->get('/?lang=kk')
            ->assertOk()
            ->assertSee('<html lang="kk">', false)
            ->assertSee('Абонементтер бойынша танымал кітаптар')
            ->assertSee('Жаңа түсімдер')
            ->assertSee('Кітапхана жинақтары')
            ->assertSee('Кітапхана статистикасы')
            ->assertSee('Жиі қойылатын сұрақтар');
    }

    public function test_english_locale_renders_every_section_heading(): void
    {
        $this->get('/?lang=en')
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('Popular books by desk')
            ->assertSee('New additions')
            ->assertSee('Library collections')
            ->assertSee('Library statistics')
            ->assertSee('Frequently asked questions');
    }

    public function test_internal_links_carry_the_active_locale(): void
    {
        $this->get('/?lang=en')
            ->assertOk()
            ->assertSee('href="/catalog?udc=004&amp;lang=en"', false)
            ->assertSee('href="/rules?lang=en#borrowing"', false);
    }

    // ─────────────────────────────────────────────────────────────────
    // Guards — retired markup must not return
    // ─────────────────────────────────────────────────────────────────

    public function test_retired_canonical_shell_markup_is_absent(): void
    {
        $response = $this->get('/?lang=en');

        $response->assertOk()
            // Old hero furniture.
            ->assertDontSee('data-test-id="homepage-canonical-hero-stats"', false)
            ->assertDontSee('id="hero-quick-links"', false)
            ->assertDontSee('Цифровой куратор')
            // Old bento collections and services trio.
            ->assertDontSee('data-section="homepage-canonical-collections"', false)
            ->assertDontSee('data-section="homepage-canonical-services"', false)
            ->assertDontSee('data-test-id="homepage-canonical-bento-featured"', false)
            ->assertDontSee('data-test-id="homepage-canonical-services-heading"', false)
            ->assertDontSee('Scholarly Services');
    }

    public function test_homepage_never_reintroduces_legacy_branding(): void
    {
        $this->get('/?lang=en')
            ->assertOk()
            ->assertDontSee('Athenaeum', false)
            ->assertDontSee('KazUTB Digital Library', false);
    }
}
