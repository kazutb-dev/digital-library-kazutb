<?php

namespace Tests\Feature\Api;

use App\Models\Library\CirculationAuditEvent;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InternalCopyRetirementTest extends TestCase
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

    // ─────────────────────────────────────────────────────────────
    // Success path
    // ─────────────────────────────────────────────────────────────

    public function test_retire_copy_succeeds_and_writes_audit_event(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for retire success test.');

        $copyId = $this->pickNonRetiredCopyId();

        $response = $this->withSession($this->staffSession())->postJson(
            '/api/v1/internal/copies/' . $copyId . '/retire',
            [
                'reason_code' => 'LOST',
                'request_id' => 'retire-req-001',
                'correlation_id' => 'retire-corr-001',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.copyIdentity.id', $copyId)
            ->assertJsonPath('data.lifecycle.retirementReasonCode', 'LOST')
            ->assertJsonPath('data.circulation.availabilityIndicator', 'RETIRED')
            ->assertJsonPath('source', 'app.book_copies');

        $this->assertNotNull($response->json('data.lifecycle.retiredAt'));
        $this->assertNull($response->json('data.lifecycle.retirementNote'));

        $row = DB::connection('pgsql')
            ->table('app.book_copies')
            ->select(['retired_at', 'retirement_reason_code', 'retirement_note'])
            ->where('id', $copyId)
            ->first();

        $this->assertNotNull($row);
        $this->assertNotNull($row->retired_at);
        $this->assertSame('LOST', $row->retirement_reason_code);
        $this->assertNull($row->retirement_note);

        $audit = CirculationAuditEvent::query()
            ->where('action', 'internal_copy_retired')
            ->where('entity_type', 'copy')
            ->where('entity_id', $copyId)
            ->first();

        $this->assertNotNull($audit, 'Audit event must be written for successful retirement.');
        $this->assertSame('LOST', $audit->new_state['lifecycle']['retirementReasonCode'] ?? null);
        $this->assertSame('RETIRED', $audit->new_state['circulation']['availabilityIndicator'] ?? null);
        // previous_state must have retiredAt key with null value (copy was not retired before).
        $prevLifecycle = $audit->previous_state['lifecycle'] ?? [];
        $this->assertArrayHasKey('retiredAt', $prevLifecycle, 'previous_state.lifecycle must contain retiredAt key.');
        $this->assertNull($prevLifecycle['retiredAt'], 'previous_state.lifecycle.retiredAt must be null before retirement.');
    }

    public function test_retire_copy_with_note_persists_note(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for retire-with-note test.');

        $copyId = $this->pickNonRetiredCopyId();

        $response = $this->withSession($this->staffSession())->postJson(
            '/api/v1/internal/copies/' . $copyId . '/retire',
            [
                'reason_code' => 'DAMAGED_BEYOND_REPAIR',
                'note' => 'Water damage during flood.',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.lifecycle.retirementReasonCode', 'DAMAGED_BEYOND_REPAIR')
            ->assertJsonPath('data.lifecycle.retirementNote', 'Water damage during flood.');
    }

    public function test_retire_copy_with_reason_other_and_note_succeeds(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for retire-OTHER-with-note test.');

        $copyId = $this->pickNonRetiredCopyId();

        $response = $this->withSession($this->staffSession())->postJson(
            '/api/v1/internal/copies/' . $copyId . '/retire',
            [
                'reason_code' => 'OTHER',
                'note' => 'Decommissioned as part of collection rationalization.',
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.lifecycle.retirementReasonCode', 'OTHER')
            ->assertJsonPath('data.lifecycle.retirementNote', 'Decommissioned as part of collection rationalization.');
    }

    // ─────────────────────────────────────────────────────────────
    // Read-model: retirement fields visible post-retirement
    // ─────────────────────────────────────────────────────────────

    public function test_read_model_exposes_retirement_fields_after_retirement(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for read-model retirement fields test.');

        $copyId = $this->pickNonRetiredCopyId();

        // Retire the copy.
        $this->withSession($this->staffSession())->postJson(
            '/api/v1/internal/copies/' . $copyId . '/retire',
            ['reason_code' => 'WRITTEN_OFF']
        )->assertOk();

        // Then read it back.
        $response = $this->withSession($this->staffSession())->getJson(
            '/api/v1/internal/copies/' . $copyId
        );

        $response
            ->assertOk()
            ->assertJsonPath('data.lifecycle.retirementReasonCode', 'WRITTEN_OFF')
            ->assertJsonPath('data.circulation.availabilityIndicator', 'RETIRED');

        $this->assertNotNull($response->json('data.lifecycle.retiredAt'));
    }

    // ─────────────────────────────────────────────────────────────
    // UUID validation
    // ─────────────────────────────────────────────────────────────

    public function test_retire_returns_400_for_invalid_copy_id(): void
    {
        $response = $this->withSession($this->staffSession())->postJson(
            '/api/v1/internal/copies/not-a-valid-uuid/retire',
            ['reason_code' => 'LOST']
        );

        $response
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'invalid_copy_id');
    }

    // ─────────────────────────────────────────────────────────────
    // Not found
    // ─────────────────────────────────────────────────────────────

    public function test_retire_returns_404_for_missing_copy(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for retire not-found test.');

        $response = $this->withSession($this->staffSession())->postJson(
            '/api/v1/internal/copies/' . Str::uuid() . '/retire',
            ['reason_code' => 'LOST']
        );

        $response
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'copy_not_found');
    }

    // ─────────────────────────────────────────────────────────────
    // Already retired
    // ─────────────────────────────────────────────────────────────

    public function test_retire_returns_409_for_already_retired_copy(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for already-retired test.');

        $copyId = $this->pickNonRetiredCopyId();

        // Retire once.
        $this->withSession($this->staffSession())->postJson(
            '/api/v1/internal/copies/' . $copyId . '/retire',
            ['reason_code' => 'LOST']
        )->assertOk();

        // Attempt to retire again in the same transaction context.
        $response = $this->withSession($this->staffSession())->postJson(
            '/api/v1/internal/copies/' . $copyId . '/retire',
            ['reason_code' => 'MISSING_AFTER_AUDIT']
        );

        $response
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'already_retired');
    }

    // ─────────────────────────────────────────────────────────────
    // Active loan conflict
    // ─────────────────────────────────────────────────────────────

    public function test_retire_returns_409_when_copy_has_active_loan(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for active-loan conflict test.');

        $copyId = $this->pickNonRetiredCopyId();

        // Insert a synthetic active loan inside the transaction (will be rolled back).
        DB::connection('pgsql')->table('app.circulation_loans')->insert([
            'id' => (string) Str::uuid(),
            'reader_id' => $this->pickReaderId(),
            'copy_id' => $copyId,
            'status' => 'active',
            'issued_at' => now('UTC')->toDateTimeString(),
            'due_at' => now('UTC')->addDays(14)->toDateTimeString(),
            'returned_at' => null,
            'renew_count' => 0,
            'created_at' => now('UTC')->toDateTimeString(),
            'updated_at' => now('UTC')->toDateTimeString(),
        ]);

        $response = $this->withSession($this->staffSession())->postJson(
            '/api/v1/internal/copies/' . $copyId . '/retire',
            ['reason_code' => 'DAMAGED_BEYOND_REPAIR']
        );

        $response
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'copy_on_loan');
    }

    // ─────────────────────────────────────────────────────────────
    // Validation rules
    // ─────────────────────────────────────────────────────────────

    public function test_retire_returns_400_for_missing_reason_code(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for missing reason_code test.');

        $response = $this->withSession($this->staffSession())->postJson(
            '/api/v1/internal/copies/' . Str::uuid() . '/retire',
            []
        );

        $response
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'invalid_request_body');
    }

    public function test_retire_returns_400_for_invalid_reason_code(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for invalid reason_code test.');

        $response = $this->withSession($this->staffSession())->postJson(
            '/api/v1/internal/copies/' . Str::uuid() . '/retire',
            ['reason_code' => 'DESTROYED_BY_FIRE']
        );

        $response
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'invalid_request_body');
    }

    public function test_retire_returns_400_for_reason_other_without_note(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for OTHER-without-note test.');

        $response = $this->withSession($this->staffSession())->postJson(
            '/api/v1/internal/copies/' . Str::uuid() . '/retire',
            ['reason_code' => 'OTHER']
        );

        $response
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'note_required_for_other');
    }

    public function test_retire_returns_400_for_reason_other_with_blank_note(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for OTHER-blank-note test.');

        $response = $this->withSession($this->staffSession())->postJson(
            '/api/v1/internal/copies/' . Str::uuid() . '/retire',
            ['reason_code' => 'OTHER', 'note' => '   ']
        );

        $response
            ->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'note_required_for_other');
    }

    // ─────────────────────────────────────────────────────────────
    // Staff middleware
    // ─────────────────────────────────────────────────────────────

    public function test_retire_endpoint_requires_staff_session(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for middleware test.');

        $response = $this->postJson(
            '/api/v1/internal/copies/' . Str::uuid() . '/retire',
            ['reason_code' => 'LOST']
        );

        $response
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'staff_authorization_required');
    }

    public function test_retire_rejects_non_admin_actor_override(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for retire actor override authorization test.');

        $copyId = $this->pickNonRetiredCopyId();

        $response = $this->withSession($this->staffSession('librarian'))->postJson(
            '/api/v1/internal/copies/' . $copyId . '/retire',
            [
                'reason_code' => 'WRITTEN_OFF',
                'actor_user_id' => (string) Str::uuid(),
            ]
        );

        $response
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'insufficient_staff_role');
    }

    public function test_retire_allows_admin_actor_override(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for retire admin actor override authorization test.');

        $copyId = $this->pickNonRetiredCopyId();

        $response = $this->withSession($this->staffSession('admin'))->postJson(
            '/api/v1/internal/copies/' . $copyId . '/retire',
            [
                'reason_code' => 'WRITTEN_OFF',
                'actor_user_id' => (string) Str::uuid(),
            ]
        );

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.lifecycle.retirementReasonCode', 'WRITTEN_OFF');
    }

    // ─────────────────────────────────────────────────────────────
    // Copy-bound reservation conflict
    // ─────────────────────────────────────────────────────────────

    public function test_retire_returns_409_when_copy_has_blocking_reservation(): void
    {
        $this->requireLivePgsql('Live PostgreSQL is required for reservation conflict test.');

        // Check if a real copy-bound reservation exists in the DB.
        $reservationRow = DB::connection('pgsql')->selectOne(
            'SELECT "copyId"::text AS copy_id, status::text AS status FROM public."Reservation" WHERE "copyId" IS NOT NULL AND status::text = ANY(\'{PENDING,READY}\'::text[]) LIMIT 1'
        );

        if ($reservationRow === null) {
            $this->markTestSkipped('No copy-bound reservation in blocking status is available in current DB snapshot.');
        }

        $copyId = (string) ($reservationRow->copy_id ?? '');

        // Verify the copy referenced by the reservation actually exists in app.book_copies.
        $copy = DB::connection('pgsql')->selectOne(
            'SELECT id FROM app.book_copies WHERE id::text = ? AND retired_at IS NULL LIMIT 1',
            [$copyId]
        );
        if ($copy === null) {
            $this->markTestSkipped('Copy referenced by blocking reservation does not exist in app.book_copies or is already retired.');
        }

        $response = $this->withSession($this->staffSession())->postJson(
            '/api/v1/internal/copies/' . $copyId . '/retire',
            ['reason_code' => 'WRITTEN_OFF']
        );

        $response
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'active_reservation_conflict');
    }

    // ─────────────────────────────────────────────────────────────
    // Helpers
    // ─────────────────────────────────────────────────────────────

    private function pickNonRetiredCopyId(): string
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL is not available.');
        }

        $copyId = DB::connection('pgsql')
            ->table('app.book_copies')
            ->whereNull('retired_at')
            ->value('id');

        if (! is_string($copyId) || $copyId === '') {
            $this->markTestSkipped('No non-retired copy is available in current database snapshot.');
        }

        return $copyId;
    }

    private function pickReaderId(): string
    {
        // Find any reader, or fall back to a synthetic UUID.
        $readerId = DB::connection('pgsql')
            ->table('app.readers')
            ->value('id');

        if (! is_string($readerId) || $readerId === '') {
            return (string) Str::uuid();
        }

        return $readerId;
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
