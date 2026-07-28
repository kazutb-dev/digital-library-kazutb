<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class IdentityMappingE2ETest extends TestCase
{
    /**
     * End-to-end test: Simulate full account summary request with audit
     */
    public function test_account_summary_endpoint_includes_identity_audit_metadata(): void
    {
        // This test verifies the complete flow from controller through services

        // 1. Create mock session data (simulating CRM login)
        $sessionData = [
            'id' => 'crm-user-12345',
            'name' => 'John Doe',
            'email' => 'john@example.com',
            'ad_login' => 'johndoe',
            'role' => 'reader',
        ];

        // 2. Set session data (simulating authenticated request)
        $this->withSession(['library.user' => $sessionData]);

        // 3. Make request to account summary endpoint
        $response = $this->getJson('/api/v1/account/summary');

        // 4. Verify response structure includes identity audit metadata
        $response->assertStatus(200)
            ->assertJsonStructure([
                'authenticated',
                'data' => [
                    'user',
                    'reader',
                    'stats',
                ],
                'matching' => [
                    'status',
                    'matched_by',
                    'has_ambiguity',
                    'ambiguity_details',
                    'is_stale',
                    'stale_reason',
                ],
                'source',
            ]);

        // 5. Verify matching field is present and has expected values
        $response->assertJson([
            'authenticated' => true,
            'matching' => [
                'status' => 'no_match', // Expected for non-existent email in test
                'has_ambiguity' => false,
            ],
        ]);

        // 6. Confirm audit data is accessible
        $json = $response->json();
        $this->assertArrayHasKey('matching', $json);
        $this->assertIsString($json['matching']['matched_by']);
        $this->assertIsBool($json['matching']['has_ambiguity']);
        $this->assertIsString($json['matching']['ambiguity_details']);
    }

    /**
     * Test that Phase 1 works without breaking existing account page
     */
    public function test_account_page_loads_with_new_matching_field(): void
    {
        $response = $this->withSession([
            'library.user' => [
                'id' => 'u-test-1', 'name' => 'Test', 'email' => 'test@example.com',
                'login' => 'test01', 'ad_login' => 'test01', 'role' => 'reader',
            ],
            'library.crm_token' => 'test-token',
            'library.authenticated_at' => now()->toIso8601String(),
        ])->get('/account');

        $response->assertStatus(200);
        $response->assertSee('/api/v1/account/summary'); // Page should call the endpoint
    }
}
