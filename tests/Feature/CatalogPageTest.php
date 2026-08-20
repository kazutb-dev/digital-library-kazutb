<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Fund;
use App\Models\Setting;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class CatalogPageTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        $this->seedCatalogueRow();
    }

    /**
     * The catalogue page only renders per-record controls when the collection
     * has something in it, so seed one real holding at the college service
     * point rather than relying on demo data.
     */
    private function seedCatalogueRow(): void
    {
        $record = BibliographicRecord::factory()->create([
            'title' => 'Тестовое издание каталога',
            'primary_author' => 'Тестов Т.Т.',
            'publication_year' => 2024,
            'is_draft' => false,
        ]);

        BookCopy::factory()->create([
            'bibliographic_record_id' => $record->getKey(),
            'branch_id' => Branch::query()->where('code', 'READING-ROOM')->value('id'),
            'fund_id' => Fund::query()->where('code', 'COLLEGE')->value('id'),
            'shelf_location' => 'каб. 3',
            'status' => 'available',
        ]);
    }

    public function test_catalog_page_renders_successfully(): void
    {
        $response = $this->get('/catalog?lang=ru');

        $response
            ->assertOk()
            ->assertSee('Каталог университетской библиотеки', false)
            ->assertSee('/api/v1/catalog-db', false)
            ->assertSee('id="catalog-active-filters"', false)
            ->assertSee('id="language-chips"', false)
            ->assertSee('id="year-from-input"', false)
            ->assertSee('id="year-to-input"', false)
            ->assertSee('id="filter-available-only"', false)
            ->assertSee('id="filter-physical-only"', false)
            ->assertSee('id="sort-select"', false)
            ->assertSee('ISBN', false)
            ->assertDontSee('data-facet-axis="udc"', false)
            ->assertDontSee('id="advanced-udc-input"', false)
            ->assertSee('data-catalog-shortlist-button', false)
            ->assertSee('bookmark_add', false)
            ->assertSee('aria-label="В подборку"', false)
            ->assertSee('id="header-shortlist-count"', false)
            ->assertSee('id="catalog-shortlist-toast"', false);
    }

    public function test_catalog_page_has_functional_filter_chips(): void
    {
        $response = $this->get('/catalog');

        $response
            ->assertOk()
            ->assertSee('data-lang="ru"', false)
            ->assertSee('data-lang="kk"', false)
            ->assertSee('data-lang="en"', false)
            ->assertSee('id="year-from-input"', false)
            ->assertSee('id="year-to-input"', false)
            // The hardcoded "Коллекция" select was replaced by the real,
            // facet-driven fund and branch axes.
            ->assertDontSee('id="institution-select"', false)
            ->assertSee('data-facet="fund"', false)
            ->assertSee('data-facet="branch"', false);
    }

    public function test_catalog_page_sends_filters_to_api(): void
    {
        $response = $this->get('/catalog');

        $response
            ->assertOk()
            ->assertSee('params.set(\'language\'', false)
            ->assertSee('params.set(\'sort\'', false)
            ->assertSee('advanced-subject-input', false)
            ->assertSee("apiParams.set('subject'", false)
            ->assertSee('year_from', false)
            ->assertSee('year_to', false)
            ->assertSee('Number(window.catalogState.yearFrom) !== YEAR_BOUNDS.min', false)
            ->assertSee('Number(window.catalogState.yearTo) !== YEAR_BOUNDS.max', false)
            ->assertSee('available_only', false)
            ->assertSee('physical_only', false)
            ->assertSee('institution', false);
    }

    public function test_record_without_isbn_still_links_to_its_detail_page_by_id(): void
    {
        $record = BibliographicRecord::factory()->create([
            'title' => 'Карточка без ISBN',
            'isbn' => null,
            'is_draft' => true,
        ]);

        $this->get('/catalog?sort=title')
            ->assertOk()
            ->assertSee('/book/'.$record->getKey(), false);
    }

    /**
     * The coarse "institution" axis lost its sidebar control (fund + branch
     * supersede it) but stays a working, linkable query parameter: the page
     * must still render, and the value must survive into the JS state so the
     * first XHR does not silently drop the reader's filter.
     */
    public function test_catalog_page_keeps_institution_deep_links_and_honest_holding_filters(): void
    {
        $response = $this->get('/catalog?institution=technology_library');

        $response
            ->assertOk()
            ->assertSee('institution: "technology_library"', false)
            ->assertSee('Тек қолжетімді даналар')
            ->assertSee('Тек физикалық қоры бар');
    }

    /**
     * Every sidebar option is rendered from the live facet payload, with its
     * real count, and a zero-count option is disabled rather than dropped so
     * the sidebar does not reshuffle as the collection grows.
     */
    public function test_catalog_sidebar_axes_are_facet_driven_with_counts(): void
    {
        $response = $this->get('/catalog?lang=ru');

        $response
            ->assertOk()
            ->assertSee('data-facet="resource_type"', false)
            ->assertSee('data-facet="category"', false)
            ->assertSee('data-facet-single="availability"', false)
            ->assertSee('data-facet-single="format"', false)
            ->assertDontSee('data-facet-single="udc"', false)
            // The one seeded holding sits in the college fund at the reading
            // room, so those are the only options either axis may offer.
            ->assertSee('value="COLLEGE"', false)
            ->assertSee('value="READING-ROOM"', false)
            ->assertSee('class="catalog-facet__count">1<', false)
            // Zero-count availability states stay visible but disabled.
            ->assertSee('value="issued"', false)
            ->assertSee('catalog-facet is-disabled', false)
            // ...and none of the retired hardcoded lists come back.
            ->assertDontSee('Показать ещё 12')
            ->assertDontSee('data-collection-filter', false)
            ->assertDontSee('data-material-type', false);
    }

    /**
     * Pagination is sized by Setting::catalogPageSize(), renders numbered
     * pages as real filter-preserving links, and disables prev on page 1.
     */
    public function test_catalog_pagination_uses_the_real_page_size(): void
    {
        $pageSize = Setting::catalogPageSize();

        $response = $this->get('/catalog');

        $response
            ->assertOk()
            ->assertSee('const PAGE_SIZE = '.$pageSize.';', false)
            ->assertSee("apiParams.set('limit', String(PAGE_SIZE))", false)
            ->assertDontSee("apiParams.set('limit', '10')", false);
    }

    public function test_catalog_page_uses_canonical_catalog_db_endpoint_only(): void
    {
        $response = $this->get('/catalog');

        $response
            ->assertOk()
            ->assertSee('/api/v1/catalog-db', false)
            ->assertDontSee('/api/v1/catalog?', false)
            ->assertDontSee('/api/v1/catalog-external', false);
    }

    public function test_catalog_page_is_ready_for_description_and_pagination_behavior(): void
    {
        $response = $this->get('/catalog?sort=relevance');

        $response
            ->assertOk()
            ->assertSee('data-catalog-description', false)
            ->assertSee('id="catalog-pagination"', false)
            ->assertSee("params.set('page'", false)
            ->assertDontSee('app.document_detail_v');
    }

    public function test_catalog_page_shows_human_friendly_library_locations(): void
    {
        $response = $this->get('/catalog?institution=college_library&lang=ru');

        $response
            ->assertOk()
            ->assertSee('Библиотека колледжа')
            ->assertDontSee('каб. 3')
            ->assertSee('Тестовое издание каталога', false);
    }

    public function test_catalog_page_has_enhanced_sort_year_and_advanced_controls(): void
    {
        $response = $this->get('/catalog');

        $response
            ->assertOk()
            ->assertSee('data-sort-menu', false)
            ->assertSee('id="catalog-active-filters-list"', false)
            ->assertSee('id="year-from-range"', false)
            ->assertSee('id="year-to-range"', false)
            ->assertSee('id="advanced-search-panel"', false)
            ->assertSee('params.set(\'title\'', false)
            ->assertSee('params.set(\'author\'', false)
            ->assertSee('params.set(\'isbn\'', false);
    }
}
