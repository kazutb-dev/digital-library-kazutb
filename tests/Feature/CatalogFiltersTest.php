<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\ElectronicMaterial;
use App\Models\Fund;
use App\Models\Setting;
use App\Services\Library\CatalogReadService;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

/**
 * Public catalogue filter axes and pagination (Master.md §8.2-§8.4).
 *
 * Every axis is asserted against records this test creates, so a filter that
 * silently matches nothing — or everything — fails here rather than in front
 * of a reader.
 */
class CatalogFiltersTest extends TestCase
{
    use BuildsAdminControlPlane;

    private CatalogReadService $catalog;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        $this->catalog = app(CatalogReadService::class);
    }

    private function record(array $attributes = []): BibliographicRecord
    {
        return BibliographicRecord::factory()->create($attributes + ['is_draft' => false]);
    }

    private function copy(BibliographicRecord $record, array $attributes = []): BookCopy
    {
        return BookCopy::factory()->create($attributes + [
            'bibliographic_record_id' => $record->getKey(),
            'status' => 'available',
        ]);
    }

    private function fundId(string $code): ?int
    {
        return Fund::query()->where('code', $code)->value('id');
    }

    private function branchId(string $code): ?int
    {
        return Branch::query()->where('code', $code)->value('id');
    }

    public function test_page_size_comes_from_settings_and_the_last_page_is_partial(): void
    {
        // 13 records at 12 per page is the real edge case: 12 + 1.
        BibliographicRecord::factory()->count(13)->create(['is_draft' => false]);

        $first = $this->catalog->search(limit: Setting::catalogPageSize());
        $this->assertSame(12, $first['meta']['per_page']);
        $this->assertSame(13, $first['meta']['total']);
        $this->assertSame(2, $first['meta']['total_pages']);
        $this->assertCount(12, $first['data']);

        $second = $this->catalog->search(page: 2, limit: Setting::catalogPageSize());
        $this->assertCount(1, $second['data'], 'The last page must hold the remainder, not a full page.');
        $this->assertSame(2, $second['meta']['page']);
    }

    public function test_a_changed_page_size_setting_is_honoured(): void
    {
        BibliographicRecord::factory()->count(13)->create(['is_draft' => false]);
        Setting::query()->where('key', 'catalog_page_size')->update(['value' => json_encode(6)]);

        $this->assertSame(6, Setting::catalogPageSize());
        $result = $this->catalog->search(limit: Setting::catalogPageSize());
        $this->assertCount(6, $result['data']);
        $this->assertSame(3, $result['meta']['total_pages']);
    }

    public function test_resource_type_filter_accepts_one_and_many_values(): void
    {
        $this->record(['resource_type' => 'book']);
        $this->record(['resource_type' => 'textbook']);
        $this->record(['resource_type' => 'dissertation']);

        $this->assertSame(1, $this->catalog->search(resourceType: 'book')['meta']['total']);
        $this->assertSame(2, $this->catalog->search(resourceType: 'book,textbook')['meta']['total']);
        $this->assertSame(0, $this->catalog->search(resourceType: 'journal')['meta']['total']);
    }

    public function test_fund_and_branch_are_independent_axes(): void
    {
        $inTechFund = $this->record();
        $this->copy($inTechFund, [
            'fund_id' => $this->fundId('UNIVERSITY-TECHNOLOGY'),
            'branch_id' => $this->branchId('TECHNOLOGY-DESK'),
        ]);

        $inMainFundAtReadingRoom = $this->record();
        $this->copy($inMainFundAtReadingRoom, [
            'fund_id' => $this->fundId('MAIN'),
            'branch_id' => $this->branchId('READING-ROOM'),
        ]);

        $this->assertSame(1, $this->catalog->search(fund: 'UNIVERSITY-TECHNOLOGY')['meta']['total']);
        $this->assertSame(1, $this->catalog->search(branch: 'READING-ROOM')['meta']['total']);
        $this->assertSame(2, $this->catalog->search(fund: 'UNIVERSITY-TECHNOLOGY,MAIN')['meta']['total']);

        // Fund and branch combine as AND, not OR.
        $this->assertSame(
            0,
            $this->catalog->search(fund: 'UNIVERSITY-TECHNOLOGY', branch: 'READING-ROOM')['meta']['total'],
            'A fund/branch combination that no holding satisfies must return nothing.',
        );
    }

    public function test_category_filter_selects_by_subject_area(): void
    {
        $this->record(['category' => 'technology']);
        $this->record(['category' => 'technology']);
        $this->record(['category' => 'economics']);

        $this->assertSame(2, $this->catalog->search(category: 'technology')['meta']['total']);
        $this->assertSame(3, $this->catalog->search(category: 'technology,economics')['meta']['total']);
    }

    public function test_udc_filter_matches_the_whole_branch_of_the_classifier(): void
    {
        $this->record(['udc_code' => '004']);
        $this->record(['udc_code' => '004.8']);
        $this->record(['udc_code' => '33']);

        // Selecting a top-level class also returns its subdivisions.
        $this->assertSame(2, $this->catalog->search(udc: '004')['meta']['total']);
        $this->assertSame(1, $this->catalog->search(udc: '004.8')['meta']['total']);
        $this->assertSame(1, $this->catalog->search(udc: '33')['meta']['total']);
    }

    public function test_catalog_udc_code_and_description_are_public_bibliographic_metadata(): void
    {
        $this->record(['udc_code' => '004.8']);

        $guest = $this->catalog->search()['data'][0]['udc'];
        $authenticated = $this->catalog->search(includeUdcCode: true)['data'][0]['udc'];

        $this->assertSame('004.8', $guest['raw']);
        $this->assertSame('004.8 — Жасанды интеллект', $guest['display']);
        $this->assertSame('004.8', $authenticated['raw']);
        $this->assertSame('004.8 — Жасанды интеллект', $authenticated['display']);
    }

    public function test_homepage_desk_popularity_uses_real_copies_and_issue_counts(): void
    {
        $mostRequested = $this->record(['title' => 'Реальная популярная книга']);
        $lessRequested = $this->record(['title' => 'Реальная книга номер два']);
        $technologyFund = $this->fundId('UNIVERSITY-TECHNOLOGY');
        $technologyBranch = $this->branchId('TECHNOLOGY-DESK');

        $this->copy($mostRequested, [
            'fund_id' => $technologyFund,
            'branch_id' => $technologyBranch,
            'issue_count' => 25,
        ]);
        $this->copy($lessRequested, [
            'fund_id' => $technologyFund,
            'branch_id' => $technologyBranch,
            'issue_count' => 3,
        ]);

        $popular = $this->catalog->popularByInstitution('technology_library', 2);

        $this->assertSame('Реальная популярная книга', $popular[0]['title']);
        $this->assertSame(25, $popular[0]['issueCount']);
        $this->assertSame('Реальная книга номер два', $popular[1]['title']);
    }

    public function test_public_popular_sort_uses_issue_count_with_stable_ties(): void
    {
        $mostIssued = $this->record(['title' => 'Z — Most issued']);
        $this->copy($mostIssued, ['issue_count' => 25]);

        $firstTie = $this->record(['title' => 'Same popularity']);
        $this->copy($firstTie, ['issue_count' => 5]);
        $secondTie = $this->record(['title' => 'Same popularity']);
        $this->copy($secondTie, ['issue_count' => 5]);

        $manyAvailable = $this->record(['title' => 'A — Many available']);
        for ($copy = 0; $copy < 3; $copy++) {
            $this->copy($manyAvailable, ['issue_count' => 0]);
        }
        $noHoldings = $this->record(['title' => '0 — Metadata only']);

        $expected = [
            (string) $mostIssued->getKey(),
            (string) $firstTie->getKey(),
            (string) $secondTie->getKey(),
            (string) $manyAvailable->getKey(),
            (string) $noHoldings->getKey(),
        ];

        $this->assertSame($expected, array_column($this->catalog->search(sort: 'popular')['data'], 'id'));
        $this->assertSame($expected, array_column($this->catalog->search(sort: 'unknown')['data'], 'id'));
    }

    public function test_general_search_covers_bibliographic_metadata_beyond_title_and_author(): void
    {
        $this->record([
            'title' => 'Уникальная запись поиска',
            'publisher' => 'издательство сапфир',
            'udc_code' => '621.397',
            'author_mark' => 'с19',
            'category' => 'телекоммуникации',
        ]);

        foreach (['сапфир', '621.397', 'с19', 'телекоммуникации'] as $term) {
            $this->assertSame(1, $this->catalog->search(query: $term)['meta']['total']);
        }
    }

    public function test_subject_search_combines_annotation_keywords_category_and_udc(): void
    {
        $this->record([
            'title' => 'Тематический поиск',
            'annotation' => 'исследование возобновляемой энергетики',
            'keywords' => ['солнечная энергия', 'энергосбережение'],
            'category' => 'энергетика',
            'udc_code' => '620.9',
        ]);

        foreach (['возобновляемой', 'солнечная энергия', 'энергетика', '620.9'] as $term) {
            $this->assertSame(1, $this->catalog->search(subject: $term)['meta']['total']);
        }

        $this->getJson('/api/v1/catalog-db?subject='.urlencode('солнечная энергия'))
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    public function test_availability_distinguishes_the_states_of_master_8_3(): void
    {
        $onShelf = $this->record();
        $this->copy($onShelf, ['status' => 'available']);

        $allIssued = $this->record();
        $this->copy($allIssued, ['status' => 'issued']);

        $inRepair = $this->record();
        $this->copy($inRepair, ['status' => 'under_repair']);

        $processing = $this->record();
        $this->copy($processing, ['status' => 'in_processing']);

        $electronicOnly = $this->record();
        ElectronicMaterial::query()->create([
            'bibliographic_record_id' => $electronicOnly->getKey(),
            'title' => 'PDF версия',
            'file_type' => 'pdf',
            'access_level' => 'authenticated',
            'is_active' => true,
            'workflow_status' => 'published',
        ]);

        $this->assertSame(1, $this->catalog->search(availability: 'available')['meta']['total']);
        $this->assertSame(1, $this->catalog->search(availability: 'issued')['meta']['total']);
        $this->assertSame(1, $this->catalog->search(availability: 'repair')['meta']['total']);
        $this->assertSame(1, $this->catalog->search(availability: 'processing')['meta']['total']);
        $this->assertSame(1, $this->catalog->search(availability: 'electronic_only')['meta']['total']);
    }

    public function test_a_record_with_one_free_copy_counts_as_available_even_when_others_are_out(): void
    {
        $record = $this->record();
        $this->copy($record, ['status' => 'issued']);
        $this->copy($record, ['status' => 'available']);

        $this->assertSame(1, $this->catalog->search(availability: 'available')['meta']['total']);
        $this->assertSame(0, $this->catalog->search(availability: 'issued')['meta']['total'], 'A record is only "issued" when nothing is left on the shelf.');
    }

    public function test_format_distinguishes_print_electronic_and_hybrid(): void
    {
        $print = $this->record();
        $this->copy($print);

        $electronic = $this->record();
        ElectronicMaterial::query()->create([
            'bibliographic_record_id' => $electronic->getKey(),
            'title' => 'Только электронная версия',
            'file_type' => 'pdf',
            'access_level' => 'public',
            'is_active' => true,
            'workflow_status' => 'published',
        ]);

        $hybrid = $this->record();
        $this->copy($hybrid);
        ElectronicMaterial::query()->create([
            'bibliographic_record_id' => $hybrid->getKey(),
            'title' => 'Электронная версия печатного издания',
            'file_type' => 'pdf',
            'access_level' => 'authenticated',
            'is_active' => true,
            'workflow_status' => 'published',
        ]);

        $this->assertSame(1, $this->catalog->search(format: 'print')['meta']['total']);
        $this->assertSame(1, $this->catalog->search(format: 'electronic')['meta']['total']);
        $this->assertSame(1, $this->catalog->search(format: 'hybrid')['meta']['total']);
    }

    public function test_active_but_unpublished_digital_material_is_not_a_public_holding(): void
    {
        $published = $this->record(['title' => 'Published digital']);
        ElectronicMaterial::query()->create([
            'bibliographic_record_id' => $published->getKey(),
            'title' => 'Public PDF',
            'file_type' => 'pdf',
            'access_level' => 'public',
            'is_active' => true,
            'workflow_status' => 'published',
        ]);

        $draft = $this->record(['title' => 'Workflow draft']);
        ElectronicMaterial::query()->create([
            'bibliographic_record_id' => $draft->getKey(),
            'title' => 'Unreviewed PDF',
            'file_type' => 'pdf',
            'access_level' => 'public',
            'is_active' => true,
            'workflow_status' => 'metadata_review',
        ]);

        $this->assertSame(1, $this->catalog->search(format: 'electronic')['meta']['total']);
        $this->assertSame(1, $this->catalog->search(availability: 'electronic_only')['meta']['total']);
        $this->assertSame(1, $this->catalog->search(availability: 'no_holdings')['meta']['total']);

        $items = collect($this->catalog->search(limit: 10)['data'])
            ->keyBy(fn (array $item): string => $item['title']['display']);
        $this->assertSame('electronic', $items['Published digital']['indicators']['format']);
        $this->assertSame('electronic_only', $items['Published digital']['indicators']['availability']);
        $this->assertSame('metadata_only', $items['Workflow draft']['indicators']['format']);
        $this->assertSame('no_holdings', $items['Workflow draft']['indicators']['availability']);
    }

    public function test_incomplete_records_remain_visible_in_the_public_catalogue(): void
    {
        $this->record(['title' => 'Готовая запись']);
        BibliographicRecord::factory()->draft()->create(['title' => 'Незавершённая запись']);

        $result = $this->catalog->search();
        $titles = array_map(static fn (array $row): string => $row['title']['display'], $result['data']);

        $this->assertContains('Готовая запись', $titles);
        $this->assertContains(
            'Незавершённая запись',
            $titles,
            'Data Cleanup status must not hide a real holding from readers.',
        );
    }

    public function test_presentable_only_slice_excludes_drafts_and_blank_titles_for_homepage(): void
    {
        $presentable = $this->record(['title' => 'Презентабельное поступление']);
        BibliographicRecord::factory()->draft()->create(['title' => '[Без заглавия; MARC DOC_ID 17903]']);

        $result = $this->catalog->search(sort: 'recently_added', completeOnly: true);

        $this->assertSame(1, $result['meta']['total']);
        $this->assertSame((string) $presentable->getKey(), $result['data'][0]['id']);
    }

    public function test_result_indicators_cover_availability_format_supply_popularity_newness_and_access(): void
    {
        $featured = $this->record(['title' => 'Indicator reference record']);
        $this->copy($featured, [
            'registration_date' => now()->toDateString(),
            'access_restriction' => 'reading_room',
            'issue_count' => 50,
        ]);

        // Ten records make the documented top-decile rule deterministic:
        // the only record with circulation is the one popular result.
        BibliographicRecord::factory()->count(9)->create(['is_draft' => false]);

        $item = collect($this->catalog->search(limit: 20)['data'])
            ->firstWhere('id', (string) $featured->getKey());

        $this->assertSame('available', $item['indicators']['availability']);
        $this->assertSame('print', $item['indicators']['format']);
        $this->assertSame('last_copy', $item['indicators']['copySupply']);
        $this->assertSame('reading_room', $item['indicators']['accessRestriction']);
        $this->assertTrue($item['indicators']['popular']);
        $this->assertTrue($item['indicators']['newArrival']);
        $this->assertSame(50, $item['indicators']['issueCount']);
    }

    public function test_result_availability_distinguishes_issued_processing_repair_and_no_holdings(): void
    {
        foreach ([
            'issued' => 'issued',
            'in_processing' => 'in_processing',
            'under_repair' => 'under_repair',
        ] as $title => $status) {
            $record = $this->record(['title' => $title]);
            $this->copy($record, ['status' => $status]);
        }
        $this->record(['title' => 'no_holdings']);

        $items = collect($this->catalog->search(limit: 20)['data'])
            ->keyBy(fn (array $item): string => $item['title']['display']);

        $this->assertSame('issued', $items['issued']['indicators']['availability']);
        $this->assertSame('in_processing', $items['in_processing']['indicators']['availability']);
        $this->assertSame('under_repair', $items['under_repair']['indicators']['availability']);
        $this->assertSame('no_holdings', $items['no_holdings']['indicators']['availability']);
        $this->assertSame('metadata_only', $items['no_holdings']['indicators']['format']);
    }

    public function test_noncirculating_live_copy_is_unavailable_not_no_holdings(): void
    {
        $record = $this->record(['title' => 'Reserved stock']);
        $this->copy($record, ['status' => 'reserved_stock']);

        $item = $this->catalog->search()['data'][0];

        $this->assertSame(1, $item['copies']['total']);
        $this->assertSame('unavailable', $item['indicators']['availability']);
        $this->assertSame('print', $item['indicators']['format']);
        $this->assertSame(0, $this->catalog->search(availability: 'no_holdings')['meta']['total']);
    }

    public function test_lost_and_written_off_copies_do_not_affect_public_indicators_or_sums(): void
    {
        $record = $this->record(['title' => 'Truthful holding totals']);
        $this->copy($record, [
            'issue_count' => 2,
            'registration_date' => now()->subMonths(2)->toDateString(),
            'access_restriction' => 'free',
        ]);
        $this->copy($record, [
            'status' => 'lost',
            'issue_count' => 500,
            'registration_date' => now()->toDateString(),
            'access_restriction' => 'limited',
        ]);
        $this->copy($record, [
            'status' => 'written_off',
            'issue_count' => 400,
            'registration_date' => now()->toDateString(),
            'access_restriction' => 'reading_room',
        ]);

        $item = $this->catalog->search()['data'][0];

        $this->assertSame(1, $item['copies']['total']);
        $this->assertSame(2, $item['indicators']['issueCount']);
        $this->assertSame('free', $item['indicators']['accessRestriction']);
        $this->assertFalse($item['indicators']['newArrival']);
    }

    public function test_facets_report_only_values_present_in_the_collection(): void
    {
        $record = $this->record(['resource_type' => 'textbook', 'category' => 'technology', 'language' => 'kk', 'udc_code' => '004.8']);
        $this->copy($record, [
            'fund_id' => $this->fundId('EDUCATIONAL'),
            'branch_id' => $this->branchId('SCIENTIFIC-LIBRARY'),
        ]);

        $facets = $this->catalog->facets();

        $this->assertSame([['value' => 'textbook', 'count' => 1]], $facets['resource_types']);
        $this->assertSame([['value' => 'technology', 'count' => 1]], $facets['categories']);
        $this->assertSame('EDUCATIONAL', $facets['funds'][0]['value']);
        $this->assertSame('Учебный фонд', $facets['funds'][0]['label']);
        $this->assertSame('SCIENTIFIC-LIBRARY', $facets['branches'][0]['value']);

        // The UDC facet rolls subdivisions up to their top-level class.
        $this->assertSame('004', $facets['udc'][0]['value']);
        $this->assertStringContainsString('Ақпараттық технологиялар', $facets['udc'][0]['label']);
    }

    public function test_language_facet_always_lists_every_interface_language(): void
    {
        $this->record(['language' => 'ru']);

        $languages = collect($this->catalog->facets()['languages']);

        $this->assertSame(['ru', 'kk', 'en', 'other'], $languages->pluck('value')->all());
        $this->assertSame(1, $languages->firstWhere('value', 'ru')['count']);
        // A language with nothing catalogued yet stays listed at zero so the
        // sidebar does not reshuffle as records arrive.
        $this->assertSame(0, $languages->firstWhere('value', 'en')['count']);
    }

    public function test_year_bounds_follow_the_data_rather_than_a_hardcoded_range(): void
    {
        $this->record(['publication_year' => 1957]);
        $this->record(['publication_year' => 2011]);

        $this->assertSame(['min' => 1957, 'max' => 2011], $this->catalog->facets()['years']);
    }

    public function test_facet_counts_match_what_the_filter_actually_returns(): void
    {
        BibliographicRecord::factory()->count(4)->create(['is_draft' => false, 'resource_type' => 'book']);
        BibliographicRecord::factory()->count(2)->create(['is_draft' => false, 'resource_type' => 'periodical']);

        foreach ($this->catalog->facets()['resource_types'] as $facet) {
            $this->assertSame(
                $facet['count'],
                $this->catalog->search(resourceType: $facet['value'])['meta']['total'],
                "Facet count for {$facet['value']} disagrees with the filter it drives.",
            );
        }
    }

    public function test_the_facets_endpoint_is_public_and_returns_live_axes(): void
    {
        $record = $this->record(['resource_type' => 'book']);
        $this->copy($record, ['fund_id' => $this->fundId('MAIN')]);

        $payload = $this->getJson('/api/v1/catalog-facets')->assertOk()->json('data');

        foreach (['resource_types', 'languages', 'categories', 'funds', 'branches', 'udc', 'availability', 'formats', 'years', 'total'] as $key) {
            $this->assertArrayHasKey($key, $payload);
        }
        $this->assertSame(1, $payload['total']);
    }

    public function test_the_api_exposes_every_new_axis(): void
    {
        $record = $this->record(['resource_type' => 'textbook', 'category' => 'economics']);
        $this->copy($record, [
            'fund_id' => $this->fundId('UNIVERSITY-ECONOMIC'),
            'branch_id' => $this->branchId('ECONOMICS-DESK'),
        ]);
        $this->record(['resource_type' => 'book', 'category' => 'history']);

        foreach ([
            'resource_type=textbook',
            'category=economics',
            'fund=UNIVERSITY-ECONOMIC',
            'branch=ECONOMICS-DESK',
            'format=print',
        ] as $filter) {
            $total = $this->getJson('/api/v1/catalog-db?'.$filter)->assertOk()->json('meta.total');
            $this->assertSame(1, $total, "Filter {$filter} did not narrow the result set as expected.");
        }
    }

    public function test_the_api_rejects_an_unknown_availability_or_format(): void
    {
        $this->getJson('/api/v1/catalog-db?availability=nonsense')->assertUnprocessable();
        $this->getJson('/api/v1/catalog-db?format=papyrus')->assertUnprocessable();
    }

    public function test_filters_combine_and_paginate_together(): void
    {
        for ($i = 0; $i < 15; $i++) {
            $record = $this->record(['resource_type' => 'book', 'category' => 'technology']);
            $this->copy($record, ['fund_id' => $this->fundId('MAIN')]);
        }
        $this->record(['resource_type' => 'periodical', 'category' => 'periodicals']);

        $page1 = $this->catalog->search(page: 1, limit: 12, resourceType: 'book', category: 'technology', fund: 'MAIN');
        $this->assertSame(15, $page1['meta']['total']);
        $this->assertCount(12, $page1['data']);

        $page2 = $this->catalog->search(page: 2, limit: 12, resourceType: 'book', category: 'technology', fund: 'MAIN');
        $this->assertCount(3, $page2['data'], 'Pagination must apply after filtering, not before.');
    }
}
