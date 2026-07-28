<?php

namespace Tests\Feature\Api;

use App\Services\Library\IsbnService;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class CatalogEnrichmentTest extends TestCase
{
    private bool $useLivePgsql = false;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->canUseLivePgsql()) {
            $this->useLivePgsql = true;
            Config::set('database.default', 'pgsql');
            DB::purge('pgsql');
        }
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

    private function requireLivePgsql(): void
    {
        if (! $this->useLivePgsql) {
            $this->markTestSkipped('Live PostgreSQL not available.');
        }
    }

    private function staffSession(): array
    {
        return [
            'library.user' => [
                'id' => 'aaaaaaaa-0000-0000-0000-111111111111',
                'role' => 'librarian',
                'email' => 'staff@digital-library.test',
                'name' => 'Test Staff',
            ],
        ];
    }

    // ── ISBN Validation Unit Tests ──────────────────────────

    public function test_isbn13_valid_checksum(): void
    {
        $svc = new IsbnService();
        $result = $svc->validate('978-3-16-148410-0');
        $this->assertTrue($result['valid']);
        $this->assertEquals('ISBN-13', $result['format']);
        $this->assertEquals('9783161484100', $result['isbn']);
    }

    public function test_isbn13_invalid_checksum(): void
    {
        $svc = new IsbnService();
        $result = $svc->validate('978-3-16-148410-9');
        $this->assertFalse($result['valid']);
        $this->assertEquals('ISBN-13', $result['format']);
        $this->assertNotNull($result['error']);
    }

    public function test_isbn10_valid_checksum(): void
    {
        $svc = new IsbnService();
        $result = $svc->validate('0-306-40615-2');
        $this->assertTrue($result['valid']);
        $this->assertEquals('ISBN-10', $result['format']);
        $this->assertEquals('0306406152', $result['isbn']);
    }

    public function test_isbn10_with_x_check_digit(): void
    {
        $svc = new IsbnService();
        $result = $svc->validate('0-8044-2957-X');
        $this->assertTrue($result['valid']);
        $this->assertEquals('ISBN-10', $result['format']);
    }

    public function test_isbn10_invalid_checksum(): void
    {
        $svc = new IsbnService();
        $result = $svc->validate('0-306-40615-3');
        $this->assertFalse($result['valid']);
    }

    public function test_isbn_wrong_length(): void
    {
        $svc = new IsbnService();
        $result = $svc->validate('12345');
        $this->assertFalse($result['valid']);
        $this->assertNull($result['format']);
        $this->assertStringContainsString('length', $result['error']);
    }

    public function test_isbn10_to_isbn13_conversion(): void
    {
        $svc = new IsbnService();
        $isbn13 = $svc->isbn10to13('0-306-40615-2');
        $this->assertEquals('9780306406157', $isbn13);

        $validation = $svc->validate($isbn13);
        $this->assertTrue($validation['valid']);
    }

    public function test_isbn_normalize(): void
    {
        $svc = new IsbnService();
        $this->assertEquals('9783161484100', $svc->normalize('978-3-16-148410-0'));
        $this->assertEquals('080442957X', $svc->normalize('0-8044-2957-x'));
    }

    // ── Enrichment API Route Tests ─────────────────────────

    public function test_enrichment_stats_route_exists(): void
    {
        $response = $this->withSession($this->staffSession())
            ->getJson('/api/v1/internal/enrichment/stats');

        $this->assertNotEquals(404, $response->status(), 'Route should exist');
        $this->assertNotEquals(405, $response->status(), 'Route should accept GET');
    }

    public function test_enrichment_stats_returns_gap_data(): void
    {
        $this->requireLivePgsql();

        $response = $this->withSession($this->staffSession())
            ->getJson('/api/v1/internal/enrichment/stats');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'totalDocuments',
                'gaps' => ['missingIsbn', 'invalidIsbn', 'validIsbn', 'missingTitle', 'missingYear', 'missingLanguage', 'missingPublisher'],
                'enrichableByIsbn',
            ],
        ]);

        $this->assertGreaterThan(0, $response->json('data.totalDocuments'));
    }

    public function test_validate_isbn_requires_document_id(): void
    {
        $response = $this->withSession($this->staffSession())
            ->postJson('/api/v1/internal/enrichment/validate-isbn', []);

        $response->assertStatus(422);
    }

    public function test_validate_isbn_for_real_document(): void
    {
        $this->requireLivePgsql();

        $doc = DB::connection('pgsql')->table('app.documents')
            ->whereNotNull('isbn_normalized')
            ->where('isbn_normalized', '!=', '')
            ->first();

        if ($doc === null) {
            $this->markTestSkipped('No documents with ISBN available.');
        }

        $response = $this->withSession($this->staffSession())
            ->postJson('/api/v1/internal/enrichment/validate-isbn', [
                'document_id' => $doc->id,
            ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['documentId', 'isbn', 'validation' => ['isbn', 'format', 'valid'], 'updated'],
        ]);
        $this->assertTrue($response->json('data.updated'));
    }

    public function test_bulk_validate_route_works(): void
    {
        $this->requireLivePgsql();

        $docs = DB::connection('pgsql')->table('app.documents')
            ->whereNotNull('isbn_normalized')
            ->limit(3)
            ->pluck('id')
            ->map(fn ($id) => (string) $id)
            ->all();

        if (empty($docs)) {
            $this->markTestSkipped('No documents with ISBN available.');
        }

        $response = $this->withSession($this->staffSession())
            ->postJson('/api/v1/internal/enrichment/bulk-validate', [
                'document_ids' => $docs,
            ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => ['processed', 'valid', 'invalid', 'noIsbn', 'results'],
        ]);
        $this->assertGreaterThan(0, $response->json('data.processed'));
    }

    public function test_check_isbn_pure_validation(): void
    {
        $response = $this->withSession($this->staffSession())
            ->postJson('/api/v1/internal/enrichment/check-isbn', [
                'isbn' => '978-3-16-148410-0',
            ]);

        $response->assertOk();
        $response->assertJson(['data' => ['valid' => true, 'format' => 'ISBN-13']]);
    }

    public function test_check_isbn_invalid(): void
    {
        $response = $this->withSession($this->staffSession())
            ->postJson('/api/v1/internal/enrichment/check-isbn', [
                'isbn' => '978-3-16-148410-9',
            ]);

        $response->assertOk();
        $response->assertJson(['data' => ['valid' => false]]);
    }

    public function test_lookup_rejects_invalid_uuid(): void
    {
        $response = $this->withSession($this->staffSession())
            ->getJson('/api/v1/internal/enrichment/lookup/not-a-uuid');

        $response->assertStatus(422);
    }

    public function test_lookup_returns_404_for_nonexistent_document(): void
    {
        $response = $this->withSession($this->staffSession())
            ->getJson('/api/v1/internal/enrichment/lookup/00000000-0000-0000-0000-000000000000');

        $response->assertStatus(404);
    }

    public function test_apply_rejects_empty_fields(): void
    {
        $response = $this->withSession($this->staffSession())
            ->postJson('/api/v1/internal/enrichment/apply/00000000-0000-0000-0000-000000000001', [
                'fields' => [],
            ]);

        $response->assertStatus(422);
    }

    public function test_apply_rejects_invalid_field_values(): void
    {
        $response = $this->withSession($this->staffSession())
            ->postJson('/api/v1/internal/enrichment/apply/00000000-0000-0000-0000-000000000001', [
                'fields' => ['publication_year' => 999],
            ]);

        $response->assertStatus(422);
    }
}
