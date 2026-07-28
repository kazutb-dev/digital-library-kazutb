<?php

namespace Tests\Feature\Api\Integration;

use App\Services\Library\IntegrationDocumentManagementException;
use App\Services\Library\IntegrationDocumentManagementService;
use Mockery;
use Mockery\MockInterface;
use RuntimeException;
use Tests\TestCase;

class DocumentManagementTest extends TestCase
{
    /** @var array<string, string> */
    private array $headers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->headers = [
            'Authorization' => 'Bearer integration-test-token',
            'X-Request-Id' => 'req-doc-001',
            'X-Correlation-Id' => 'corr-doc-001',
            'X-Source-System' => 'crm',
            'X-Operator-Id' => 'crm-op-99',
            'X-Operator-Roles' => 'documents.read,documents.write',
            'X-Operator-Org-Context' => '{"branch_id":"f84341eb-1010-45be-b93e-6c94cd9cea8a"}',
        ];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    public function test_list_success(): void
    {
        /** @var MockInterface&IntegrationDocumentManagementService $mock */
        $mock = Mockery::mock(IntegrationDocumentManagementService::class);
        $mock->shouldReceive('listDocuments')
            ->once()
            ->with('', 1, 20)
            ->andReturn([
                'data' => [$this->sampleDocument()],
                'meta' => ['page' => 1, 'per_page' => 20, 'total' => 1, 'pages' => 1],
            ]);

        $this->app->instance(IntegrationDocumentManagementService::class, $mock);

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/integration/v1/documents');

        $response->assertOk()
            ->assertJsonPath('data.0.id', 'd4f798b5-357e-4969-b4d0-224583f609a9')
            ->assertJsonPath('meta.total', 1)
            ->assertJsonPath('request_id', 'req-doc-001');
    }

