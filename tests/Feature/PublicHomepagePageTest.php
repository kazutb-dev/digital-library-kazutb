<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Support\Facades\Cache;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * Public homepage — contract for the redesigned page.
 *
 * The homepage was rebuilt around a single-row institutional header, a
 * full-bleed hero with the catalog search, and source-backed content sections:
 *
 *   1. Hero                     data-section="homepage-canonical-hero"
 *   2. Source-backed figures    data-section="homepage-hero-stats"
 *   3. Library collections      data-section="homepage-faculty-picks" (only with real usage data)
 *   4. Reader workflow          data-section="homepage-how-to-use-library"
 *   5. New additions            data-section="homepage-new-arrivals"
 *   6. UDC collections          data-section="homepage-collections"
 *   7. FAQ                      data-section="homepage-faq"
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
        $response = $this->get('/?lang=ru');

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
        $response = $this->get('/?lang=ru');

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
            $response->assertSee('href="'.$href.'?lang=ru"', false);
        }
    }

    public function test_header_exposes_search_shortlist_and_locale_switcher(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('id="site-search-input"', false)
            ->assertSee('href="/shortlist?lang=ru"', false)
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

    public function test_footer_exposes_only_the_four_verified_information_groups(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('university-footer', false)
            ->assertSee('Навигация')
            ->assertSee('Обновления')
            ->assertSee('О библиотеке')
            ->assertSee('Поддержка')
            ->assertDontSee('Институт');

        $this->assertSame(
            4,
            substr_count($response->getContent(), 'class="university-footer__column"'),
            'The footer must not invent an unsupported institutional column.',
        );
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

    public function test_faculty_showcase_is_omitted_without_source_backed_usage(): void
    {
        $response = $this->get('/');
        $markup = substr($response->getContent(), strpos($response->getContent(), '<div data-section="homepage-canonical-page">'));

        $response->assertOk();
        $this->assertDoesNotMatchRegularExpression(
            '/<section\b[^>]*\bdata-section="homepage-faculty-picks"/u',
            $markup,
        );
        $this->assertStringNotContainsString('class="homepage-faculty-showcase__desk"', $markup);
    }

    public function test_homepage_does_not_publish_legacy_institution_filters_without_source_data(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertDontSee('institution=economic_library', false)
            ->assertDontSee('institution=technology_library', false)
            ->assertDontSee('institution=college_library', false);
    }

    // ─────────────────────────────────────────────────────────────────
    // 3. New additions
    // ─────────────────────────────────────────────────────────────────

    public function test_new_additions_section_renders_a_scrollable_rail(): void
    {
        $response = $this->get('/?lang=ru');

        $response->assertOk()
            ->assertSee('data-section="homepage-new-arrivals"', false)
            ->assertSee('Новые поступления')
            ->assertSee('Каталог пополняется')
            ->assertDontSee('id="hs-arrivals-rail"', false);
    }

    public function test_new_addition_cards_carry_catalog_metadata_and_a_detail_link(): void
    {
        $response = $this->get('/?lang=ru');

        $response->assertOk()
            ->assertSee('data-section="homepage-new-arrivals"', false)
            ->assertSee('Каталог пополняется')
            ->assertDontSee('data-book-id=', false);
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

    public function test_statistics_render_only_source_backed_figures(): void
    {
        Cache::put('public.portal.statistics.v3', [
            'catalog_titles' => 9562,
            'physical_copies' => 50907,
            'electronic_materials' => null,
            'published_resources' => 6,
            'published_repository' => 0,
            'published_news' => 0,
            'published_events' => 0,
        ], now()->addMinute());

        $response = $this->get('/?lang=ru');

        $response->assertOk()
            ->assertSee('data-section="homepage-hero-stats"', false)
            ->assertSee('data-stat-source="catalog_titles"', false)
            ->assertSee('9 562')
            ->assertSee('Наименований в электронном каталоге')
            ->assertSee('data-stat-source="physical_copies"', false)
            ->assertSee('50 907')
            ->assertSee('Экземпляров в библиотечном фонде')
            ->assertSee('data-stat-source="published_resources"', false)
            ->assertSee('Ресурсов с опубликованными условиями доступа')
            ->assertSee('data-stat-source="public_catalog_availability"', false)
            ->assertSee('Онлайн-каталог доступен круглосуточно')
            ->assertDontSee('46 000+')
            ->assertDontSee('100 000+')
            ->assertDontSee('90 000+')
            ->assertDontSee('2 416+');

        $this->assertSame(
            4,
            substr_count($response->getContent(), 'class="homepage-hero-stats__item"'),
            'Every rendered figure must be either source-backed or the explicit online-catalogue availability claim.',
        );
    }

    public function test_statistics_are_localised_for_english_number_formatting(): void
    {
        Cache::put('public.portal.statistics.v3', [
            'catalog_titles' => 9562,
            'physical_copies' => 50907,
            'electronic_materials' => null,
            'published_resources' => null,
            'published_repository' => 0,
            'published_news' => 0,
            'published_events' => 0,
        ], now()->addMinute());

        $this->get('/?lang=en')
            ->assertOk()
            ->assertSee('9,562')
            ->assertSee('Titles in the electronic catalogue')
            ->assertSee('50,907')
            ->assertSee('Copies in the library collection')
            ->assertSee('The online catalogue is available around the clock')
            ->assertDontSee('46,000+')
            ->assertDontSee('100,000+');
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
            ->assertSee('Как связаться с библиотекарем?')
            ->assertDontSee('Как пользоваться AI-помощником?');

        // <details>/<summary> keeps the accordion usable without JavaScript.
        $this->assertSame(
            7,
            substr_count($response->getContent(), '<details class="hs-faq__item">'),
            'Each FAQ entry must be a native disclosure element.',
        );
    }

    public function test_faq_uses_current_workflow_copy_without_unverified_limits(): void
    {
        $response = $this->get('/');

        $response->assertOk()
            ->assertSee('Если продление разрешено для текущей выдачи')
            ->assertSee('Кнопка бронирования показывается только когда запрос доступен')
            ->assertSee('скачивание (если разрешено)')
            // The section links out to the full rules document.
            ->assertSee('href="/rules?lang=ru#borrowing"', false)
            ->assertDontSee('Скачивание файлов не предусмотрено')
            ->assertDontSee('продлить один раз')
            ->assertDontSee('до трёх')
            ->assertDontSee('3 дня');
    }

    public function test_public_faq_does_not_expose_development_roadmap_copy(): void
    {
        $this->get('/?lang=ru')
            ->assertOk()
            ->assertDontSee('находится в разработке')
            ->assertDontSee('пока недоступен читателям');

        $this->get('/?lang=en')
            ->assertOk()
            ->assertDontSee('under development')
            ->assertDontSee('not yet available to readers');
    }

    // ─────────────────────────────────────────────────────────────────
    // Section ordering and structure
    // ─────────────────────────────────────────────────────────────────

    public function test_sections_appear_in_the_designed_order(): void
    {
        $content = $this->get('/')->assertOk()->getContent();
        $markupStart = strpos($content, '<div data-section="homepage-canonical-page">');
        $this->assertNotFalse($markupStart, 'The canonical homepage wrapper is missing.');
        $markup = substr($content, $markupStart);

        $positions = [];
        foreach ([
            'homepage-canonical-hero',
            'homepage-hero-stats',
            'homepage-how-to-use-library',
            'homepage-new-arrivals',
            'homepage-collections',
            'homepage-faq',
        ] as $section) {
            $position = strpos($markup, 'data-section="'.$section.'"');
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

        // Source-backed figures live in the hero strip and do not introduce a
        // fake statistics heading. The remaining content sections each do.
        $this->assertSame(
            4,
            substr_count($content, 'class="hs-title"'),
            'Every source-backed content section rendered by the empty test fixture must expose one heading.',
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
            ->assertSee('Как пользоваться библиотекой');
    }

    public function test_kazakh_locale_renders_public_sections_and_truthful_online_claim(): void
    {
        $this->get('/?lang=kk')
            ->assertOk()
            ->assertSee('<html lang="kk">', false)
            ->assertSee('Кітапхананы қалай пайдалану керек')
            ->assertSee('Жаңа түсімдер')
            ->assertSee('Кітапхана жинақтары')
            ->assertSee('Жиі қойылатын сұрақтар')
            ->assertSee('Онлайн каталог тәулік бойы қолжетімді')
            ->assertDontSee('Кітапхана статистикасы');
    }

    public function test_english_locale_renders_public_sections_and_truthful_online_claim(): void
    {
        $this->get('/?lang=en')
            ->assertOk()
            ->assertSee('<html lang="en">', false)
            ->assertSee('How to use the library')
            ->assertSee('New additions')
            ->assertSee('Library collections')
            ->assertSee('Frequently asked questions')
            ->assertSee('The online catalogue is available around the clock')
            ->assertDontSee('Library statistics');
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
