<?php

namespace Tests\Feature\Api;

use App\Models\Library\CirculationAuditEvent;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InternalCopyMutationTest extends TestCase
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

    public function test_create_copy_successfully_inserts_copy_and_audit_event(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for internal copy create test.');

        [$documentId, $branchId, $siglaId] = $this->pickCreateReferenceSet();

        $response = $this->withSession($this->staffSession())->postJson('/api/v1/internal/copies', [
            'document_id' => $documentId,
            'branch_id' => $branchId,
            'sigla_id' => $siglaId,
            'inventory_number' => 'TEST-COPY-' . Str::upper(Str::random(8)),
            'needs_review' => true,
            'review_reason_codes' => ['MANUAL_CREATE'],
            'request_id' => 'copy-create-request',
            'correlation_id' => 'copy-create-correlation',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.parentDocument.documentId', $documentId)
            ->assertJsonPath('data.branch.branchId', $branchId)
            ->assertJsonPath('data.location.siglaId', $siglaId)
            ->assertJsonPath('data.lifecycle.needsReview', true)
            ->assertJsonPath('source', 'app.book_copies');

        $copyId = (string) $response->json('data.copyIdentity.id');

        $this->assertDatabaseHas('app.book_copies', [
            'id' => $copyId,
            'document_id' => $documentId,
            'branch_id' => $branchId,
            'sigla_id' => $siglaId,
            'needs_review' => true,
        ], 'pgsql');

        $audit = CirculationAuditEvent::query()
            ->where('action', 'internal_copy_created')
            ->where('entity_type', 'copy')
            ->where('entity_id', $copyId)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame($documentId, $audit->new_state['parentDocument']['documentId'] ?? null);
    }

    public function test_create_copy_returns_400_for_invalid_request_body(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for internal copy create validation test.');

        [$documentId, $branchId] = $this->pickCreateReferenceSet();

        $response = $this->withSession($this->staffSession())->postJson('/api/v1/internal/copies', [
            'document_id' => $documentId,
            'branch_id' => $branchId,
        ]);

        $response
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'invalid_request_body');
    }

    public function test_patch_copy_successfully_updates_review_fields_and_audit_event(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for internal copy patch test.');

        $copyId = $this->pickCopyId();

        $response = $this->withSession($this->staffSession())->patchJson('/api/v1/internal/copies/' . $copyId, [
            'needs_review' => true,
            'review_reason_codes' => ['MANUAL_PATCH'],
            'request_id' => 'copy-patch-request',
            'correlation_id' => 'copy-patch-correlation',
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.copyIdentity.id', $copyId)
            ->assertJsonPath('data.lifecycle.needsReview', true)
            ->assertJsonPath('data.lifecycle.reviewReasonCodes.0', 'MANUAL_PATCH');

        $row = DB::connection('pgsql')
            ->table('app.book_copies')
            ->select(['needs_review', 'review_reason_codes'])
            ->where('id', $copyId)
            ->first();

        $this->assertNotNull($row);
        $this->assertTrue((bool) $row->needs_review);
        $this->assertStringContainsString('MANUAL_PATCH', (string) $row->review_reason_codes);

        $audit = CirculationAuditEvent::query()
            ->where('action', 'internal_copy_updated')
            ->where('entity_type', 'copy')
            ->where('entity_id', $copyId)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame(true, $audit->new_state['lifecycle']['needsReview'] ?? null);
    }

    public function test_patch_copy_rejects_forbidden_field(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for internal copy patch forbidden-field test.');

        $copyId = $this->pickCopyId();

        $response = $this->withSession($this->staffSession())->patchJson('/api/v1/internal/copies/' . $copyId, [
            'branch_id' => (string) Str::uuid(),
        ]);

        $response
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'unsupported_mutation_field');
    }

    public function test_patch_copy_returns_not_found_for_missing_copy(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for internal copy patch not-found test.');

        $response = $this->withSession($this->staffSession())->patchJson('/api/v1/internal/copies/' . Str::uuid(), [
            'needs_review' => true,
        ]);

        $response
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'copy_not_found');
    }

    public function test_patch_copy_rejects_invalid_uuid(): void
    {
        $response = $this->withSession($this->staffSession())->patchJson('/api/v1/internal/copies/not-a-uuid', [
            'needs_review' => true,
        ]);

        $response
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'invalid_copy_id');
    }

    public function test_copy_mutation_endpoints_require_staff_session(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for internal copy mutation middleware test.');

        [$documentId, $branchId, $siglaId] = $this->pickCreateReferenceSet();

        $response = $this->postJson('/api/v1/internal/copies', [
            'document_id' => $documentId,
            'branch_id' => $branchId,
            'sigla_id' => $siglaId,
        ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'staff_authorization_required');
    }

    public function test_create_copy_rejects_non_admin_actor_override(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for actor override authorization test.');

        [$documentId, $branchId, $siglaId] = $this->pickCreateReferenceSet();

        $response = $this->withSession($this->staffSession('librarian'))->postJson('/api/v1/internal/copies', [
            'document_id' => $documentId,
            'branch_id' => $branchId,
            'sigla_id' => $siglaId,
            'actor_user_id' => (string) Str::uuid(),
        ]);

        $response
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'insufficient_staff_role');
    }

    public function test_create_copy_allows_admin_actor_override(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for admin actor override authorization test.');

        [$documentId, $branchId, $siglaId] = $this->pickCreateReferenceSet();

        $response = $this->withSession($this->staffSession('admin'))->postJson('/api/v1/internal/copies', [
            'document_id' => $documentId,
            'branch_id' => $branchId,
            'sigla_id' => $siglaId,
            'inventory_number' => 'TEST-COPY-' . Str::upper(Str::random(8)),
            'actor_user_id' => (string) Str::uuid(),
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true);
    }

    /**
     * @return array{string, string, string}
     */
    private function pickCreateReferenceSet(): array
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL is not available for internal copy mutation tests.');
        }

        $documentId = DB::connection('pgsql')->table('app.documents')->value('id');
        $row = DB::connection('pgsql')
            ->table('app.siglas as s')
            ->join('app.branches as b', 'b.id', '=', 's.branch_id')
            ->select(['b.id as branch_id', 's.id as sigla_id'])
            ->first();

        if (! is_string($documentId) || $documentId === '' || $row === null) {
            $this->markTestSkipped('Required document/branch/location references are not available for mutation tests.');
        }

        return [$documentId, (string) $row->branch_id, (string) $row->sigla_id];
    }

    private function pickCopyId(): string
    {
        $copyId = DB::connection('pgsql')
            ->table('app.book_copies')
            ->value('id');

        if (! is_string($copyId) || $copyId === '') {
            $this->markTestSkipped('No copy is available in current database snapshot.');
        }

        return $copyId;
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