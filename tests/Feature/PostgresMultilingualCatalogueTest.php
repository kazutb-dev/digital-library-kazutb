<?php

namespace Tests\Feature;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BibliographicRecordTranslation;
use App\Services\Localization\LocalizedContentResolver;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PostgresMultilingualCatalogueTest extends TestCase
{
    use RefreshDatabase;

    public function test_postgresql_constraints_unicode_search_and_fallback_are_real(): void
    {
        $this->assertSame('pgsql', config('database.default'));
        $this->assertStringEndsWith('_test', (string) config('database.connections.pgsql.database'));

        $record = BibliographicRecord::query()->create([
            'title' => 'Оригинал', 'language' => 'ru', 'resource_type' => 'book', 'keywords' => [], 'is_draft' => true,
        ]);
        $record->translations()->create([
            'locale' => 'kk', 'title' => 'ӘҒҚҢӨҰҮҺІ кітапханасы', 'annotation' => 'Қазақша мәтін',
            'keywords' => ['ғылым'], 'translation_status' => 'approved', 'source' => 'manual_translation',
        ]);

        $this->assertTrue(BibliographicRecord::query()->search('әғқңөұүһі')->whereKey($record)->exists());
        $this->assertTrue(BibliographicRecord::query()->search('ғылым')->whereKey($record)->exists());
        $this->assertSame('ӘҒҚҢӨҰҮҺІ кітапханасы', app(LocalizedContentResolver::class)->bibliographic($record->fresh('translations'), 'kk')['title']);
        $this->assertSame('Оригинал', app(LocalizedContentResolver::class)->bibliographic($record->fresh('translations'), 'en')['title']);

        $this->expectException(QueryException::class);
        BibliographicRecordTranslation::query()->create([
            'bibliographic_record_id' => $record->getKey(), 'locale' => 'de', 'title' => 'Ungültig',
            'translation_status' => 'approved', 'source' => 'manual_translation',
        ]);
    }
}
