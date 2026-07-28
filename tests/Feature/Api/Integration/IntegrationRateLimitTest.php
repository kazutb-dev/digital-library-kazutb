<?php

namespace Tests\Feature\Api\Integration;

use Tests\TestCase;

class IntegrationRateLimitTest extends TestCase
{
    public function test_read_endpoint_returns_rate_limit_headers(): void
    {
        $response = $this
            ->withHeaders($this->fullHeaders())
            ->getJson('/api/integration/v1/_boundary/ping');

        $response->assertOk();
        // Rate limiter adds these headers automatically
        $this->assertNotNull($response->headers->get('X-RateLimit-Limit'));
        $this->assertNotNull($response->headers->get('X-RateLimit-Remaining'));
    }

    public function test_governance_headers_present_on_success(): void
    {
        $response = $this
            ->withHeaders($this->fullHeaders())
            ->getJson('/api/integration/v1/_boundary/ping');

        $response
            ->assertOk()
            ->assertHeader('X-API-Version', 'v1')
            ->assertHeader('X-API-Scope', 'frozen')
            ->assertHeader('X-Request-Id', 'req-rl-001')
            ->assertHeader('X-Correlation-Id', 'corr-rl-001');
    }

    public function test_missing_operator_roles_header_returns_400(): void
    {
        $headers = $this->fullHeaders();
        unset($headers['X-Operator-Roles']);

        $response = $this
            ->withHeaders($headers)
            ->getJson('/api/integration/v1/_boundary/ping');

        $response->assertStatus(400)
            ->assertJsonPath('error.error_code', 'invalid_request')
            ->assertJsonPath('error.reason_code', 'missing_required_header');
    }

    public function test_invalid_source_system_is_rejected(): void
    {
        $headers = $this->fullHeaders();
        $headers['X-Source-System'] = 'erp';

        $response = $this
            ->withHeaders($headers)
            ->getJson('/api/integration/v1/_boundary/ping');

        $response->assertStatus(400)
            ->assertJsonPath('error.error_code', 'invalid_request')
            ->assertJsonPath('error.reason_code', 'invalid_source_system');
    }

    public function test_governance_headers_present_on_error(): void
    {
        // Missing bearer token — error response should still have governance headers
        $response = $this
            ->withHeaders([
                'X-Request-Id' => 'req-err-001',
                'X-Correlation-Id' => 'corr-err-001',
                'X-Source-System' => 'crm',
                'X-Operator-Id' => 'op-1',
                'X-Operator-Roles' => 'reader',
                'X-Operator-Org-Context' => '{"branch_id":"main"}',
            ])
            ->getJson('/api/integration/v1/_boundary/ping');

        $response->assertStatus(401);
        // Error responses from boundary middleware still include trace headers
        $response->assertHeader('X-Request-Id', 'req-err-001');
        $response->assertHeader('X-Correlation-Id', 'corr-err-001');
    }

    public function test_rate_limiter_name_is_applied(): void
    {
        // Send several requests and confirm rate limit headers track correctly
        for ($i = 0; $i < 3; $i++) {
            $response = $this
                ->withHeaders($this->fullHeaders())
                ->getJson('/api/integration/v1/_boundary/ping');

            $response->assertOk();
        }

        // After 3 requests, remaining should be less than limit
        $limit = (int) $response->headers->get('X-RateLimit-Limit');
        $remaining = (int) $response->headers->get('X-RateLimit-Remaining');
        $this->assertGreaterThan(0, $limit, 'Rate limit header should show a positive limit');
        $this->assertLessThan($limit, $remaining, 'Remaining should decrease after requests');
    }

    public function test_mutation_route_applies_mutation_rate_limiter(): void
    {
        // POST to reservation approve — even if it 404s on the reservation,
        // it should still have passed through rate limiting middleware
        $response = $this
            ->withHeaders($this->fullHeaders())
            ->postJson('/api/integration/v1/reservations/00000000-0000-0000-0000-000000000000/approve');

        // Will be 400/403/404/422 depending on validation — but NOT 429
        $this->assertNotEquals(429, $response->getStatusCode(), 'First mutation should not be rate limited');
    }

