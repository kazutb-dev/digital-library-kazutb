<?php

namespace Tests\Feature\Api;

use App\Services\Library\IdentityMatchAudit;
use Tests\TestCase;

class IdentityMatchAuditTest extends TestCase
{
    private IdentityMatchAudit $audit;

    protected function setUp(): void
    {
        parent::setUp();
        $this->audit = resolve(IdentityMatchAudit::class);
    }

    public function test_audit_detects_no_match(): void
    {
        $result = $this->audit->validate(
            [
                'id' => '123',
                'email' => 'nonexistent@example.com',
                'ad_login' => 'nonexistent',
            ],
            null,
            []
        );

        $this->assertEquals('no_match', $result['status']);
        $this->assertEquals('no_match', $result['matched_by']);
        $this->assertNull($result['reader_id']);
    }

    public function test_stale_check_detects_email_change(): void
    {
        $result = $this->audit->checkIfStale('new@example.com', 'old@example.com');

        $this->assertTrue($result['stale']);
        $this->assertStringContainsString('changed', $result['reason']);
    }

    public function test_stale_check_passes_matching_email(): void
    {
        $result = $this->audit->checkIfStale('same@example.com', 'same@example.com');

        $this->assertFalse($result['stale']);
    }

    public function test_audit_validates_with_candidates(): void
    {
        $result = $this->audit->validate(
            [
                'id' => '123',
                'email' => 'test@example.com',
                'ad_login' => 'test_user',
            ],
            null,
            ['test@example.com', 'test_user']
        );

        $this->assertEquals('no_match', $result['status']);
        $this->assertEquals(2, $result['candidate_count']);
    }
}
