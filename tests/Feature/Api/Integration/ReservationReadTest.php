<?php

namespace Tests\Feature\Api\Integration;

use App\Services\Library\IntegrationReservationReadService;
use Mockery;
use Mockery\MockInterface;
use Tests\TestCase;

/**
 * Narrow feature tests for the read-only external reservation API.
 *
 * The EnsureIntegrationBoundary middleware is exercised by every test that goes
 * through the route — we include one explicit boundary-enforcement case to confirm
 * the middleware is still active on these new routes.
 *
 * The IntegrationReservationReadService is mocked so that tests remain independent
 * of the PostgreSQL database (phpunit.xml configures SQLite :memory: for testing).
 */
class ReservationReadTest extends TestCase
{
    /** @var array<string, string> */
    private array $validHeaders;

    protected function setUp(): void
    {
        parent::setUp();

        $this->validHeaders = [
            'Authorization' => 'Bearer integration-test-token',
            'X-Request-Id' => 'req-rv-001',
            'X-Correlation-Id' => 'corr-rv-001',
            'X-Source-System' => 'crm',
            'X-Operator-Id' => 'crm-op-99',
            'X-Operator-Roles' => 'reservations.read',
            'X-Operator-Org-Context' => '{"branch_id":"main"}',
        ];
    }

    protected function tearDown(): void
    {
        Mockery::close();
        parent::tearDown();
    }

    // -------------------------------------------------------------------------
    // List endpoint
    // -------------------------------------------------------------------------

    public function test_list_returns_200_with_envelope_and_meta(): void
    {
        $this->mockServiceList([
            'data' => [$this->sampleReservation('4327164d-49ae-48ad-98c5-cff27c3aa8fc')],
            'meta' => ['page' => 1, 'per_page' => 20, 'total' => 1, 'pages' => 1],
        ]);

        $response = $this->withHeaders($this->validHeaders)
            ->getJson('/api/integration/v1/reservations');

        $response->assertOk()
            ->assertJsonStructure([
                'data' => [
                    ['id', 'status', 'reserved_at', 'expires_at', 'processed_at',
                        'library_branch_id', 'copy_id',
                        'reader_snapshot' => ['user_id', 'full_name', 'university_id'],
                        'book_snapshot' => ['book_id', 'isbn', 'title'],
                    ],
                ],
                'meta' => ['page', 'per_page', 'total', 'pages'],
                'request_id',
                'correlation_id',
                'timestamp',
            ])
            ->assertJsonPath('request_id', 'req-rv-001')
            ->assertJsonPath('correlation_id', 'corr-rv-001')
            ->assertJsonPath('meta.page', 1)
            ->assertJsonPath('meta.per_page', 20)
            ->assertJsonPath('meta.total', 1)
            ->assertHeader('X-Request-Id', 'req-rv-001')
            ->assertHeader('X-Correlation-Id', 'corr-rv-001');
    }

    public function test_list_empty_result_has_meta_total_zero(): void
    {
        $this->mockServiceList([
            'data' => [],
            'meta' => ['page' => 1, 'per_page' => 20, 'total' => 0, 'pages' => 1],
        ]);

        $response = $this->withHeaders($this->validHeaders)
            ->getJson('/api/integration/v1/reservations');

        $response->assertOk()
            ->assertJsonPath('data', [])
            ->assertJsonPath('meta.total', 0);
    }

    // -------------------------------------------------------------------------
    // Detail endpoint
    // -------------------------------------------------------------------------

    public function test_detail_returns_200_with_single_reservation(): void
    {
        $id = '4327164d-49ae-48ad-98c5-cff27c3aa8fc';

        /** @var MockInterface&IntegrationReservationReadService $mock */
        $mock = Mockery::mock(IntegrationReservationReadService::class);
        $mock->shouldReceive('findById')
            ->once()
            ->with($id, '{"branch_id":"main"}')
            ->andReturn($this->sampleReservation($id));

        $this->app->instance(IntegrationReservationReadService::class, $mock);

        $response = $this->withHeaders($this->validHeaders)
            ->getJson("/api/integration/v1/reservations/{$id}");

        $response->assertOk()
            ->assertJsonPath('data.id', $id)
            ->assertJsonPath('data.status', 'PENDING')
            ->assertJsonStructure([
                'data' => [
                    'id', 'status', 'reserved_at', 'expires_at', 'processed_at',
                    'library_branch_id', 'copy_id',
                    'reader_snapshot' => ['user_id', 'full_name', 'university_id'],
                    'book_snapshot' => ['book_id', 'isbn', 'title'],
                ],
                'request_id',
                'correlation_id',
                'timestamp',
            ])
            ->assertJsonPath('request_id', 'req-rv-001');
    }

