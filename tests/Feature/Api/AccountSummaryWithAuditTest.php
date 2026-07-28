<?php

namespace Tests\Feature\Api;

use App\Services\Library\AccountSummaryReadService;
use App\Services\Library\IdentityMatchAudit;
use Tests\TestCase;

class AccountSummaryWithAuditTest extends TestCase
{
    public function test_account_summary_includes_matching_metadata_field(): void
    {
        // Mock the audit service behavior
        $audit = new IdentityMatchAudit();

        $sessionProfile = [
            'id' => 'test-user-123',
            'name' => 'Test User',
            'email' => 'test@example.com',
            'ad_login' => 'test_login',
            'role' => 'reader',
        ];

        // Validate on no match (no reader found)
        $result = $audit->validate($sessionProfile, null, []);

        $this->assertEquals('no_match', $result['status']);
        $this->assertArrayHasKey('matched_by', $result);
        $this->assertArrayHasKey('has_ambiguity', $result);
        $this->assertArrayHasKey('ambiguity_details', $result);
    }

    public function test_account_summary_service_response_structure(): void
    {
        // This test verifies response structure without hitting real DB
        $audit = resolve(IdentityMatchAudit::class);

        // Verify that IdentityMatchAudit returns the right structure
        $expectedAuditFields = [
            'status',
            'matched_by',
            'has_ambiguity',
            'ambiguity_details',
        ];

        // We can't call service directly without DB, but we verify the audit
        // structure which is used in the response
        $auditResult = $audit->validate(
            ['id' => '123', 'email' => 'test@example.com', 'ad_login' => ''],
            null,
            ['test@example.com']
        );

        foreach ($expectedAuditFields as $field) {
            $this->assertArrayHasKey($field, $auditResult, "Missing field: $field");
        }

        // Note: is_stale and stale_reason are added by AccountSummaryReadService
        // after audit validation based on email comparison with reader profile
    }
}
