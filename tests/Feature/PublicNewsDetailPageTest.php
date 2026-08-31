<?php

namespace Tests\Feature;

use Tests\Concerns\BuildsAdminControlPlane;
use Tests\Concerns\SeedsPublicNews;
use Tests\TestCase;

class PublicNewsDetailPageTest extends TestCase
{
    use BuildsAdminControlPlane, SeedsPublicNews;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        $this->seedPublicNews();
    }

    public function test_each_published_article_opens_and_unknown_slug_is_404(): void
    {
        foreach (['global-symposium-archival-integrity', 'eurasian-manuscripts-integration', 'digital-access-partner-institutions'] as $slug) {
            $this->get('/news/'.$slug.'?lang=en')->assertOk();
        }
        $this->get('/news/does-not-exist?lang=en')->assertNotFound();
    }

    public function test_detail_uses_canonical_readable_structure_and_related_publications(): void
    {
        $this->get('/news/global-symposium-archival-integrity?lang=en')->assertOk()
            ->assertSee('data-section="news-detail-canonical-page"', false)
            ->assertSee('data-section="news-detail-canonical-article"', false)
            ->assertSee('data-test-id="news-detail-canonical-back"', false)
            ->assertSee('data-test-id="news-detail-canonical-body"', false)
            ->assertSee('data-section="news-detail-canonical-related"', false)
            ->assertSee('Global Symposium on Archival Integrity Concludes in Astana', false)
            ->assertSee('Integration of the 19th-Century Eurasian Manuscripts', false)
            ->assertSee('Return to News &amp; Announcements', false)
            ->assertDontSee('Athenaeum', false)->assertDontSee('KazUTB Digital Library', false);
    }

    public function test_detail_is_localized_in_all_supported_languages(): void
    {
        $this->get('/news/global-symposium-archival-integrity?lang=ru')->assertOk()
            ->assertSee('Вернуться к новостям', false)->assertSee('Международный симпозиум по целостности архивов', false);
        $this->get('/news/global-symposium-archival-integrity?lang=kk')->assertOk()
            ->assertSee('Жаңалықтарға оралу', false)->assertSee('Астанада мұрағат тұтастығы', false);
        $this->get('/news/global-symposium-archival-integrity?lang=en')->assertOk()->assertSee('Main publication text.', false);
    }
}
