<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BibliographicRecordTranslation;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\Loan;
use App\Models\User;
use App\Services\DataQuality\DataQualityRuleRegistry;
use App\Services\Library\BookDetailReadService;
use App\Services\Library\CatalogReadService;
use App\Services\Localization\LocalizedContentResolver;
use App\Services\Reports\DirectorAnalyticsService;
use Illuminate\Database\QueryException;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class MultilingualCatalogueFullStackTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo_users.enabled' => true]);
        $this->setUpAdminControlPlane();
    }

    public function test_original_metadata_is_preserved_and_public_resolver_uses_only_reviewed_translation(): void
    {
        $record = BibliographicRecord::factory()->create([
            'title' => 'Оригинальное русское название',
            'annotation' => 'Оригинальная аннотация',
            'keywords' => ['экономика'],
            'language' => 'ru',
        ]);
        $record->translations()->create([
            'locale' => 'kk',
            'title' => 'Қазақстан экономикасы',
            'annotation' => 'Қазақша аңдатпа ә ғ қ ң ө ұ ү һ і',
            'keywords' => ['экономика', 'Қазақстан'],
            'translation_status' => 'approved',
            'source' => 'manual_translation',
        ]);
        $record->translations()->create([
            'locale' => 'en',
            'title' => 'Draft English title',
            'translation_status' => 'draft',
            'source' => 'manual_translation',
        ]);

        $resolver = app(LocalizedContentResolver::class);
        $kk = $resolver->bibliographic($record->fresh('translations'), 'kk');
        $en = $resolver->bibliographic($record->fresh('translations'), 'en');

        $this->assertSame('Қазақстан экономикасы', $kk['title']);
        $this->assertSame('Оригинальное русское название', $kk['original_title']);
        $this->assertSame('Оригинальное русское название', $en['title']);
        $this->assertTrue($en['is_fallback']);
        $this->assertSame('Оригинальное русское название', $record->fresh()->title);
    }

    public function test_search_and_public_read_models_resolve_all_supported_metadata_locales_without_n_plus_one(): void
    {
        $record = BibliographicRecord::factory()->create(['title' => 'Исходное название', 'language' => 'ru']);
        foreach ([
            'kk' => ['Қазақ тіліндегі атау', 'Қазақша аңдатпа', ['кітапхана']],
            'ru' => ['Русская языковая версия', 'Русская аннотация', ['наука']],
            'en' => ['English metadata title', 'English annotation', ['research']],
        ] as $locale => [$title, $annotation, $keywords]) {
            $record->translations()->create(compact('locale', 'title', 'annotation', 'keywords') + [
                'translation_status' => 'reviewed',
                'source' => 'manual_translation',
            ]);
        }

        foreach (['Қазақ тіліндегі', 'Русская языковая', 'English metadata', 'кітапхана', 'research'] as $term) {
            $this->assertTrue(BibliographicRecord::query()->search($term)->whereKey($record)->exists(), $term);
        }

        app()->setLocale('kk');
        $catalog = app(CatalogReadService::class)->search(query: 'Қазақ тіліндегі', limit: 10);
        $this->assertSame('Қазақ тіліндегі атау', $catalog['data'][0]['title']['display']);
        $this->assertSame('ru', $catalog['data'][0]['language']['code']);

        app()->setLocale('en');
        $book = app(BookDetailReadService::class)->findByIdentifier((string) $record->getKey());
        $this->assertSame('English metadata title', $book['title']['display']);
        $this->assertSame('Исходное название', $book['title']['original']);

        $this->getJson('/api/v1/catalog-db?lang=en&q='.rawurlencode('English metadata'))
            ->assertOk()
            ->assertJsonPath('data.0.title.display', 'English metadata title')
            ->assertJsonPath('data.0.title.original', 'Исходное название');

        $resourceLanguage = app(CatalogReadService::class)->search(query: 'English metadata', language: 'kk', limit: 10);
        $this->assertSame(0, $resourceLanguage['meta']['total'], 'Metadata locale must never be treated as the resource-language facet.');
        $resourceLanguage = app(CatalogReadService::class)->search(query: 'English metadata', language: 'ru', limit: 10);
        $this->assertSame(1, $resourceLanguage['meta']['total']);

        $this->withSession(['locale' => 'kk'])->get('/catalog?q='.rawurlencode('Қазақ тіліндегі'))
            ->assertOk()
            ->assertSee('Қазақ тіліндегі атау')
            ->assertSee('Исходное название');
    }

    public function test_translation_editor_enforces_rbac_unique_locale_and_audits_changes(): void
    {
        $record = BibliographicRecord::factory()->create(['title' => 'Original']);
        $cataloguer = $this->makeControlPlaneUser('cataloguer');

        $this->signInToLibraryAs($cataloguer)
            ->patch(route('librarian.catalog.update', $record), $this->recordPayload($record) + [
                'translations' => [
                    'kk' => [
                        'title' => 'Қазақша атау',
                        'annotation' => '<b>Қауіпсіз мәтін</b>',
                        'keywords' => "бір\nекі",
                        'translation_status' => 'reviewed',
                        'source' => 'manual_translation',
                    ],
                ],
            ])
            ->assertRedirect(route('librarian.catalog.edit', $record));

        $translation = BibliographicRecordTranslation::query()->where('bibliographic_record_id', $record->getKey())->sole();
        $this->assertSame('Қазақша атау', $translation->title);
        $this->assertSame('Қауіпсіз мәтін', $translation->annotation);
        $this->assertSame(['бір', 'екі'], $translation->keywords);
        $this->assertSame($cataloguer->getKey(), $translation->reviewed_by);
        $this->assertTrue(ActivityLog::query()->where('action_type', 'metadata.translation.create')->where('entity_id', (string) $record->getKey())->exists());

        $reader = $this->makeControlPlaneUser('member');
        $this->signInToLibraryAs($reader)
            ->patch(route('librarian.catalog.update', $record), $this->recordPayload($record))
            ->assertForbidden();

        $this->expectException(QueryException::class);
        $record->translations()->create([
            'locale' => 'kk', 'title' => 'Duplicate', 'translation_status' => 'approved', 'source' => 'manual_translation',
        ]);
    }

    public function test_executive_reporting_uses_localized_title_with_original_fallback(): void
    {
        $reader = User::factory()->create(['is_active' => true]);
        $translated = BibliographicRecord::factory()->create(['title' => 'Original report title']);
        $translated->translations()->create([
            'locale' => 'kk', 'title' => 'Есептегі қазақша атау', 'translation_status' => 'approved', 'source' => 'manual_translation',
        ]);
        $copy = BookCopy::factory()->create(['bibliographic_record_id' => $translated->getKey()]);
        Loan::factory()->create(['user_id' => $reader->getKey(), 'copy_id' => $copy->getKey(), 'issued_at' => now(), 'status' => 'active']);

        app()->setLocale('kk');
        $top = app(DirectorAnalyticsService::class)->build(['period' => 'month'])['top_resources'];
        $this->assertSame('Есептегі қазақша атау', $top[0]['label']);

        app()->setLocale('en');
        $fallback = app(DirectorAnalyticsService::class)->build(['period' => 'month'])['top_resources'];
        $this->assertSame('Original report title', $fallback[0]['label']);
    }

    public function test_data_quality_detects_translation_review_and_identical_title_issues(): void
    {
        $record = BibliographicRecord::factory()->create(['title' => 'Одинаковое название', 'language' => 'ru']);
        $record->translations()->create([
            'locale' => 'kk',
            'title' => 'Одинаковое название',
            'translation_status' => 'needs_review',
            'source' => 'manual_translation',
        ]);

        $codes = collect(app(DataQualityRuleRegistry::class)->inspect($record))->pluck('code');
        $this->assertTrue($codes->contains('bib.translation.identical'));
        $this->assertTrue($codes->contains('bib.translation.needs_review'));
    }

    private function recordPayload(BibliographicRecord $record): array
    {
        return [
            'title' => $record->title,
            'primary_author' => $record->primary_author,
            'publisher' => $record->publisher,
            'publication_year' => $record->publication_year,
            'language' => $record->language,
            'annotation' => $record->annotation,
            'resource_type' => $record->resource_type,
            'needs_manual_review' => 0,
        ];
    }
}
