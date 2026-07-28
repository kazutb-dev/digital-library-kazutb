<?php

namespace Tests\Feature\Api;

use App\Models\Library\CirculationAuditEvent;
use App\Models\Library\CirculationLoan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InternalCirculationCheckoutReturnTest extends TestCase
{
    private bool $transactionStarted = false;

    /**
     * @return array{library.user: array<string, string>}
     */
    private function staffSession(string $role = 'librarian'): array
    {
        return [
            'library.user' => [
                'id' => (string) DB::scalar('select gen_random_uuid()::text'),
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

    public function test_internal_checkout_creates_active_loan_and_audit_event(): void
    {
        [$readerId, $copyId] = $this->pickReaderAndCopy();

        $response = $this->withSession($this->staffSession())->postJson('/api/v1/internal/circulation/checkouts', [
            'reader_id' => $readerId,
            'copy_id' => $copyId,
            'request_id' => 'test-checkout-request',
            'correlation_id' => 'test-checkout-correlation',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.readerId', $readerId)
            ->assertJsonPath('data.copyId', $copyId)
            ->assertJsonPath('data.status', 'active');

        $loanId = (string) $response->json('data.id');

        $this->assertDatabaseHas('app.circulation_loans', [
            'id' => $loanId,
            'reader_id' => $readerId,
            'copy_id' => $copyId,
            'status' => 'active',
        ], 'pgsql');

        $this->assertDatabaseHas('app.circulation_audit_events', [
            'action' => 'checkout_created',
            'entity_type' => 'loan',
            'entity_id' => $loanId,
            'reader_id' => $readerId,
        ], 'pgsql');
    }

    public function test_second_checkout_for_same_copy_returns_conflict(): void
    {
        [$readerId, $copyId] = $this->pickReaderAndCopy();

        CirculationLoan::query()->create([
            'id' => (string) DB::scalar('select gen_random_uuid()::text'),
            'reader_id' => $readerId,
            'copy_id' => $copyId,
            'status' => 'active',
            'issued_at' => now('UTC'),
            'due_at' => now('UTC')->addDays(7),
            'returned_at' => null,
            'renew_count' => 0,
        ]);

        $response = $this->withSession($this->staffSession())->postJson('/api/v1/internal/circulation/checkouts', [
            'reader_id' => $readerId,
            'copy_id' => $copyId,
        ]);

        $response
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'copy_already_on_loan');
    }

    public function test_checkout_for_retired_copy_returns_conflict(): void
    {
        [$readerId, $copyId] = $this->pickReaderAndCopy();

        DB::connection('pgsql')->table('app.book_copies')
            ->where('id', $copyId)
            ->update([
                'retired_at' => Carbon::now('UTC'),
                'retirement_reason_code' => 'WRITTEN_OFF',
                'retirement_note' => 'retired for circulation consistency test',
            ]);

        $response = $this->withSession($this->staffSession())->postJson('/api/v1/internal/circulation/checkouts', [
            'reader_id' => $readerId,
            'copy_id' => $copyId,
        ]);

        $response
            ->assertStatus(409)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'copy_retired');

        $this->assertDatabaseMissing('app.circulation_loans', [
            'reader_id' => $readerId,
            'copy_id' => $copyId,
            'status' => 'active',
        ], 'pgsql');
    }

    public function test_internal_return_closes_active_loan_and_writes_audit(): void
    {
        [$readerId, $copyId] = $this->pickReaderAndCopy();

        $loan = CirculationLoan::query()->create([
            'id' => (string) DB::scalar('select gen_random_uuid()::text'),
            'reader_id' => $readerId,
            'copy_id' => $copyId,
            'status' => 'active',
            'issued_at' => now('UTC')->subDays(2),
            'due_at' => now('UTC')->addDays(12),
            'returned_at' => null,
            'renew_count' => 0,
        ]);

        $response = $this->withSession($this->staffSession())->postJson('/api/v1/internal/circulation/returns', [
            'copy_id' => $copyId,
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', (string) $loan->id)
            ->assertJsonPath('data.status', 'returned');

        $loan->refresh();

        $this->assertSame('returned', $loan->status);
        $this->assertNotNull($loan->returned_at);

        $audit = CirculationAuditEvent::query()
            ->where('action', 'return_completed')
            ->where('entity_id', $loan->id)
            ->first();

        $this->assertNotNull($audit);
        $this->assertSame('active', $audit->previous_state['status'] ?? null);
        $this->assertSame('returned', $audit->new_state['status'] ?? null);
    }

    public function test_return_for_copy_without_active_loan_returns_not_found(): void
    {
        [, $copyId] = $this->pickReaderAndCopy();

        $response = $this->withSession($this->staffSession())->postJson('/api/v1/internal/circulation/returns', [
            'copy_id' => $copyId,
        ]);

        $response
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'active_loan_not_found');
    }

    public function test_checkout_requires_staff_session(): void
    {
        [$readerId, $copyId] = $this->pickReaderAndCopy();

        $response = $this->postJson('/api/v1/internal/circulation/checkouts', [
            'reader_id' => $readerId,
            'copy_id' => $copyId,
        ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'staff_authorization_required');
    }

    public function test_checkout_rejects_reader_role_session(): void
    {
        [$readerId, $copyId] = $this->pickReaderAndCopy();

        $response = $this->withSession($this->staffSession('reader'))->postJson('/api/v1/internal/circulation/checkouts', [
            'reader_id' => $readerId,
            'copy_id' => $copyId,
        ]);

        $response
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'staff_authorization_required');
    }

    public function test_checkout_rejects_non_admin_actor_override(): void
    {
        [$readerId, $copyId] = $this->pickReaderAndCopy();

        $response = $this->withSession($this->staffSession('librarian'))->postJson('/api/v1/internal/circulation/checkouts', [
            'reader_id' => $readerId,
            'copy_id' => $copyId,
            'actor_user_id' => (string) DB::scalar('select gen_random_uuid()::text'),
        ]);

        $response
            ->assertStatus(403)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'insufficient_staff_role');
    }

    public function test_checkout_allows_admin_actor_override(): void
    {
        [$readerId, $copyId] = $this->pickReaderAndCopy();

        $response = $this->withSession($this->staffSession('admin'))->postJson('/api/v1/internal/circulation/checkouts', [
            'reader_id' => $readerId,
            'copy_id' => $copyId,
            'actor_user_id' => (string) DB::scalar('select gen_random_uuid()::text'),
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.status', 'active');
    }

    /**
     * @return array{string, string}
     */
    private function pickReaderAndCopy(): array
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL is not available for internal circulation write tests.');
        }

        $readerId = DB::connection('pgsql')->table('app.readers')->value('id');
        $copyId = DB::connection('pgsql')->table('app.book_copies as bc')
            ->leftJoin('app.circulation_loans as cl', function ($join): void {
                $join->on('cl.copy_id', '=', 'bc.id')
                    ->where('cl.status', '=', 'active')
                    ->whereNull('cl.returned_at');
            })
            ->whereNull('bc.retired_at')
            ->whereNull('cl.id')
            ->value('bc.id');

        if (! is_string($readerId) || $readerId === '' || ! is_string($copyId) || $copyId === '') {
            $this->markTestSkipped('Required readers or loan-free copies are not available for write tests.');
        }

        return [$readerId, $copyId];
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
}