    public function test_detail_not_found_returns_404_with_error_envelope(): void
    {
        $id = 'aaaabbbb-cccc-dddd-eeee-ffffaaaabbbb';

        /** @var MockInterface&IntegrationReservationReadService $mock */
        $mock = Mockery::mock(IntegrationReservationReadService::class);
        $mock->shouldReceive('findById')
            ->once()
            ->andReturn(null);

        $this->app->instance(IntegrationReservationReadService::class, $mock);

        $response = $this->withHeaders($this->validHeaders)
            ->getJson("/api/integration/v1/reservations/{$id}");

        $response->assertStatus(404)
            ->assertJsonPath('error.error_code', 'not_found')
            ->assertJsonPath('error.reason_code', 'reservation_not_found')
            ->assertJsonPath('request_id', 'req-rv-001')
            ->assertJsonStructure([
                'error' => ['error_code', 'reason_code', 'message'],
                'request_id',
                'correlation_id',
                'timestamp',
            ]);
    }

    // -------------------------------------------------------------------------
    // Filter validation
    // -------------------------------------------------------------------------

    public function test_invalid_status_filter_returns_400(): void
    {
        // Validation fires before the service is called — no mock needed.
        $response = $this->withHeaders($this->validHeaders)
            ->getJson('/api/integration/v1/reservations?status=NOT_A_VALID_STATUS');

        $response->assertStatus(400)
            ->assertJsonPath('error.error_code', 'invalid_request')
            ->assertJsonPath('error.reason_code', 'invalid_filter_value')
            ->assertJsonPath('request_id', 'req-rv-001');
    }

    public function test_invalid_user_id_filter_returns_400(): void
    {
        $response = $this->withHeaders($this->validHeaders)
            ->getJson('/api/integration/v1/reservations?user_id=not-a-uuid');

        $response->assertStatus(400)
            ->assertJsonPath('error.error_code', 'invalid_request')
            ->assertJsonPath('error.reason_code', 'invalid_filter_value');
    }

    public function test_invalid_per_page_returns_400(): void
    {
        $response = $this->withHeaders($this->validHeaders)
            ->getJson('/api/integration/v1/reservations?per_page=999');

        $response->assertStatus(400)
            ->assertJsonPath('error.error_code', 'invalid_request')
            ->assertJsonPath('error.reason_code', 'invalid_filter_value');
    }

    // -------------------------------------------------------------------------
    // Boundary enforcement — middleware still active on these routes
    // -------------------------------------------------------------------------

    public function test_missing_bearer_token_is_rejected_on_list(): void
    {
        $headers = $this->validHeaders;
        unset($headers['Authorization']);

        $response = $this->withHeaders($headers)
            ->getJson('/api/integration/v1/reservations');

        $response->assertStatus(401)
            ->assertJsonPath('error.error_code', 'auth_failed')
            ->assertJsonPath('error.reason_code', 'missing_bearer_token');
    }

    public function test_missing_bearer_token_is_rejected_on_detail(): void
    {
        $headers = $this->validHeaders;
        unset($headers['Authorization']);

        $response = $this->withHeaders($headers)
            ->getJson('/api/integration/v1/reservations/4327164d-49ae-48ad-98c5-cff27c3aa8fc');

        $response->assertStatus(401)
            ->assertJsonPath('error.error_code', 'auth_failed');
    }

    // -------------------------------------------------------------------------
    // Helpers
    // -------------------------------------------------------------------------

    /**
     * Bind a mock service that returns $result for listReservations().
     *
     * @param  array{data: list<array<string, mixed>>, meta: array<string, int>}  $result
     */
    private function mockServiceList(array $result): void
    {
        /** @var MockInterface&IntegrationReservationReadService $mock */
        $mock = Mockery::mock(IntegrationReservationReadService::class);
        $mock->shouldReceive('listReservations')
            ->once()
            ->andReturn($result);

        $this->app->instance(IntegrationReservationReadService::class, $mock);
    }

    /**
     * Build a minimal valid reservation shape for assertions.
     *
     * @return array<string, mixed>
     */
    private function sampleReservation(string $id): array
    {
        return [
            'id' => $id,
            'status' => 'PENDING',
            'reserved_at' => '2026-03-18T09:53:50.000000Z',
            'expires_at' => '2026-03-28T09:53:50.000000Z',
            'processed_at' => null,
            'library_branch_id' => 'f84341eb-1010-45be-b93e-6c94cd9cea8a',
            'copy_id' => null,
            'reader_snapshot' => [
                'user_id' => '817f1875-e4d6-469e-b8d5-b5916497e4ec',
                'full_name' => 'Aibek Nurlanov',
                'university_id' => 'STU-2021-0042',
            ],
            'book_snapshot' => [
                'book_id' => 'd4f798b5-357e-4969-b4d0-224583f609a9',
                'isbn' => '978-0134610993',
                'title' => 'Clean Architecture',
            ],
        ];
    }
}
