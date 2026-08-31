<?php

namespace Tests\Feature;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\Contributor;
use App\Models\Catalog\LegacyMarcField;
use App\Models\Catalog\LegacyMarcRecord;
use App\Models\Catalog\Subject;
use App\Services\Library\BookDetailReadService;
use App\Services\Library\CatalogReadService;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\DB;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class CatalogRecoveryIntegrationTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();

        foreach ([
            'database/migrations/2026_08_28_100000_create_marc_recovery_model.php',
            'database/migrations/2026_08_28_100100_extend_catalogue_for_marc_recovery.php',
        ] as $path) {
            (require base_path($path))->up();
        }
    }

    public function test_cataloguer_can_save_and_resynchronise_recovered_metadata_relations(): void
    {
        $cataloguer = $this->makeControlPlaneUser('cataloguer');

        $this->signInToLibraryAs($cataloguer)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.catalog.store'), $this->payload())
            ->assertRedirect();

        $record = BibliographicRecord::query()->where('control_number', 'KAZUTB-CN-100')->sole();
        $this->assertSame('Алматы', $record->publication_place);
        $this->assertSame('2-е переработанное издание', $record->edition_statement);
        $this->assertSame('1234-5678', $record->issn);
        $this->assertSame('Университетская серия', $record->series_title);
        $this->assertSame('240 с.', $record->physical_extent);
        $this->assertCount(2, $record->contributors()->get());
        $this->assertCount(2, $record->subjects()->get());
        $this->assertDatabaseHas('bibliographic_record_contributor', [
            'bibliographic_record_id' => $record->id,
            'role' => 'editor',
            'marc_tag' => '700',
        ]);
        $this->assertDatabaseHas('bibliographic_record_subject', [
            'bibliographic_record_id' => $record->id,
            'marc_tag' => '650',
        ]);

        $updated = $this->payload([
            'edition_statement' => '3-е издание',
            'contributors' => [[
                'name' => 'Смағұлова Б. Б.',
                'role' => 'translator',
                'kind' => 'person',
                'marc_tag' => '700',
            ]],
            'subjects' => [[
                'term' => 'Теория вероятностей',
                'scheme' => 'topical',
                'marc_tag' => '650',
            ]],
        ]);

        $this->patch(route('librarian.catalog.update', $record), $updated)->assertRedirect();

        $record->refresh();
        $this->assertSame('3-е издание', $record->edition_statement);
        $this->assertSame(['Смағұлова Б. Б.'], $record->contributors()->pluck('name')->all());
        $this->assertSame('translator', $record->contributors()->firstOrFail()->pivot->role);
        $this->assertSame(['Теория вероятностей'], $record->subjects()->pluck('term')->all());
    }

    public function test_catalog_form_loads_source_marc_as_read_only_fields(): void
    {
        $cataloguer = $this->makeControlPlaneUser('cataloguer');
        $record = BibliographicRecord::factory()->create(['control_number' => 'CN-RAW-9']);
        $batchId = DB::table('legacy_import_batches')->insertGetId([
            'package_name' => 'catalog-recovery-test.zip',
            'package_sha256' => str_repeat('a', 64),
            'status' => 'applied',
            'created_at' => now('UTC'),
            'updated_at' => now('UTC'),
        ]);
        LegacyMarcRecord::query()->create([
            'legacy_import_batch_id' => $batchId,
            'source_doc_id' => 9565,
            'source_hash' => str_repeat('b', 64),
            'leader' => '00000nam a2200000 i 4500',
            'control_number' => 'CN-RAW-9',
            'bibliographic_record_id' => $record->id,
            'apply_status' => 'updated',
        ]);
        LegacyMarcField::query()->create([
            'legacy_import_batch_id' => $batchId,
            'source_doc_id' => 9565,
            'tag' => '245',
            'indicator1' => '1',
            'indicator2' => '0',
            'subfield_code' => 'a',
            'value' => 'Восстановленное заглавие MARC',
            'occurrence' => 0,
        ]);

        $this->signInToLibraryAs($cataloguer)
            ->get(route('librarian.catalog.edit', $record))
            ->assertOk()
            ->assertSee('data-testid="raw-marc-readonly"', false)
            ->assertSee('00000nam a2200000 i 4500')
            ->assertSee('Восстановленное заглавие MARC')
            ->assertSee('$a', false)
            ->assertDontSee('name="legacy_marc', false);

        $librarian = $this->makeControlPlaneUser('librarian');
        $this->signInToLibraryAs($librarian)
            ->get(route('librarian.catalog.edit', $record))
            ->assertOk()
            ->assertDontSee('data-testid="raw-marc-readonly"', false)
            ->assertDontSee('00000nam a2200000 i 4500')
            ->assertDontSee('Восстановленное заглавие MARC');
    }

    public function test_recovered_fields_flow_through_search_service_and_public_book_view_without_internal_provenance(): void
    {
        $record = BibliographicRecord::factory()->create($this->payload([
            'isbn' => '9783161484100',
            'notes' => 'INTERNAL ACQUISITION NOTE',
            'source_agency' => 'INTERNAL-SOURCE-AGENCY',
            'legacy_local_path' => '/private/import/source.mrc',
            'legacy_import_batch_id' => 77,
        ], includeRelations: false));
        $editor = Contributor::query()->create([
            'name' => 'Нұрғалиева Р. Т.',
            'normalized_name' => Contributor::normalizeName('Нұрғалиева Р. Т.'),
            'kind' => 'person',
        ]);
        $subject = Subject::query()->create([
            'term' => 'Теория вероятностей',
            'normalized_term' => Subject::normalizeTerm('Теория вероятностей'),
            'scheme' => 'topical',
        ]);
        $record->contributors()->attach($editor->id, ['role' => 'editor', 'position' => 0, 'marc_tag' => '700']);
        $record->subjects()->attach($subject->id, ['position' => 0, 'marc_tag' => '650']);

        foreach (['Нұрғалиева', 'Теория вероятностей', 'Университетская серия', 'KAZUTB-CN-100', '12345678', 'Алматы'] as $needle) {
            $this->assertTrue(
                BibliographicRecord::query()->search($needle)->whereKey($record->id)->exists(),
                "Recovery search did not find [{$needle}].",
            );
        }

        $catalog = app(CatalogReadService::class)->search(query: 'Теория вероятностей', limit: 10);
        $this->assertSame((string) $record->id, $catalog['data'][0]['id']);
        $this->assertSame('Теория вероятностей', $catalog['data'][0]['subjects'][0]['term']);

        $detail = app(BookDetailReadService::class)->findByIdentifier((string) $record->id);
        $this->assertSame('Алматы', $detail['publicationPlace']);
        $this->assertSame('2-е переработанное издание', $detail['editionStatement']);
        $this->assertSame('1234-5678', $detail['issn']['raw']);
        $this->assertSame('240 с.', $detail['physicalExtent']);
        $this->assertSame('Университетская серия', $detail['seriesTitle']);
        $this->assertSame('Теория вероятностей', $detail['subjects'][0]['term']);
        $this->assertSame('Нұрғалиева Р. Т.', $detail['contributors'][0]['name']);
        $this->assertSame('', $detail['notes']);
        $this->assertArrayNotHasKey('sourceAgency', $detail);
        $this->assertArrayNotHasKey('legacyLocalPath', $detail);
        $this->assertArrayNotHasKey('rawMarc', $detail);

        $response = $this->get('/book/'.$record->id.'?lang=ru')
            ->assertOk()
            ->assertDontSee('INTERNAL ACQUISITION NOTE')
            ->assertDontSee('INTERNAL-SOURCE-AGENCY')
            ->assertDontSee('/private/import/source.mrc');
        foreach (['Алматы', 'Университетская серия', 'Теория вероятностей'] as $publicValue) {
            $this->assertStringContainsString((string) json_encode($publicValue), $response->getContent());
        }
    }

    /** @return array<string, mixed> */
    private function payload(array $overrides = [], bool $includeRelations = true): array
    {
        $payload = [
            'title' => 'Восстановленная библиографическая запись',
            'subtitle' => 'Практическое пособие',
            'primary_author' => 'Әбілқасымова Г. Қ.',
            'publisher' => 'Ғылым',
            'publication_place' => 'Алматы',
            'statement_of_responsibility' => 'Әбілқасымова Г. Қ. ; под редакцией Нұрғалиевой Р. Т.',
            'edition_statement' => '2-е переработанное издание',
            'publication_year' => 2026,
            'language' => 'ru',
            'udc_code' => '519.2',
            'bbk_code' => '22.171',
            'local_classification' => 'LOCAL-MATH-9',
            'author_mark' => 'Ә14',
            'annotation' => 'Проверка полного потока восстановленных библиографических метаданных.',
            'keywords' => "вероятность\nстатистика",
            'isbn' => '9783161484100',
            'issn' => '1234-5678',
            'physical_extent' => '240 с.',
            'physical_details' => 'ил., табл.',
            'dimensions' => '24 см',
            'accompanying_material' => '1 электронный диск',
            'series_title' => 'Университетская серия',
            'series_number' => '12',
            'volume' => '2',
            'issue' => '4',
            'part_number' => '1',
            'part_title' => 'Прикладные методы',
            'control_number' => 'KAZUTB-CN-100',
            'country_code' => 'kz',
            'cataloging_language' => 'ru',
            'source_agency' => 'KazUTB',
            'material_designation' => 'текст',
            'resource_type' => 'book',
        ];

        if ($includeRelations) {
            $payload['contributors'] = [
                ['name' => 'Нұрғалиева Р. Т.', 'role' => 'editor', 'kind' => 'person', 'marc_tag' => '700'],
                ['name' => 'Smith Academic Group', 'role' => 'other', 'kind' => 'organisation', 'marc_tag' => '710'],
            ];
            $payload['subjects'] = [
                ['term' => 'Теория вероятностей', 'scheme' => 'topical', 'marc_tag' => '650'],
                ['term' => 'Қазақстан', 'scheme' => 'geographic', 'marc_tag' => '651'],
            ];
        } else {
            $payload['keywords'] = ['вероятность', 'статистика'];
        }

        return array_replace($payload, $overrides);
    }
}
