<?php

namespace Tests\Feature\Api;

use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MidtermIntegrationRiskExpansionTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('services.integration.allowed_tokens', 'integration-token');
    }

    public function test_invalid_bearer_token_is_rejected_for_reservations_read_endpoint(): void
    {
        $response = $this
            ->withHeaders($this->requiredHeaders())
            ->withToken('invalid-token')
            ->getJson('/api/integration/v1/reservations');

        $response
            ->assertStatus(401)
            ->assertJsonPath('error.error_code', 'auth_failed')
            ->assertJsonPath('error.reason_code', 'invalid_bearer_token');
    }

    public function test_missing_org_context_header_returns_400_for_reservations_read_endpoint(): void
    {
        $headers = $this->requiredHeaders();
        unset($headers['X-Operator-Org-Context']);

        $response = $this
            ->withHeaders($headers)
            ->withToken('integration-token')
            ->getJson('/api/integration/v1/reservations');

        $response
            ->assertStatus(400)
            ->assertJsonPath('error.error_code', 'invalid_request')
            ->assertJsonPath('error.reason_code', 'missing_required_header');
    }

    public function test_semantic_empty_operator_roles_is_rejected_for_documents_endpoint(): void
    {
        $headers = $this->requiredHeaders();
        $headers['X-Operator-Roles'] = ' , , ';

        $response = $this
            ->withHeaders($headers)
            ->withToken('integration-token')
            ->getJson('/api/integration/v1/documents');

        $response
            ->assertStatus(400)
            ->assertJsonPath('error.error_code', 'invalid_request')
            ->assertJsonPath('error.reason_code', 'missing_operator_roles');
    }

    public function test_rapid_repeated_boundary_requests_keep_governance_headers_and_avoid_5xx(): void
    {
        $responses = [];
        for ($i = 0; $i < 5; $i++) {
            $responses[] = $this
                ->withHeaders($this->requiredHeaders("req-midterm-$i", "corr-midterm-$i"))
                ->withToken('integration-token')
                ->getJson('/api/integration/v1/_boundary/ping');
        }

        foreach ($responses as $idx => $response) {
            $this->assertNotNull($response, "Response {$idx} should exist");
            $response->assertStatus(200);
            $response->assertHeader('X-API-Version', 'v1');
            $response->assertHeader('X-API-Scope', 'frozen');
            $this->assertLessThan(500, $response->getStatusCode());
        }
    }

    /**
     * @return array<string, string>
     */
    private function requiredHeaders(string $requestId = 'req-midterm-001', string $correlationId = 'corr-midterm-001'): array
    {
        return [
            'X-Request-Id' => $requestId,
            'X-Correlation-Id' => $correlationId,
            'X-Source-System' => 'crm',
            'X-Operator-Id' => 'crm-op-midterm',
            'X-Operator-Roles' => 'reservations.read,documents.read',
            'X-Operator-Org-Context' => '{"branch_id":"main"}',
        ];
    }
}
