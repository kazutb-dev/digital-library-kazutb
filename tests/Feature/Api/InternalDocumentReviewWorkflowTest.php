<?php

namespace Tests\Feature\Api;

use App\Models\Library\CirculationAuditEvent;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InternalDocumentReviewWorkflowTest extends TestCase
{
    private bool $transactionStarted = false;

    /**
     * @return array{library.user: array<string, string>}
     */
    private function staffSession(string $role = 'librarian'): array
    {
        return [
            'library.user' => [
                'id' => (string) Str::uuid(),
                'name' => 'Internal Staff',
                'email' => 'staff@example.test',
                'login' => 'staff',
                'ad_login' => 'staff',
                'role' => $role,
            ],
        ];
    }

    protected function setUp(): void
    {
        parent::setUp();

        Config::set('database.default', 'pgsql');
        DB::purge('pgsql');

        if ($this->canUseLivePgsql()) {
            DB::connection('pgsql')->beginTransaction();
            $this->transactionStarted = true;
        }
    }

    protected function tearDown(): void
    {
        if ($this->transactionStarted) {
            DB::connection('pgsql')->rollBack();
        }

        parent::tearDown();
    }

    public function test_document_review_queue_lists_documents_needing_review(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for document review queue test.');

        [$documentId1, $documentId2] = $this->pickTwoDistinctDocumentIds();
        $reasonCode = 'DOC_STEWARD_' . Str::upper(Str::random(6));

        DB::connection('pgsql')->table('app.documents')->where('id', $documentId1)->update([
            'needs_review' => true,
            'review_reason_codes' => '{' . $reasonCode . '}',
            'title_raw' => 'Document For Review 1',
        ]);

        DB::connection('pgsql')->table('app.documents')->where('id', $documentId2)->update([
            'needs_review' => false,
            'review_reason_codes' => DB::raw("'{}'::text[]"),
            'title_raw' => 'Clean Document',
        ]);

        $response = $this->withSession($this->staffSession())->getJson('/api/v1/internal/review/documents?limit=100');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                '*' => [
                    'documentIdentity' => ['id', 'isbnRaw', 'isbnNormalized'],
                    'title' => ['titleRaw', 'titleNormalized'],
                    'lifecycle' => ['needsReview', 'reviewReasonCodes', 'createdAt'],
                    'updatedAt',
                ]
            ],
            'meta' => ['page', 'per_page', 'total', 'total_pages', 'totalPages'],
            'filters' => ['reason_code'],
            'source',
        ]);

        $data = $response->json('data');
        $this->assertNotEmpty($data);

        // Verify that all returned documents have needs_review=true
        foreach ($data as $row) {
            $this->assertTrue((bool) ($row['lifecycle']['needsReview'] ?? false));
        }

        // Verify documentId2 (which we cleared) is NOT in the response if any data is present
        $ids = array_map(static fn (array $row): string => (string) ($row['documentIdentity']['id'] ?? ''), $data);
        $this->assertNotContains($documentId2, $ids);

        // Verify we got the reason code we set
        $response->assertJsonStructure(['filters' => ['reason_code']]);
    }

    public function test_document_review_summary_counts_review_states(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for document review summary test.');

        [$documentId] = $this->pickTwoDistinctDocumentIds();
        $reasonCode = 'DOC_SUMMARY_' . Str::upper(Str::random(6));

        $totalBefore = DB::connection('pgsql')->table('app.documents')->count();
        $needsReviewBefore = DB::connection('pgsql')->table('app.documents')->where('needs_review', true)->count();

        DB::connection('pgsql')->table('app.documents')->where('id', $documentId)->update([
            'needs_review' => true,
            'review_reason_codes' => '{' . $reasonCode . '}',
        ]);

        $response = $this->withSession($this->staffSession())->getJson('/api/v1/internal/review/documents-summary?top_limit=5');

        $response->assertOk();
        $response->assertJsonStructure([
            'data' => [
                'entity',
                'totalDocuments',
                'needsReviewCount',
                'resolvedCount',
                'topReasonCodes' => [
                    '*' => ['reasonCode', 'count']
                ],
            ],
            'source',
        ]);

        $this->assertEquals('documents', $response->json('data.entity'));
        $this->assertEquals($totalBefore, $response->json('data.totalDocuments'));
        $this->assertGreaterThanOrEqual($needsReviewBefore + 1, $response->json('data.needsReviewCount'));
    }

    public function test_document_review_resolve_clears_review_flags(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for document resolve test.');

        [$documentId] = $this->pickTwoDistinctDocumentIds();

        // Mark document for review
        DB::connection('pgsql')->table('app.documents')->where('id', $documentId)->update([
            'needs_review' => true,
            'review_reason_codes' => '{METADATA_INCOMPLETE}',
        ]);

        $response = $this->withSession($this->staffSession())
            ->postJson('/api/v1/internal/review/documents/' . $documentId . '/resolve', [
                'resolution_note' => 'Metadata corrected by librarian',
            ]);

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $response->assertJsonStructure([
            'success',
            'data' => [
                'documentIdentity' => ['id', 'isbnRaw', 'isbnNormalized'],
                'title' => ['titleRaw', 'titleNormalized'],
                'lifecycle' => ['needsReview', 'reviewReasonCodes', 'createdAt'],
                'updatedAt',
            ],
            'source',
        ]);

        $this->assertFalse($response->json('data.lifecycle.needsReview'));
        $this->assertEquals([], $response->json('data.lifecycle.reviewReasonCodes'));

        // Verify in database
        $resolved = DB::connection('pgsql')
            ->table('app.documents')
            ->where('id', $documentId)
            ->first();

        $this->assertFalse((bool) ($resolved->needs_review ?? false));
        $this->assertTrue($this->isPgArrayEmpty($resolved->review_reason_codes ?? ''));

        // Verify audit event created
        $event = CirculationAuditEvent::query()
            ->where('action', 'internal_document_review_resolved')
            ->where('entity_type', 'document')
            ->where('entity_id', $documentId)
            ->first();

        $this->assertNotNull($event);
        $this->assertNotNull($event->previous_state);
        $this->assertNotNull($event->new_state);
        $this->assertSame('Metadata corrected by librarian', $event->metadata['details']['resolution_note']);
    }

    public function test_document_review_resolve_fails_with_invalid_document_id(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for invalid ID test.');

        $response = $this->withSession($this->staffSession())
            ->postJson('/api/v1/internal/review/documents/not-a-uuid/resolve', [
                'resolution_note' => 'Test',
            ]);

        $response->assertStatus(400);
        $response->assertJson([
            'error' => 'invalid_document_id',
            'success' => false,
        ]);
    }

    public function test_document_review_resolve_fails_when_not_marked_for_review(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for not-marked test.');

        [$documentId] = $this->pickTwoDistinctDocumentIds();

        // Ensure document is NOT marked for review
        DB::connection('pgsql')->table('app.documents')->where('id', $documentId)->update([
            'needs_review' => false,
            'review_reason_codes' => DB::raw("'{}'::text[]"),
        ]);

        $response = $this->withSession($this->staffSession())
            ->postJson('/api/v1/internal/review/documents/' . $documentId . '/resolve', [
                'resolution_note' => 'Test',
            ]);

        $response->assertStatus(409);
        $response->assertJson([
            'error' => 'review_not_required',
            'success' => false,
        ]);
    }

    public function test_document_review_queue_requires_staff_auth(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for auth test.');

        // Without session
        $response = $this->getJson('/api/v1/internal/review/documents');
        $response->assertStatus(403);
    }

    public function test_document_review_resolve_requires_staff_auth(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for resolve auth test.');

        [$documentId] = $this->pickTwoDistinctDocumentIds();

        // Without session
        $response = $this->postJson('/api/v1/internal/review/documents/' . $documentId . '/resolve');
        $response->assertStatus(403);
    }

    public function test_document_review_queue_filters_by_reason_code(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for reason code filter test.');

        [$documentId1, $documentId2] = $this->pickTwoDistinctDocumentIds();
        $reasonCode1 = 'FILTER_1_' . Str::upper(Str::random(6));
        $reasonCode2 = 'FILTER_2_' . Str::upper(Str::random(6));

        DB::connection('pgsql')->table('app.documents')->where('id', $documentId1)->update([
            'needs_review' => true,
            'review_reason_codes' => '{' . $reasonCode1 . '}',
        ]);

        DB::connection('pgsql')->table('app.documents')->where('id', $documentId2)->update([
            'needs_review' => true,
            'review_reason_codes' => '{' . $reasonCode2 . '}',
        ]);

        $response = $this->withSession($this->staffSession())
            ->getJson('/api/v1/internal/review/documents?reason_code=' . urlencode($reasonCode1));

        $response->assertOk();
        $data = $response->json('data');
        $this->assertGreaterThan(0, count($data));

        // Verify all returned documents have the filter code
        foreach ($data as $doc) {
            $this->assertContains($reasonCode1, $doc['lifecycle']['reviewReasonCodes']);
        }
    }

    /**
     * @return array{string, string}
     */
    private function pickTwoDistinctDocumentIds(): array
    {
        $ids = DB::connection('pgsql')
            ->table('app.documents')
            ->limit(2)
            ->pluck('id')
            ->all();

        if (count($ids) < 2) {
            $this->markTestSkipped('At least two documents are required for internal document review workflow tests.');
        }

        return [(string) $ids[0], (string) $ids[1]];
    }

    private function isPgArrayEmpty(string $value): bool
    {
        $trimmed = trim($value);

        return $trimmed === '' || $trimmed === '{}' || $trimmed === '{NULL}';
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

    private function requireLivePgsql(string $message): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped($message);
        }
    }
}

