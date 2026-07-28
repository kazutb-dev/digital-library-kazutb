<?php

namespace Tests\Feature\Api;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InternalBulkReviewTest extends TestCase
{
    private function staffSession(): array
    {
        return [
            'library.user' => [
                'id' => 'aaaaaaaa-bbbb-cccc-dddd-eeeeeeee0001',
                'login' => 'librarian1',
                'role' => 'librarian',
            ],
        ];
    }

    private function canUseLivePgsql(): bool
    {
        try {
            Config::set('database.default', 'pgsql');
            DB::purge('pgsql');
            DB::connection('pgsql')->getPdo();

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    // ── Route existence tests (no DB required) ────────────────────────

    public function test_bulk_resolve_copies_route_requires_staff_session(): void
    {
        $response = $this->postJson('/api/v1/internal/review/copies/bulk-resolve', [
            'ids' => ['00000000-0000-0000-0000-000000000001'],
        ]);

        // Without staff session, middleware rejects (401 or 403)
        $this->assertTrue(in_array($response->status(), [401, 403]),
            'Expected 401/403 without staff session, got ' . $response->status());
    }

    public function test_bulk_resolve_documents_route_requires_staff_session(): void
    {
        $response = $this->postJson('/api/v1/internal/review/documents/bulk-resolve', [
            'ids' => ['00000000-0000-0000-0000-000000000001'],
        ]);

        $this->assertTrue(in_array($response->status(), [401, 403]),
            'Expected 401/403 without staff session, got ' . $response->status());
    }

    public function test_bulk_flag_documents_route_requires_staff_session(): void
    {
        $response = $this->postJson('/api/v1/internal/review/documents/bulk-flag', [
            'ids' => ['00000000-0000-0000-0000-000000000001'],
            'reason_codes' => ['TEST_CODE'],
        ]);

        $this->assertTrue(in_array($response->status(), [401, 403]),
            'Expected 401/403 without staff session, got ' . $response->status());
    }

    public function test_bulk_resolve_readers_route_requires_staff_session(): void
    {
        $response = $this->postJson('/api/v1/internal/review/readers/bulk-resolve', [
            'ids' => ['00000000-0000-0000-0000-000000000001'],
        ]);

        $this->assertTrue(in_array($response->status(), [401, 403]),
            'Expected 401/403 without staff session, got ' . $response->status());
    }

    // ── Validation tests (with staff session) ─────────────────────────

    public function test_bulk_resolve_copies_validates_ids_required(): void
    {
        $response = $this->withSession($this->staffSession())
            ->postJson('/api/v1/internal/review/copies/bulk-resolve', []);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ids']);
    }

    public function test_bulk_resolve_copies_validates_ids_are_uuids(): void
    {
        $response = $this->withSession($this->staffSession())
            ->postJson('/api/v1/internal/review/copies/bulk-resolve', [
                'ids' => ['not-a-uuid', 'also-bad'],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ids.0', 'ids.1']);
    }

    public function test_bulk_resolve_copies_validates_max_200(): void
    {
        $ids = array_map(fn () => fake()->uuid(), range(1, 201));

        $response = $this->withSession($this->staffSession())
            ->postJson('/api/v1/internal/review/copies/bulk-resolve', [
                'ids' => $ids,
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['ids']);
    }

    public function test_bulk_flag_documents_validates_reason_codes_required(): void
    {
        $response = $this->withSession($this->staffSession())
            ->postJson('/api/v1/internal/review/documents/bulk-flag', [
                'ids' => ['00000000-0000-0000-0000-000000000001'],
            ]);

        $response->assertStatus(422);
        $response->assertJsonValidationErrors(['reason_codes']);
    }

    // ── Live DB tests (partial-success response shape) ────────────────

    public function test_bulk_resolve_copies_returns_summary_with_failures_for_nonexistent_ids(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL not available');
        }

        $fakeIds = [
            '10000000-0000-0000-0000-000000000001',
            '10000000-0000-0000-0000-000000000002',
        ];

        $response = $this->withSession($this->staffSession())
            ->postJson('/api/v1/internal/review/copies/bulk-resolve', [
                'ids' => $fakeIds,
                'resolution_note' => 'Bulk test',
            ]);

        $response->assertOk();
        $response->assertJsonStructure([
            'success',
            'summary' => ['total', 'succeeded', 'failed'],
            'results' => [['id', 'success']],
        ]);

        $data = $response->json();
        $this->assertTrue($data['success']);
        $this->assertEquals(2, $data['summary']['total']);
        $this->assertEquals(0, $data['summary']['succeeded']);
        $this->assertEquals(2, $data['summary']['failed']);

        foreach ($data['results'] as $r) {
            $this->assertFalse($r['success']);
            $this->assertArrayHasKey('error_code', $r);
            $this->assertEquals('copy_not_found', $r['error_code']);
        }
    }

    public function test_bulk_resolve_documents_returns_summary_with_failures(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL not available');
        }

        $response = $this->withSession($this->staffSession())
            ->postJson('/api/v1/internal/review/documents/bulk-resolve', [
                'ids' => ['20000000-0000-0000-0000-000000000001'],
            ]);

        $response->assertOk();
        $json = $response->json();
        $this->assertEquals(1, $json['summary']['total']);
        $this->assertEquals(0, $json['summary']['succeeded']);
        $this->assertEquals(1, $json['summary']['failed']);
    }

    public function test_bulk_flag_documents_returns_summary_with_failures(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL not available');
        }

        $response = $this->withSession($this->staffSession())
            ->postJson('/api/v1/internal/review/documents/bulk-flag', [
                'ids' => ['20000000-0000-0000-0000-000000000002'],
                'reason_codes' => ['BULK_TEST'],
            ]);

        $response->assertOk();
        $json = $response->json();
        $this->assertEquals(1, $json['summary']['total']);
        $this->assertEquals(0, $json['summary']['succeeded']);
        $this->assertEquals(1, $json['summary']['failed']);
    }

    public function test_bulk_resolve_readers_returns_summary_with_failures(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL not available');
        }

        $response = $this->withSession($this->staffSession())
            ->postJson('/api/v1/internal/review/readers/bulk-resolve', [
                'ids' => ['30000000-0000-0000-0000-000000000001'],
                'resolution_note' => 'Bulk test readers',
            ]);

        $response->assertOk();
        $json = $response->json();
        $this->assertEquals(1, $json['summary']['total']);
        $this->assertEquals(0, $json['summary']['succeeded']);
        $this->assertEquals(1, $json['summary']['failed']);
    }
}
