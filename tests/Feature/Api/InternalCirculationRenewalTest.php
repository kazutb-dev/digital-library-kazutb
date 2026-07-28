<?php

namespace Tests\Feature\Api;

use App\Models\Library\CirculationLoan;
use App\Services\Library\CirculationLoanWriteService;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

class InternalCirculationRenewalTest extends TestCase
{
    private bool $useLivePgsql = false;

    protected function setUp(): void
    {
        parent::setUp();

        if ($this->canUseLivePgsql()) {
            $this->useLivePgsql = true;
            Config::set('database.default', 'pgsql');
            DB::purge('pgsql');
            DB::connection('pgsql')->beginTransaction();
        }
    }

    protected function tearDown(): void
    {
        if ($this->useLivePgsql) {
            DB::connection('pgsql')->rollBack();
        }

        parent::tearDown();
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

    private function requireLivePgsql(): void
    {
        if (! $this->useLivePgsql) {
            $this->markTestSkipped('Live PostgreSQL not available.');
        }
    }

    private function staffSession(string $role = 'librarian'): array
    {
        return [
            'library.user' => [
                'id' => 'aaaaaaaa-0000-0000-0000-111111111111',
                'role' => $role,
                'email' => 'staff@digital-library.test',
                'name' => 'Test Staff',
            ],
        ];
    }

    public function test_renew_route_exists(): void
    {
        $fakeUuid = '00000000-0000-0000-0000-000000000000';

        $response = $this->withSession($this->staffSession())
            ->postJson("/api/v1/internal/circulation/loans/{$fakeUuid}/renew");

        $this->assertNotEquals(405, $response->status(), 'Route should exist (not 405 Method Not Allowed)');
    }

    public function test_renew_returns_404_for_nonexistent_loan(): void
    {
        $this->requireLivePgsql();

        $fakeUuid = '00000000-0000-0000-0000-ffffffffffff';

        $response = $this->withSession($this->staffSession())
            ->postJson("/api/v1/internal/circulation/loans/{$fakeUuid}/renew");

        $response->assertStatus(404);
        $response->assertJson(['success' => false, 'error' => 'loan_not_found']);
    }

    public function test_renew_validates_uuid(): void
    {
        $response = $this->withSession($this->staffSession())
            ->postJson('/api/v1/internal/circulation/loans/not-a-uuid/renew');

        $response->assertStatus(400);
        $response->assertJson(['success' => false, 'error' => 'invalid_loan_id']);
    }

    public function test_renew_requires_staff_session(): void
    {
        $response = $this->postJson('/api/v1/internal/circulation/loans/00000000-0000-0000-0000-000000000000/renew');

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'error' => 'staff_authorization_required',
        ]);
    }

    public function test_renew_rejects_reader_role_session(): void
    {
        $response = $this->withSession($this->staffSession('reader'))
            ->postJson('/api/v1/internal/circulation/loans/00000000-0000-0000-0000-000000000000/renew');

        $response->assertStatus(403);
        $response->assertJson([
            'success' => false,
            'error' => 'staff_authorization_required',
        ]);
    }

    public function test_renew_succeeds_for_active_loan(): void
    {
        $this->requireLivePgsql();

        $loan = $this->createTestLoan([
            'status' => 'active',
            'renew_count' => 0,
            'due_at' => Carbon::now('UTC')->addDays(7),
        ]);

        $originalDueAt = Carbon::parse($loan->due_at);

        $response = $this->withSession($this->staffSession())
            ->postJson("/api/v1/internal/circulation/loans/{$loan->id}/renew");

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $data = $response->json('data');
        $this->assertEquals(1, $data['renewCount']);

        $newDueAt = Carbon::parse($data['dueAt']);
        $diffDays = abs($newDueAt->diffInDays($originalDueAt));
        $this->assertGreaterThanOrEqual(13, $diffDays, "Due date should be extended by ~14 days (got {$diffDays})");
        $this->assertLessThanOrEqual(15, $diffDays, "Due date should be extended by ~14 days (got {$diffDays})");
    }

    public function test_renew_fails_when_max_renewals_reached(): void
    {
        $this->requireLivePgsql();

        $loan = $this->createTestLoan([
            'status' => 'active',
            'renew_count' => 3,
            'due_at' => Carbon::now('UTC')->addDays(7),
        ]);

        $response = $this->withSession($this->staffSession())
            ->postJson("/api/v1/internal/circulation/loans/{$loan->id}/renew");

        $response->assertStatus(409);
        $response->assertJson(['success' => false, 'error' => 'max_renewals_reached']);
    }

    public function test_staff_can_renew_overdue_loan(): void
    {
        $this->requireLivePgsql();

        $loan = $this->createTestLoan([
            'status' => 'active',
            'renew_count' => 0,
            'due_at' => Carbon::now('UTC')->subDays(3),
        ]);

        $response = $this->withSession($this->staffSession())
            ->postJson("/api/v1/internal/circulation/loans/{$loan->id}/renew");

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertEquals(1, $response->json('data.renewCount'));
    }

    public function test_renew_fails_for_returned_loan(): void
    {
        $this->requireLivePgsql();

        $loan = $this->createTestLoan([
            'status' => 'returned',
            'renew_count' => 0,
            'due_at' => Carbon::now('UTC')->addDays(7),
            'returned_at' => Carbon::now('UTC'),
        ]);

        $response = $this->withSession($this->staffSession())
            ->postJson("/api/v1/internal/circulation/loans/{$loan->id}/renew");

        $response->assertStatus(409);
        $response->assertJson(['success' => false, 'error' => 'loan_not_active']);
    }

    public function test_renew_increments_renew_count_correctly(): void
    {
        $this->requireLivePgsql();

        $loan = $this->createTestLoan([
            'status' => 'active',
            'renew_count' => 1,
            'due_at' => Carbon::now('UTC')->addDays(7),
        ]);

        $response = $this->withSession($this->staffSession())
            ->postJson("/api/v1/internal/circulation/loans/{$loan->id}/renew");

        $response->assertOk();
        $this->assertEquals(2, $response->json('data.renewCount'));
    }

    public function test_renew_creates_audit_event(): void
    {
        $this->requireLivePgsql();

        $loan = $this->createTestLoan([
            'status' => 'active',
            'renew_count' => 0,
            'due_at' => Carbon::now('UTC')->addDays(7),
        ]);

        $this->withSession($this->staffSession())
            ->postJson("/api/v1/internal/circulation/loans/{$loan->id}/renew");

        $auditEvent = DB::connection('pgsql')
            ->table('app.circulation_audit_events')
            ->where('entity_type', 'loan')
            ->where('entity_id', $loan->id)
            ->where('action', 'renewal_completed')
            ->first();

        $this->assertNotNull($auditEvent, 'Audit event for renewal should be created');
    }

    public function test_service_max_renewals_constant(): void
    {
        $this->assertEquals(3, CirculationLoanWriteService::MAX_RENEWALS);
    }

    public function test_service_renewal_days_constant(): void
    {
        $this->assertEquals(14, CirculationLoanWriteService::RENEWAL_DAYS);
    }

    private function createTestLoan(array $overrides = []): CirculationLoan
    {
        $readerId = $this->findOrFailTestReader();
        $copyId = $this->findOrFailTestCopy();

        $id = (string) Str::uuid();
        $now = Carbon::now('UTC');

        DB::connection('pgsql')->table('app.circulation_loans')->insert(array_merge([
            'id' => $id,
            'reader_id' => $readerId,
            'copy_id' => $copyId,
            'status' => 'active',
            'issued_at' => $now->copy()->subDays(14),
            'due_at' => $now->copy()->addDays(7),
            'returned_at' => null,
            'renew_count' => 0,
            'created_at' => $now,
            'updated_at' => $now,
        ], $overrides));

        return CirculationLoan::query()->findOrFail($id);
    }

    private function findOrFailTestReader(): string
    {
        $readerId = DB::connection('pgsql')
            ->table('app.readers')
            ->limit(1)
            ->value('id');

        if ($readerId === null) {
            $this->markTestSkipped('No readers in database.');
        }

        return (string) $readerId;
    }

    private function findOrFailTestCopy(): string
    {
        $copyId = DB::connection('pgsql')
            ->table('app.book_copies')
            ->whereNotIn('id', function ($query) {
                $query->select('copy_id')
                    ->from('app.circulation_loans')
                    ->where('status', 'active');
            })
            ->limit(1)
            ->value('id');

        if ($copyId === null) {
            $this->markTestSkipped('No available copies in database.');
        }

        return (string) $copyId;
    }
}
