<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Tests\TestCase;

/**
 * Phase 3.f — Public News index (/news) — canonical-exact rebuild.
 *
 * The old news shell (data-section="news-intro", "news-featured", "news-grid",
 * CSS classes news-intro / news-featured-grid / news-grid / news-card) has been
 * replaced wholesale with the news_index_canonical layout structure.
 *
 * Covers:
 *   1. /news returns 200
 *   2. canonical major structure markers render
 *   3. old obsolete shell markers are absent
 *   4. seeded articles still render
 *   5. tri-lingual chrome renders correctly
 *   6. article detail links are well-formed
 *   7. no incorrect legacy brand strings appear
 */
class PublicNewsIndexPageTest extends TestCase
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
            '_token'      => csrf_token(),
            'login'       => $identity['login'],
            'password'    => $identity['password'],
            'device_name' => 'phpunit',
        ]);
    }

    // ── 1. HTTP status ────────────────────────────────────────────────

    public function test_news_index_returns_200(): void
    {
        $this->get('/news')->assertOk();
        $this->get('/news?lang=en')->assertOk();
        $this->get('/news?lang=kk')->assertOk();
        $this->get('/news?lang=ru')->assertOk();
    }

    /** @deprecated alias kept for CI history grep */
    public function test_legacy_news_redirect_is_reversed(): void
    {
        $this->get('/news?lang=en')->assertOk()->assertStatus(200);
    }

    // ── 2. Canonical structure markers ───────────────────────────────

    public function test_canonical_page_section_marker_renders(): void
    {
        $r = $this->get('/news?lang=en');

        $r->assertOk();
        $r->assertSee('data-section="news-canonical-page"', false);
        $r->assertSee('data-section="news-canonical-featured"', false);
        $r->assertSee('data-section="news-canonical-grid"', false);
    }

    public function test_canonical_test_id_markers_render(): void
    {
        $r = $this->get('/news?lang=en');

        $r->assertOk();
        $r->assertSee('data-test-id="news-canonical-header"', false);
        $r->assertSee('data-test-id="news-canonical-featured"', false);
        $r->assertSee('data-test-id="news-canonical-filter"', false);
        $r->assertSee('data-test-id="news-canonical-bento"', false);
    }

    public function test_canonical_css_prefix_applied(): void
    {
        $r = $this->get('/news?lang=en');

        $r->assertOk();
        $r->assertSee('news-canonical__header', false);
        $r->assertSee('news-canonical__display', false);
        $r->assertSee('news-canonical__featured-card', false);
        $r->assertSee('news-canonical__grid', false);
        $r->assertSee('news-canonical__bento', false);
    }

    // ── 3. Old obsolete shell markers must be absent ──────────────────

    public function test_old_shell_section_markers_are_gone(): void
    {
        $r = $this->get('/news?lang=en');

        $r->assertOk();
        $r->assertDontSee('data-section="news-intro"', false);
        $r->assertDontSee('data-section="news-featured"', false);
        $r->assertDontSee('data-section="news-grid"', false);
    }

    public function test_old_shell_css_classes_are_gone(): void
    {
        $r = $this->get('/news?lang=en');

        $r->assertOk();
        $r->assertDontSee('class="page-section news-intro"', false);
        $r->assertDontSee('news-featured-grid', false);
        $r->assertDontSee('class="news-grid"', false);
        $r->assertDontSee('class="news-card"', false);
        $r->assertDontSee('class="news-card-link"', false);
    }

    public function test_old_intro_heading_copy_is_gone(): void
    {
        // Old shell used: "KazUTB Smart Library news" as intro_heading.
        // Canonical uses "Library Dispatch" as display h1.
        $r = $this->get('/news?lang=en');

        $r->assertOk();
        $r->assertDontSee('KazUTB Smart Library news', false);
    }

    // ── 4. Seeded articles still render ──────────────────────────────

    public function test_guest_can_view_news_index_with_seeded_articles(): void
    {
        $r = $this->get('/news?lang=en');

        $r->assertOk();
        // Canonical chrome present
        $r->assertSee('Library Dispatch', false);
        $r->assertSee('Institutional Updates', false);
        $r->assertSee('Recent Articles', false);
        // Featured article title from seed
        $r->assertSee('Global Symposium on Archival Integrity Concludes in Astana', false);
        // Grid articles
        $r->assertSee('Integration of the 19th-Century Eurasian Manuscripts', false);
        $r->assertSee('Expanded Digital Access for External Academic Partners', false);
        // Bento canonical element
        $r->assertSee('Library Events', false);
        // Load more
        $r->assertSee('Load More Dispatches', false);
    }

    public function test_featured_article_category_and_read_cta_render(): void
    {
        $r = $this->get('/news?lang=en');

        $r->assertOk();
        // Featured article category tag (from seed data)
        $r->assertSee('Featured Report', false);
        // New canonical CTA text (old was "Read Full Coverage")
        $r->assertSee('Read full dispatch', false);
    }

    // ── 5. Tri-lingual chrome ─────────────────────────────────────────

    public function test_russian_chrome_renders(): void
    {
        $r = $this->get('/news?lang=ru');

        $r->assertOk();
        $r->assertSee('Библиотечный вестник', false);
        $r->assertSee('Институциональные обновления', false);
        $r->assertSee('Последние статьи', false);
        $r->assertSee('Читать полностью', false);
        $r->assertSee('Загрузить ещё', false);
    }

    public function test_kazakh_chrome_renders(): void
    {
        $r = $this->get('/news?lang=kk');

        $r->assertOk();
        $r->assertSee('Кітапхана хабаршысы', false);
        $r->assertSee('Институционалдық жаңартулар', false);
        $r->assertSee('Соңғы мақалалар', false);
        $r->assertSee('Толығырақ оқу', false);
        $r->assertSee('Тағы жүктеу', false);
    }

    public function test_english_chrome_renders(): void
    {
        $r = $this->get('/news?lang=en');

        $r->assertOk();
        $r->assertSee('Library Dispatch', false);
        $r->assertSee('Institutional Updates', false);
        $r->assertSee('Recent Articles', false);
        $r->assertSee('Read full dispatch', false);
        $r->assertSee('Load More Dispatches', false);
    }

    // ── 6. Article detail links are well-formed ──────────────────────

    public function test_article_links_are_well_formed_with_lang(): void
    {
        $r = $this->get('/news?lang=en');

        $r->assertOk();
        // Featured article link with ?lang=en preserved
        $r->assertSee('href="/news/global-symposium-archival-integrity?lang=en"', false);
        // Grid article links
        $r->assertSee('href="/news/eurasian-manuscripts-integration?lang=en"', false);
        $r->assertSee('href="/news/digital-access-partner-institutions?lang=en"', false);
    }

    public function test_bento_events_link_is_well_formed(): void
    {
        $r = $this->get('/news?lang=en');

        $r->assertOk();
        $r->assertSee('href="/events?lang=en"', false);
        $r->assertSee('View all events', false);
    }

    public function test_default_lang_links_omit_lang_param(): void
    {
        $r = $this->get('/news');

        $r->assertOk();
        // Default lang (ru) links should NOT append ?lang=ru
        $r->assertSee('href="/news/global-symposium-archival-integrity"', false);
        $r->assertSee('href="/events"', false);
    }

    // ── Image assets ─────────────────────────────────────────────────

    public function test_seeded_image_assets_render(): void
    {
        $r = $this->get('/news?lang=en');

        $r->assertOk();
        $r->assertSee('/images/news/campus-library.jpg', false);
        $r->assertSee('/images/news/classics-event.jpg', false);
        $r->assertSee('/images/news/author-visit.jpg', false);
    }

    // ── 7. No legacy brand strings ────────────────────────────────────

    public function test_no_legacy_brand_strings_appear(): void
    {
        $r = $this->get('/news?lang=en');

        $r->assertOk();
        // View-scoped regression guards — shared layout may carry "Digital Library"
        // so guard specifically for erroneous legacy brand variants only.
        $r->assertDontSee('Athenaeum', false);
        $r->assertDontSee('Curator Archive', false);
        $r->assertDontSee('KazTBU Digital Library', false);
        $r->assertDontSee('KazUTB Digital Library', false);
    }

    // ── Auth access ───────────────────────────────────────────────────

    public function test_guest_can_view_news_index(): void
    {
        $this->get('/news?lang=en')->assertOk()->assertSee('Library Dispatch', false);
    }

    public function test_authenticated_reader_can_view_news_index(): void
    {
        $this->loginAs('student');

        $r = $this->get('/news?lang=en');
        $r->assertOk();
        $r->assertSee('Library Dispatch', false);
        $r->assertSee('Recent Articles', false);
        $r->assertSee('Sign out', false);
    }

    public function test_librarian_can_view_news_index(): void
    {
        $this->loginAs('librarian');

        $this->get('/news?lang=en')->assertOk()->assertSee('Library Dispatch', false);
    }

    public function test_admin_can_view_news_index(): void
    {
        $this->loginAs('admin');

        $this->get('/news?lang=en')->assertOk()->assertSee('Library Dispatch', false);
    }
}
