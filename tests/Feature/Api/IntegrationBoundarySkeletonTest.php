<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class IntegrationBoundarySkeletonTest extends TestCase
{
    public function test_missing_bearer_token_is_rejected(): void
    {
        $response = $this
            ->withHeaders($this->requiredHeaders())
            ->getJson('/api/integration/v1/_boundary/ping');

        $response
            ->assertStatus(401)
            ->assertJsonPath('error.error_code', 'auth_failed')
            ->assertJsonPath('error.reason_code', 'missing_bearer_token')
            ->assertJsonStructure([
                'error' => ['error_code', 'reason_code', 'message'],
                'request_id',
                'correlation_id',
                'timestamp',
            ]);
    }

    public function test_missing_required_header_is_rejected(): void
    {
        $headers = $this->requiredHeaders();
        unset($headers['X-Operator-Org-Context']);

        $response = $this
            ->withHeaders($headers + ['Authorization' => 'Bearer integration-token'])
            ->getJson('/api/integration/v1/_boundary/ping');

        $response
            ->assertStatus(400)
            ->assertJsonPath('error.error_code', 'invalid_request')
            ->assertJsonPath('error.reason_code', 'missing_required_header');
    }

    public function test_invalid_source_system_is_rejected(): void
    {
        $headers = $this->requiredHeaders();
        $headers['X-Source-System'] = 'library';

        $response = $this
            ->withHeaders($headers + ['Authorization' => 'Bearer integration-token'])
            ->getJson('/api/integration/v1/_boundary/ping');

        $response
            ->assertStatus(400)
            ->assertJsonPath('error.error_code', 'invalid_request')
            ->assertJsonPath('error.reason_code', 'invalid_source_system');
    }

    public function test_semantic_empty_operator_roles_is_rejected(): void
    {
        $headers = $this->requiredHeaders();
        $headers['X-Operator-Roles'] = ' ,  , ';

        $response = $this
            ->withHeaders($headers + ['Authorization' => 'Bearer integration-token'])
            ->getJson('/api/integration/v1/_boundary/ping');

        $response
            ->assertStatus(400)
            ->assertJsonPath('error.error_code', 'invalid_request')
            ->assertJsonPath('error.reason_code', 'missing_operator_roles');
    }

    public function test_successful_boundary_check_propagates_context_and_ids(): void
    {
        $response = $this
            ->withHeaders($this->requiredHeaders() + ['Authorization' => 'Bearer integration-token'])
            ->getJson('/api/integration/v1/_boundary/ping');

        $response
            ->assertOk()
            ->assertJsonPath('ok', true)
            ->assertJsonPath('context.source_system', 'crm')
            ->assertJsonPath('context.request_id', 'req-001')
            ->assertJsonPath('context.correlation_id', 'corr-001')
            ->assertHeader('X-Request-Id', 'req-001')
            ->assertHeader('X-Correlation-Id', 'corr-001');

        $clientRef = (string) $response->json('context.authenticated_client_ref');
        $this->assertStringStartsWith('token:', $clientRef);
    }

    /**
     * @return array<string, string>
     */
    private function requiredHeaders(): array
    {
        return [
            'X-Request-Id' => 'req-001',
            'X-Correlation-Id' => 'corr-001',
            'X-Source-System' => 'crm',
            'X-Operator-Id' => 'crm-op-42',
            'X-Operator-Roles' => 'reservations.approve,reservations.reject',
            'X-Operator-Org-Context' => '{"branch_id":"main"}',
        ];
    }
}