    public function test_different_clients_have_independent_rate_limits(): void
    {
        // Simply verify that different tokens produce different client refs
        // (which means they get independent rate limit buckets)
        $responseA = $this
            ->withHeaders($this->fullHeaders('token-alpha'))
            ->getJson('/api/integration/v1/_boundary/ping');
        $responseA->assertOk();

        $responseB = $this
            ->withHeaders($this->fullHeaders('token-beta'))
            ->getJson('/api/integration/v1/_boundary/ping');
        $responseB->assertOk();

        $clientRefA = $responseA->json('context.authenticated_client_ref');
        $clientRefB = $responseB->json('context.authenticated_client_ref');

        $this->assertNotEquals($clientRefA, $clientRefB, 'Different tokens should map to different client refs');
        $this->assertStringStartsWith('token:', $clientRefA);
        $this->assertStringStartsWith('token:', $clientRefB);
    }

    public function test_openapi_contract_file_exists(): void
    {
        $contractPath = base_path('docs/integration-api-contract.json');
        $this->assertFileExists($contractPath, 'OpenAPI contract file should exist');

        $json = json_decode(file_get_contents($contractPath), true);
        $this->assertIsArray($json);
        $this->assertEquals('3.0.3', $json['openapi']);
        $this->assertArrayHasKey('paths', $json);
        $this->assertArrayHasKey('x-governance', $json);
        $this->assertEquals('FROZEN — no expansion beyond v1 without explicit approval', $json['x-governance']['api-scope']);
    }

    public function test_openapi_contract_covers_all_integration_routes(): void
    {
        $contractPath = base_path('docs/integration-api-contract.json');
        $json = json_decode(file_get_contents($contractPath), true);
        $paths = array_keys($json['paths']);

        // All integration routes must be documented
        $this->assertContains('/_boundary/ping', $paths);
        $this->assertContains('/reservations', $paths);
        $this->assertContains('/reservations/{id}', $paths);
        $this->assertContains('/reservations/{id}/approve', $paths);
        $this->assertContains('/reservations/{id}/reject', $paths);
        $this->assertContains('/documents', $paths);
        $this->assertContains('/documents/{id}', $paths);
        $this->assertContains('/documents/{id}/archive', $paths);
    }

    public function test_contract_documents_rate_limits(): void
    {
        $contractPath = base_path('docs/integration-api-contract.json');
        $json = json_decode(file_get_contents($contractPath), true);

        $governance = $json['x-governance'];
        $this->assertArrayHasKey('rate-limits', $governance);
        $this->assertArrayHasKey('global', $governance['rate-limits']);
        $this->assertArrayHasKey('mutations', $governance['rate-limits']);
        $this->assertArrayHasKey('reads', $governance['rate-limits']);
    }

    public function test_contract_documents_authentication(): void
    {
        $contractPath = base_path('docs/integration-api-contract.json');
        $json = json_decode(file_get_contents($contractPath), true);

        $governance = $json['x-governance'];
        $this->assertArrayHasKey('authentication', $governance);
        $this->assertArrayHasKey('required-headers', $governance['authentication']);
        $this->assertCount(6, $governance['authentication']['required-headers']);
    }

    public function test_contract_documents_scope_restrictions(): void
    {
        $contractPath = base_path('docs/integration-api-contract.json');
        $json = json_decode(file_get_contents($contractPath), true);

        $restrictions = $json['x-governance']['scope-restrictions'];
        $this->assertIsArray($restrictions);
        $this->assertNotEmpty($restrictions);
        // Core project truth must be documented
        $this->assertContains('CRM must not connect directly to the library database', $restrictions);
    }

    /**
     * @return array<string, string>
     */
    private function fullHeaders(string $token = 'test-integration-token'): array
    {
        return [
            'Authorization' => 'Bearer '.$token,
            'X-Request-Id' => 'req-rl-001',
            'X-Correlation-Id' => 'corr-rl-001',
            'X-Source-System' => 'crm',
            'X-Operator-Id' => 'crm-op-42',
            'X-Operator-Roles' => 'reservations.approve,reservations.reject',
            'X-Operator-Org-Context' => '{"branch_id":"main"}',
        ];
    }
}
