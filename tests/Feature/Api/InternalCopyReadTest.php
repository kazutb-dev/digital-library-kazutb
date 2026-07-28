<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InternalCopyReadTest extends TestCase
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

    public function test_copy_detail_returns_operational_payload(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for copy detail success test.');

        [, $copyId] = $this->pickDocumentAndCopyWithRelation();

        $response = $this->withSession($this->staffSession())->getJson('/api/v1/internal/copies/' . $copyId);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.copyIdentity.id', $copyId)
            ->assertJsonStructure([
                'success',
                'data' => [
                    'copyIdentity' => ['id', 'coreCopyId', 'legacyInventoryId', 'inventoryNumber'],
                    'parentDocument' => ['documentId', 'legacyDocumentId', 'title'],
                    'branch' => ['branchId', 'campusId', 'institutionUnitId', 'branchHint'],
                    'location' => ['siglaId', 'mappingStatus', 'mappingConfidence'],
                    'fundOwnership' => ['institutionUnitId'],
                    'lifecycle' => ['stateCode', 'needsReview', 'reviewReasonCodes', 'registeredAt'],
                    'circulation' => ['hasActiveLoan', 'activeLoanId', 'availabilityIndicator'],
                    'timestamps' => ['createdAt', 'updatedAt'],
                    'source',
                ],
            ]);
    }

    public function test_copy_detail_returns_not_found_for_missing_copy(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for copy not-found test.');

        $missingCopyId = (string) Str::uuid();

        $response = $this->withSession($this->staffSession())->getJson('/api/v1/internal/copies/' . $missingCopyId);

        $response
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'copy_not_found');
    }

    public function test_copy_detail_rejects_invalid_uuid(): void
    {
        $response = $this->withSession($this->staffSession())->getJson('/api/v1/internal/copies/not-a-uuid');

        $response
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'invalid_copy_id');
    }

    public function test_document_copies_returns_list_for_existing_document(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for list-by-document success test.');

        [$documentId] = $this->pickDocumentAndCopyWithRelation();

        $response = $this->withSession($this->staffSession())->getJson('/api/v1/internal/documents/' . $documentId . '/copies');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.documentId', $documentId)
            ->assertJsonPath('data.0.parentDocument.documentId', $documentId);
    }

    public function test_document_copies_returns_not_found_for_missing_document(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for document not-found test.');

        $missingDocumentId = (string) Str::uuid();

        $response = $this->withSession($this->staffSession())->getJson('/api/v1/internal/documents/' . $missingDocumentId . '/copies');

        $response
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'document_not_found');
    }

    public function test_document_copies_returns_empty_list_when_document_has_no_copies(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for list-by-document empty-result test.');

        $documentId = $this->pickDocumentWithoutCopies();

        $response = $this->withSession($this->staffSession())->getJson('/api/v1/internal/documents/' . $documentId . '/copies');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('meta.documentId', $documentId)
            ->assertJsonPath('meta.total', 0)
            ->assertJsonCount(0, 'data');
    }

    public function test_document_copies_rejects_invalid_uuid(): void
    {
        $response = $this->withSession($this->staffSession())->getJson('/api/v1/internal/documents/not-a-uuid/copies');

        $response
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'invalid_document_id');
    }

    public function test_copy_read_endpoints_require_staff_session(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for staff middleware behavior test.');

        [, $copyId] = $this->pickDocumentAndCopyWithRelation();

        $response = $this->getJson('/api/v1/internal/copies/' . $copyId);

        $response
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'staff_authorization_required');
    }

    /**
     * @return array{string,string}
     */
    private function pickDocumentAndCopyWithRelation(): array
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL is not available for internal copy read tests.');
        }

        $row = DB::connection('pgsql')
            ->table('app.book_copies as bc')
            ->join('app.documents as d', 'd.id', '=', 'bc.document_id')
            ->select(['d.id as document_id', 'bc.id as copy_id'])
            ->orderByDesc('bc.created_at')
            ->first();

        if ($row === null) {
            $this->markTestSkipped('No copy->document relation is available in current database snapshot.');
        }

        return [
            (string) $row->document_id,
            (string) $row->copy_id,
        ];
    }

    private function pickDocumentWithoutCopies(): string
    {
        $documentId = DB::connection('pgsql')
            ->table('app.documents as d')
            ->leftJoin('app.book_copies as bc', 'bc.document_id', '=', 'd.id')
            ->whereNull('bc.id')
            ->value('d.id');

        if (! is_string($documentId) || $documentId === '') {
            $this->markTestSkipped('No document without copies is available in current database snapshot.');
        }

        return $documentId;
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
