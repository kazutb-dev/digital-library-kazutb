<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Tests\TestCase;

/**
 * Phase 3.g — Canonical-exact /news/{slug} detail page tests.
 *
 * Verifies that /news/{slug} follows news_detail_canonical layout:
 * 2-column article + sidebar, back link, hero, body blocks, inline CTA,
 * editorial author card, related updates (horizontal thumbnail cards),
 * and newsletter block — with tri-lingual support.
 */
class PublicNewsDetailPageTest extends TestCase
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

    // ── HTTP status ────────────────────────────────────────────────────────

    public function test_featured_article_slug_returns_200(): void
    {
        $this->get('/news/global-symposium-archival-integrity?lang=en')
            ->assertOk();
    }

    public function test_secondary_article_slug_returns_200(): void
    {
        $this->get('/news/eurasian-manuscripts-integration?lang=en')
            ->assertOk();
    }

    public function test_third_article_slug_returns_200(): void
    {
        $this->get('/news/digital-access-partner-institutions?lang=en')
            ->assertOk();
    }

    public function test_unknown_slug_returns_404(): void
    {
        $this->get('/news/does-not-exist?lang=en')
            ->assertNotFound();
    }

    // ── Canonical structure markers present ────────────────────────────────

    public function test_canonical_page_section_marker_present(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity?lang=en');
        $response->assertSee('data-section="news-detail-canonical-page"', false);
    }

    public function test_canonical_article_section_marker_present(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity?lang=en');
        $response->assertSee('data-section="news-detail-canonical-article"', false);
    }

    public function test_canonical_back_link_test_id_present(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity?lang=en');
        $response->assertSee('data-test-id="news-detail-canonical-back"', false);
    }

    public function test_canonical_body_test_id_present(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity?lang=en');
        $response->assertSee('data-test-id="news-detail-canonical-body"', false);
    }

    public function test_canonical_cta_test_id_present(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity?lang=en');
        $response->assertSee('data-test-id="news-detail-canonical-cta"', false);
    }

    public function test_canonical_sidebar_section_marker_present(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity?lang=en');
        $response->assertSee('data-section="news-detail-canonical-sidebar"', false);
    }

    public function test_canonical_author_test_id_present(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity?lang=en');
        $response->assertSee('data-test-id="news-detail-canonical-author"', false);
    }

    public function test_canonical_related_section_marker_present(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity?lang=en');
        $response->assertSee('data-section="news-detail-canonical-related"', false);
    }

    public function test_canonical_newsletter_test_id_present(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity?lang=en');
        $response->assertSee('data-test-id="news-detail-canonical-newsletter"', false);
    }

    // ── Old shell markers absent ───────────────────────────────────────────

    public function test_old_news_detail_section_absent(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity?lang=en');
        // Old marker was data-section="news-detail" (closing quote after "news-detail")
        // New canonical marker is data-section="news-detail-canonical-page" — different string.
        $response->assertDontSee('data-section="news-detail"', false);
    }

    public function test_old_news_detail_grid_class_absent(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity?lang=en');
        $response->assertDontSee('news-detail-grid', false);
    }

    public function test_old_news_back_link_class_absent(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity?lang=en');
        $response->assertDontSee('news-back-link', false);
    }

    public function test_old_news_meta_badge_class_absent(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity?lang=en');
        $response->assertDontSee('news-meta-badge', false);
    }

    public function test_old_news_detail_sidebar_class_absent(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity?lang=en');
        $response->assertDontSee('news-detail-sidebar', false);
    }

    public function test_old_related_articles_heading_absent(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity?lang=en');
        $response->assertDontSee('Related articles', false);
    }

    public function test_old_back_to_news_text_absent(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity?lang=en');
        $response->assertDontSee('Back to news', false);
    }

    // ── Real seeded content renders ────────────────────────────────────────

    public function test_featured_article_title_renders(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity?lang=en');
        $response->assertSee('Global Symposium on Archival Integrity Concludes in Astana', false);
    }

    public function test_featured_article_category_renders(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity?lang=en');
        $response->assertSee('Featured Report', false);
    }

    public function test_featured_article_hero_image_renders(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity?lang=en');
        $response->assertSee('images/news/campus-library.jpg', false);
    }

    public function test_featured_article_body_h2_renders(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity?lang=en');
        $response->assertSee('Programme themes', false);
    }

    public function test_featured_article_cta_label_renders(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity?lang=en');
        $response->assertSee('Open the repository', false);
    }

    public function test_featured_article_back_link_points_to_news(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity?lang=en');
        $response->assertSee('href="/news?lang=en"', false);
    }

    public function test_secondary_article_content_renders(): void
    {
        $response = $this->get('/news/eurasian-manuscripts-integration?lang=en');
        $response->assertSee('Integration of the 19th-Century Eurasian Manuscripts', false);
        $response->assertSee('Collection Updates', false);
        $response->assertSee('Open the catalog', false);
    }

    public function test_related_articles_exclude_current(): void
    {
        // Featured article is not related to itself.
        $response = $this->get('/news/global-symposium-archival-integrity?lang=en');
        // Related should include the other two.
        $response->assertSee('Integration of the 19th-Century Eurasian Manuscripts', false);
        $response->assertSee('Expanded Digital Access', false);
    }

    public function test_related_articles_for_secondary_include_featured(): void
    {
        $response = $this->get('/news/eurasian-manuscripts-integration?lang=en');
        $response->assertSee('Global Symposium on Archival Integrity Concludes in Astana', false);
    }

    public function test_related_thumbnails_render(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity?lang=en');
        // Related articles include those with hero images.
        $response->assertSee('images/news/classics-event.jpg', false);
    }

    // ── Tri-lingual content ────────────────────────────────────────────────

    public function test_russian_back_link_renders(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity');
        $response->assertSee('Вернуться к новостям', false);
    }

    public function test_kazakh_back_link_renders(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity?lang=kk');
        $response->assertSee('Жаңалықтарға оралу', false);
    }

    public function test_english_back_link_renders(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity?lang=en');
        // Use prefix to avoid HTML-entity escaping of &
        $response->assertSee('Return to News', false);
    }

    public function test_russian_related_heading_renders(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity');
        $response->assertSee('Связанные материалы', false);
    }

    public function test_english_related_heading_renders(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity?lang=en');
        $response->assertSee('Related Updates', false);
    }

    public function test_kazakh_editorial_label_renders(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity?lang=kk');
        $response->assertSee('Редакциялық топ', false);
    }

    public function test_english_editorial_label_renders(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity?lang=en');
        $response->assertSee('Editorial Team', false);
    }

    public function test_russian_newsletter_heading_renders(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity');
        $response->assertSee('Будьте в курсе', false);
    }

    public function test_english_newsletter_heading_renders(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity?lang=en');
        $response->assertSee('Stay Informed', false);
    }

    // ── Brand guards ──────────────────────────────────────────────────────

    public function test_no_legacy_brand_strings(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity?lang=en');
        $response->assertDontSee('Athenaeum', false);
        $response->assertDontSee('Curator Archive', false);
        $response->assertDontSee('KazTBU Digital Library', false);
        $response->assertDontSee('KazUTB Digital Library', false);
    }

    // ── Author card no personal names ─────────────────────────────────────

    public function test_author_card_shows_institutional_byline(): void
    {
        $response = $this->get('/news/global-symposium-archival-integrity?lang=en');
        $response->assertSee('Institutional Communications', false);
        $response->assertSee('All dispatches', false);
    }

    // ── Authenticated access ───────────────────────────────────────────────

    public function test_authenticated_student_can_view_news_detail(): void
    {
        $this->loginAs('student');
        $this->get('/news/global-symposium-archival-integrity?lang=en')
            ->assertOk()
            ->assertSee('KazUTB Smart Library', false);
    }

    public function test_librarian_can_view_news_detail(): void
    {
        $this->loginAs('librarian');
        $this->get('/news/global-symposium-archival-integrity?lang=en')
            ->assertOk()
            ->assertSee('KazUTB Smart Library', false);
    }

    public function test_admin_can_view_news_detail(): void
    {
        $this->loginAs('admin');
        $this->get('/news/global-symposium-archival-integrity?lang=en')
            ->assertOk()
            ->assertSee('KazUTB Smart Library', false);
    }
}
