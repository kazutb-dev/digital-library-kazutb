<?php

namespace Tests\Feature\Api;

use App\Models\Library\CirculationAuditEvent;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InternalDocumentFlagWorkflowTest extends TestCase
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

    public function test_flag_clean_document_sets_needs_review_and_reason_codes(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for document flag test.');

        $documentId = $this->pickOneDocumentId();
        $reasonCode = 'MISSING_ISBN_' . Str::upper(Str::random(6));

        // Ensure document starts clean
        DB::connection('pgsql')->table('app.documents')->where('id', $documentId)->update([
            'needs_review' => false,
            'review_reason_codes' => DB::raw("'{}'::text[]"),
        ]);

        $response = $this->withSession($this->staffSession())->postJson(
            '/api/v1/internal/review/documents/' . $documentId . '/flag',
            [
                'reason_codes' => [$reasonCode],
                'flag_note' => 'ISBN missing from catalog record.',
            ]
        );

        $response
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('data.lifecycle.needsReview', true)
            ->assertJsonPath('flagging.wasAlreadyFlagged', false);

        $this->assertContains($reasonCode, $response->json('data.lifecycle.reviewReasonCodes'));
        $this->assertContains($reasonCode, $response->json('flagging.addedReasonCodes'));

        // Verify in database
        $row = DB::connection('pgsql')->table('app.documents')->where('id', $documentId)->first();
        $this->assertTrue((bool) $row->needs_review);
    }

    public function test_flag_already_flagged_document_merges_codes_without_duplication(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for document flag merge test.');

        $documentId = $this->pickOneDocumentId();
        $existingCode = 'EXISTING_' . Str::upper(Str::random(6));
        $newCode = 'NEW_' . Str::upper(Str::random(6));

        // Pre-flag the document with one reason code
        DB::connection('pgsql')->table('app.documents')->where('id', $documentId)->update([
            'needs_review' => true,
            'review_reason_codes' => '{' . $existingCode . '}',
        ]);

        $response = $this->withSession($this->staffSession())->postJson(
            '/api/v1/internal/review/documents/' . $documentId . '/flag',
            [
                'reason_codes' => [$existingCode, $newCode],
            ]
        );

        $response
            ->assertOk()
            ->assertJson(['success' => true])
            ->assertJsonPath('flagging.wasAlreadyFlagged', true);

        $mergedCodes = $response->json('flagging.mergedReasonCodes');
        $addedCodes = $response->json('flagging.addedReasonCodes');

        // Existing code should be in merged but NOT in added (no duplication)
        $this->assertContains($existingCode, $mergedCodes);
        $this->assertContains($newCode, $mergedCodes);
        $this->assertNotContains($existingCode, $addedCodes);
        $this->assertContains($newCode, $addedCodes);

        // Merged should have exactly 2 unique codes
        $this->assertCount(2, $mergedCodes);
    }

    public function test_flag_writes_audit_event(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for document flag audit test.');

        $documentId = $this->pickOneDocumentId();
        $reasonCode = 'AUDIT_FLAG_' . Str::upper(Str::random(6));

        DB::connection('pgsql')->table('app.documents')->where('id', $documentId)->update([
            'needs_review' => false,
            'review_reason_codes' => DB::raw("'{}'::text[]"),
        ]);

        $this->withSession($this->staffSession())->postJson(
            '/api/v1/internal/review/documents/' . $documentId . '/flag',
            [
                'reason_codes' => [$reasonCode],
                'flag_note' => 'Flagged for data quality cleanup.',
            ]
        );

        $audit = CirculationAuditEvent::query()
            ->where('action', 'internal_document_review_flagged')
            ->where('entity_type', 'document')
            ->where('entity_id', $documentId)
            ->first();

        $this->assertNotNull($audit, 'Audit event should be created for flag action.');
        $this->assertFalse($audit->previous_state['lifecycle']['needsReview'] ?? true);
        $this->assertTrue($audit->new_state['lifecycle']['needsReview'] ?? false);
        $this->assertSame('Flagged for data quality cleanup.', $audit->metadata['details']['flag_note'] ?? null);
        $this->assertContains($reasonCode, $audit->metadata['details']['requested_reason_codes'] ?? []);
        $this->assertFalse($audit->metadata['details']['was_already_flagged'] ?? true);
    }

    public function test_flag_rejects_invalid_uuid(): void
    {
        $response = $this->withSession($this->staffSession())->postJson(
            '/api/v1/internal/review/documents/not-a-uuid/flag',
            ['reason_codes' => ['SOME_CODE']]
        );

        $response
            ->assertStatus(400)
            ->assertJson([
                'error' => 'invalid_document_id',
                'success' => false,
            ]);
    }

    public function test_flag_returns_404_for_nonexistent_document(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for document not found test.');

        $fakeId = (string) Str::uuid();

        $response = $this->withSession($this->staffSession())->postJson(
            '/api/v1/internal/review/documents/' . $fakeId . '/flag',
            ['reason_codes' => ['SOME_CODE']]
        );

        $response
            ->assertStatus(404)
            ->assertJson([
                'error' => 'document_not_found',
                'success' => false,
            ]);
    }

    public function test_flag_requires_staff_session(): void
    {
        $fakeId = (string) Str::uuid();

        $response = $this->postJson(
            '/api/v1/internal/review/documents/' . $fakeId . '/flag',
            ['reason_codes' => ['SOME_CODE']]
        );

        $response
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'staff_authorization_required');
    }

    public function test_flag_requires_at_least_one_reason_code(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for validation test.');

        $documentId = $this->pickOneDocumentId();

        $response = $this->withSession($this->staffSession())->postJson(
            '/api/v1/internal/review/documents/' . $documentId . '/flag',
            ['reason_codes' => []]
        );

        $response->assertStatus(422);
    }

    public function test_flag_rejects_non_admin_actor_override(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for actor override test.');

        $documentId = $this->pickOneDocumentId();

        $response = $this->withSession($this->staffSession('librarian'))->postJson(
            '/api/v1/internal/review/documents/' . $documentId . '/flag',
            [
                'reason_codes' => ['SOME_CODE'],
                'actor_user_id' => (string) Str::uuid(),
            ]
        );

        $response
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'insufficient_staff_role');
    }

    public function test_flag_then_resolve_round_trip(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for flag-resolve round trip test.');

        $documentId = $this->pickOneDocumentId();
        $reasonCode = 'ROUNDTRIP_' . Str::upper(Str::random(6));

        // Start clean
        DB::connection('pgsql')->table('app.documents')->where('id', $documentId)->update([
            'needs_review' => false,
            'review_reason_codes' => DB::raw("'{}'::text[]"),
        ]);

        // Flag
        $flagResponse = $this->withSession($this->staffSession())->postJson(
            '/api/v1/internal/review/documents/' . $documentId . '/flag',
            ['reason_codes' => [$reasonCode]]
        );
        $flagResponse->assertOk()->assertJsonPath('data.lifecycle.needsReview', true);

        // Resolve
        $resolveResponse = $this->withSession($this->staffSession())->postJson(
            '/api/v1/internal/review/documents/' . $documentId . '/resolve',
            ['resolution_note' => 'Cleaned up after flag.']
        );
        $resolveResponse->assertOk()->assertJsonPath('data.lifecycle.needsReview', false);
        $this->assertSame([], $resolveResponse->json('data.lifecycle.reviewReasonCodes'));

        // Verify database is clean
        $row = DB::connection('pgsql')->table('app.documents')->where('id', $documentId)->first();
        $this->assertFalse((bool) $row->needs_review);
    }

    private function pickOneDocumentId(): string
    {
        $row = DB::connection('pgsql')->table('app.documents')->limit(1)->first();

        if ($row === null) {
            $this->markTestSkipped('At least one document is required for document flag workflow tests.');
        }

        return (string) $row->id;
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
