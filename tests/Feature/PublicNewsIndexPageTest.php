<?php

namespace Tests\Feature;

use Tests\Concerns\BuildsAdminControlPlane;
use Tests\Concerns\SeedsPublicNews;
use Tests\TestCase;

class PublicNewsIndexPageTest extends TestCase
{
    use BuildsAdminControlPlane, SeedsPublicNews;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        $this->seedPublicNews();
    }

    public function test_news_index_renders_managed_publications_in_all_languages(): void
    {
        $this->get('/news?lang=en')->assertOk()->assertSee('Library Dispatch', false)
            ->assertSee('Global Symposium on Archival Integrity Concludes in Astana', false)
            ->assertSee('Integration of the 19th-Century Eurasian Manuscripts', false)
            ->assertSee('Expanded Digital Access for External Academic Partners', false);
        $this->get('/news?lang=ru')->assertOk()->assertSee('Библиотечный вестник', false)->assertSee('Международный симпозиум по целостности архивов', false);
        $this->get('/news?lang=kk')->assertOk()->assertSee('Кітапхана хабаршысы', false)->assertSee('Астанада мұрағат тұтастығы', false);
    }

    public function test_index_uses_canonical_structure_and_working_links(): void
    {
        $this->get('/news?lang=en')->assertOk()
            ->assertSee('data-section="news-canonical-page"', false)
            ->assertSee('data-section="news-canonical-featured"', false)
            ->assertSee('data-section="news-canonical-grid"', false)
            ->assertSee('data-test-id="news-canonical-filter"', false)
            ->assertSee('href="/news/global-symposium-archival-integrity?lang=en"', false)
            ->assertSee('/storage/images/news/campus-library.jpg', false)
            ->assertDontSee('KazUTB Smart Library news', false)->assertDontSee('KazTBU Digital Library', false);
    }

    public function test_filters_and_empty_result_are_clear_to_the_reader(): void
    {
        $this->get('/news?lang=ru&q=нет-такой-публикации')->assertOk()
            ->assertSee('По выбранным условиям публикации не найдены.', false)
            ->assertSee('Сбросить фильтры', false);
    }
}