    public function test_detail_success(): void
    {
        $id = 'd4f798b5-357e-4969-b4d0-224583f609a9';

        /** @var MockInterface&IntegrationDocumentManagementService $mock */
        $mock = Mockery::mock(IntegrationDocumentManagementService::class);
        $mock->shouldReceive('findDocument')
            ->once()
            ->with($id)
            ->andReturn($this->sampleDocument());

        $this->app->instance(IntegrationDocumentManagementService::class, $mock);

        $response = $this->withHeaders($this->headers)
            ->getJson("/api/integration/v1/documents/{$id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.title', 'Clean Architecture');
    }

    public function test_create_success(): void
    {
        /** @var MockInterface&IntegrationDocumentManagementService $mock */
        $mock = Mockery::mock(IntegrationDocumentManagementService::class);
        $mock->shouldReceive('createDocument')
            ->once()
            ->with(Mockery::type('array'), Mockery::type('array'))
            ->andReturn($this->sampleDocument());

        $this->app->instance(IntegrationDocumentManagementService::class, $mock);

        $response = $this->withHeaders($this->headers)
            ->postJson('/api/integration/v1/documents', [
                'title' => 'Clean Architecture',
                'isbn' => '978-0134494166',
                'publication_year' => 2017,
                'language' => 'en',
            ]);

        $response->assertStatus(201)
            ->assertJsonPath('data.title', 'Clean Architecture');
    }

    public function test_patch_success(): void
    {
        $id = 'd4f798b5-357e-4969-b4d0-224583f609a9';

        /** @var MockInterface&IntegrationDocumentManagementService $mock */
        $mock = Mockery::mock(IntegrationDocumentManagementService::class);
        $mock->shouldReceive('patchDocument')
            ->once()
            ->with($id, Mockery::type('array'), Mockery::type('array'))
            ->andReturn(array_merge($this->sampleDocument(), [
                'title' => 'Clean Architecture (2nd)',
            ]));

        $this->app->instance(IntegrationDocumentManagementService::class, $mock);

        $response = $this->withHeaders($this->headers)
            ->patchJson("/api/integration/v1/documents/{$id}", [
                'title' => 'Clean Architecture (2nd)',
            ]);

        $response->assertOk()
            ->assertJsonPath('data.title', 'Clean Architecture (2nd)');
    }

    public function test_archive_success(): void
    {
        $id = 'd4f798b5-357e-4969-b4d0-224583f609a9';

        /** @var MockInterface&IntegrationDocumentManagementService $mock */
        $mock = Mockery::mock(IntegrationDocumentManagementService::class);
        $mock->shouldReceive('archiveDocument')
            ->once()
            ->with($id, Mockery::type('array'))
            ->andReturn(array_merge($this->sampleDocument(), [
                'status' => 'ARCHIVED',
            ]));

        $this->app->instance(IntegrationDocumentManagementService::class, $mock);

        $response = $this->withHeaders($this->headers)
            ->postJson("/api/integration/v1/documents/{$id}/archive");

        $response->assertOk()
            ->assertJsonPath('data.status', 'ARCHIVED');
    }

    public function test_validation_failure_returns_400(): void
    {
        $response = $this->withHeaders($this->headers)
            ->postJson('/api/integration/v1/documents', [
                'isbn' => '978-0134494166',
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.error_code', 'invalid_request')
            ->assertJsonPath('error.reason_code', 'invalid_request_body');
    }

    public function test_show_rejects_invalid_uuid(): void
    {
        $response = $this->withHeaders($this->headers)
            ->getJson('/api/integration/v1/documents/not-a-uuid');

        $response->assertStatus(400)
            ->assertJsonPath('error.error_code', 'invalid_request')
            ->assertJsonPath('error.reason_code', 'invalid_document_id');
    }

    public function test_patch_without_mutable_fields_returns_400(): void
    {
        $id = 'd4f798b5-357e-4969-b4d0-224583f609a9';

        $response = $this->withHeaders($this->headers)
            ->patchJson("/api/integration/v1/documents/{$id}", []);

        $response->assertStatus(400)
            ->assertJsonPath('error.error_code', 'invalid_request')
            ->assertJsonPath('error.reason_code', 'no_mutable_fields_provided');
    }

    public function test_list_rejects_per_page_above_allowed_range(): void
    {
        $response = $this->withHeaders($this->headers)
            ->getJson('/api/integration/v1/documents?per_page=101');

        $response->assertStatus(400)
            ->assertJsonPath('error.error_code', 'invalid_request')
            ->assertJsonPath('error.reason_code', 'invalid_filter_value');
    }

    public function test_create_rejects_publication_year_above_range(): void
    {
        $response = $this->withHeaders($this->headers)
            ->postJson('/api/integration/v1/documents', [
                'title' => 'Future Book',
                'publication_year' => 2501,
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.error_code', 'invalid_request')
            ->assertJsonPath('error.reason_code', 'invalid_request_body');
    }

    public function test_patch_rejects_empty_title(): void
    {
        $id = 'd4f798b5-357e-4969-b4d0-224583f609a9';

        $response = $this->withHeaders($this->headers)
            ->patchJson("/api/integration/v1/documents/{$id}", [
                'title' => '   ',
            ]);

        $response->assertStatus(400)
            ->assertJsonPath('error.error_code', 'invalid_request')
            ->assertJsonPath('error.reason_code', 'invalid_request_body');
    }

    public function test_not_found_returns_404(): void
    {
        $id = 'd4f798b5-357e-4969-b4d0-224583f609a9';

        /** @var MockInterface&IntegrationDocumentManagementService $mock */
        $mock = Mockery::mock(IntegrationDocumentManagementService::class);
        $mock->shouldReceive('patchDocument')
            ->once()
            ->andThrow(new IntegrationDocumentManagementException(
                errorCode: 'not_found',
                reasonCode: 'document_not_found',
                message: 'Document not found.',
                httpStatus: 404,
            ));

        $this->app->instance(IntegrationDocumentManagementService::class, $mock);

        $response = $this->withHeaders($this->headers)
            ->patchJson("/api/integration/v1/documents/{$id}", [
                'title' => 'Updated',
            ]);

        $response->assertStatus(404)
            ->assertJsonPath('error.reason_code', 'document_not_found');
    }

    public function test_boundary_enforcement_missing_bearer(): void
    {
        $headers = $this->headers;
        unset($headers['Authorization']);

        $response = $this->withHeaders($headers)
            ->getJson('/api/integration/v1/documents');

        $response->assertStatus(401)
            ->assertJsonPath('error.error_code', 'auth_failed')
            ->assertJsonPath('error.reason_code', 'missing_bearer_token');
    }

    public function test_unexpected_service_error_returns_consistent_500_envelope(): void
    {
        /** @var MockInterface&IntegrationDocumentManagementService $mock */
        $mock = Mockery::mock(IntegrationDocumentManagementService::class);
        $mock->shouldReceive('listDocuments')
            ->once()
            ->andThrow(new RuntimeException('db is unavailable'));

        $this->app->instance(IntegrationDocumentManagementService::class, $mock);

        $response = $this->withHeaders($this->headers)
            ->getJson('/api/integration/v1/documents');

        $response->assertStatus(500)
            ->assertJsonPath('error.error_code', 'server_error')
            ->assertJsonPath('error.reason_code', 'internal_failure')
            ->assertJsonPath('request_id', 'req-doc-001')
            ->assertJsonPath('correlation_id', 'corr-doc-001');
    }

    /**
     * @return array<string,mixed>
     */
    private function sampleDocument(): array
    {
        return [
            'id' => 'd4f798b5-357e-4969-b4d0-224583f609a9',
            'title' => 'Clean Architecture',
            'isbn' => '978-0134494166',
            'publisher_id' => null,
            'publication_year' => 2017,
            'language' => 'en',
            'description' => 'A guide to software structure.',
            'status' => null,
            'is_active' => null,
            'archived_at' => null,
            'needs_review' => false,
            'review_reason_codes' => [],
            'created_at' => null,
            'updated_at' => null,
            'source' => 'app.documents',
        ];
    }
}
