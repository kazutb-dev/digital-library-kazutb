<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class SubjectCatalogTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'pgsql');
        DB::purge('pgsql');
    }

    public function test_subjects_endpoint_returns_grouped_structure(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL not available.');
        }

        $response = $this->getJson('/api/v1/subjects');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'faculties' => [['id', 'label', 'documentCount']],
                'departments' => [['id', 'label', 'documentCount']],
                'specializations' => [['id', 'label', 'documentCount']],
            ]);

        foreach (['faculties', 'departments', 'specializations'] as $group) {
            foreach ($response->json($group) as $item) {
                $this->assertNotEmpty($item['id']);
                $this->assertNotEmpty($item['label']);
                $this->assertGreaterThan(0, $item['documentCount']);
            }
        }
    }

    public function test_catalog_db_subject_filter_returns_matching_results(): void
    {
        $subjectId = $this->firstSubjectId();

        $response = $this->getJson('/api/v1/catalog-db?subject_id=' . $subjectId . '&limit=5');

        $response->assertOk();
        $this->assertGreaterThan(0, $response->json('meta.total'));
    }

    public function test_catalog_db_subject_filter_with_invalid_uuid_returns_422(): void
    {
        $this->getJson('/api/v1/catalog-db?subject_id=not-a-uuid')
            ->assertUnprocessable();
    }

    public function test_catalog_db_without_subject_filter_still_works(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL not available.');
        }

        $response = $this->getJson('/api/v1/catalog-db?limit=2');

        $response
            ->assertOk()
            ->assertJsonStructure([
                'data',
                'meta' => ['page', 'per_page', 'total', 'totalPages'],
            ]);
    }

    public function test_catalog_db_results_include_classification_array(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL not available.');
        }

        $response = $this->getJson('/api/v1/catalog-db?limit=5');
        $response->assertOk();

        foreach ($response->json('data') as $item) {
            $this->assertArrayHasKey('classification', $item);
            $this->assertIsArray($item['classification']);
        }
    }

    public function test_book_db_includes_classification(): void
    {
        $isbn = $this->firstIsbnWithSubjects();

        $response = $this->getJson('/api/v1/book-db/' . urlencode($isbn));

        $response->assertOk();
        $this->assertArrayHasKey('classification', $response->json('data'));
        $this->assertIsArray($response->json('data.classification'));
        $this->assertNotEmpty($response->json('data.classification'));

        $first = $response->json('data.classification.0');
        $this->assertArrayHasKey('id', $first);
        $this->assertArrayHasKey('label', $first);
        $this->assertArrayHasKey('sourceKind', $first);
    }

    private function firstSubjectId(): string
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL not available.');
        }

        $id = DB::table('app.document_subjects')
            ->value('subject_id');

        if (! $id) {
            $this->markTestSkipped('No subject mappings found.');
        }

        return $id;
    }

    private function firstIsbnWithSubjects(): string
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL not available.');
        }

        $isbn = DB::selectOne("
            SELECT dv.isbn_normalized
            FROM app.document_detail_v dv
            WHERE dv.isbn_normalized IS NOT NULL
              AND dv.subjects_json IS NOT NULL
              AND jsonb_array_length(dv.subjects_json) > 0
            LIMIT 1
        ");

        if (! $isbn) {
            $this->markTestSkipped('No book with subjects found.');
        }

        return $isbn->isbn_normalized;
    }

    private function canUseLivePgsql(): bool
    {
        try {
            DB::connection('pgsql')->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }
}
