<?php

namespace Tests\Feature\Api;

use App\Models\Library\CirculationLoan;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class AccountRenewalTest extends TestCase
{
    private bool $useLivePgsql = false;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);

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

    public function test_account_renew_route_exists(): void
    {
        $fakeUuid = '00000000-0000-0000-0000-000000000000';

        $response = $this->postJson("/api/v1/account/loans/{$fakeUuid}/renew");

        $this->assertNotEquals(405, $response->status(), 'Route should exist');
    }

    public function test_account_renew_requires_auth(): void
    {
        $fakeUuid = '00000000-0000-0000-0000-000000000000';

        $response = $this->postJson("/api/v1/account/loans/{$fakeUuid}/renew");

        $response->assertStatus(401);
        $response->assertJson(['authenticated' => false]);
    }

    public function test_account_renew_requires_reader_profile(): void
    {
        $session = [
            'library.user' => [
                'id' => 'bbbbbbbb-0000-0000-0000-222222222222',
                'role' => 'reader',
                'email' => 'notlinked-test-nobody@example.com',
                'name' => 'No Profile',
            ],
        ];

        $fakeUuid = '00000000-0000-0000-0000-ffffffffffff';

        $response = $this->withSession($session)
            ->postJson("/api/v1/account/loans/{$fakeUuid}/renew");

        $response->assertStatus(403);
        $response->assertJson(['error' => 'no_reader_profile']);
    }

    public function test_account_renew_blocks_overdue_self_renewal(): void
    {
        $this->requireLivePgsql();

        $context = $this->createLinkedReaderWithLoan([
            'status' => 'active',
            'renew_count' => 0,
            'due_at' => Carbon::now('UTC')->subDays(3),
        ]);

        $response = $this->withSession($context['session'])
            ->postJson("/api/v1/account/loans/{$context['loanId']}/renew");

        $response->assertStatus(409);
        $response->assertJson(['success' => false, 'error' => 'loan_overdue']);
    }

    public function test_account_renew_succeeds_for_active_non_overdue_loan(): void
    {
        $this->requireLivePgsql();

        $context = $this->createLinkedReaderWithLoan([
            'status' => 'active',
            'renew_count' => 0,
            'due_at' => Carbon::now('UTC')->addDays(5),
        ]);

        $response = $this->withSession($context['session'])
            ->postJson("/api/v1/account/loans/{$context['loanId']}/renew");

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertEquals(1, $response->json('data.renewCount'));
    }

    public function test_account_renew_cannot_renew_other_readers_loan(): void
    {
        $this->requireLivePgsql();

        $otherReaderId = $this->findReaderWithoutEmail('phantom-test@example.com');
        $loan = $this->createTestLoan($otherReaderId, [
            'status' => 'active',
            'renew_count' => 0,
            'due_at' => Carbon::now('UTC')->addDays(5),
        ]);

        $session = [
            'library.user' => [
                'id' => 'cccccccc-0000-0000-0000-333333333333',
                'role' => 'reader',
                'email' => 'phantom-test@example.com',
                'name' => 'Phantom',
            ],
        ];

        $response = $this->withSession($session)
            ->postJson("/api/v1/account/loans/{$loan->id}/renew");

        $this->assertTrue(
            in_array($response->status(), [403, 404]),
            "Should be 403 or 404, got {$response->status()}"
        );
    }

    /**
     * Creates a reader with an email contact and an active loan, returns session + loanId.
     */
    private function createLinkedReaderWithLoan(array $loanOverrides = []): array
    {
        $readerId = $this->findOrFailTestReader();
        $email = $this->findOrCreateReaderEmail($readerId);
        $loan = $this->createTestLoan($readerId, $loanOverrides);

        return [
            'session' => [
                'library.user' => [
                    'id' => 'dddddddd-0000-0000-0000-444444444444',
                    'role' => 'reader',
                    'email' => $email,
                    'name' => 'Test Reader',
                ],
            ],
            'loanId' => (string) $loan->id,
            'readerId' => $readerId,
        ];
    }

    private function createTestLoan(string $readerId, array $overrides = []): CirculationLoan
    {
        $copyId = $this->findOrFailTestCopy();
        $id = (string) \Illuminate\Support\Str::uuid();
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

    private function findOrCreateReaderEmail(string $readerId): string
    {
        $existing = DB::connection('pgsql')
            ->table('app.reader_contacts')
            ->where('reader_id', $readerId)
            ->where('contact_type', 'EMAIL')
            ->whereRaw("value_normalized_key IS NOT NULL AND TRIM(value_normalized_key) != ''")
            ->value('value_normalized_key');

        if ($existing !== null && trim((string) $existing) !== '') {
            return (string) $existing;
        }

        $email = "testrenew-" . substr($readerId, 0, 8) . "@test.local";
        DB::connection('pgsql')->table('app.reader_contacts')->insert([
            'id' => (string) \Illuminate\Support\Str::uuid(),
            'reader_id' => $readerId,
            'contact_type' => 'EMAIL',
            'value_raw' => $email,
            'value_normalized' => mb_strtolower($email),
            'value_normalized_key' => mb_strtolower($email),
            'created_at' => Carbon::now('UTC'),
        ]);

        return $email;
    }

    private function findReaderWithoutEmail(string $excludeEmail): string
    {
        $readerId = DB::connection('pgsql')
            ->table('app.readers as r')
            ->whereNotExists(function ($query) use ($excludeEmail) {
                $query->select(DB::raw(1))
                    ->from('app.reader_contacts as rc')
                    ->whereColumn('rc.reader_id', 'r.id')
                    ->where('rc.contact_type', 'EMAIL')
                    ->where('rc.value_normalized_key', mb_strtolower($excludeEmail));
            })
            ->limit(1)
            ->value('r.id');

        if ($readerId === null) {
            $this->markTestSkipped('Cannot find reader without specific email.');
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

    // ═══════════════════════════════════════════════════════════
    // 7. canRenew / renewBlockReason fields
    // ═══════════════════════════════════════════════════════════

    public function test_active_loan_read_includes_can_renew_true(): void
    {
        $this->requireLivePgsql();

        $context = $this->createLinkedReaderWithLoan([
            'status' => 'active',
            'renew_count' => 0,
            'due_at' => Carbon::now('UTC')->addDays(5),
        ]);

        $service = new \App\Services\Library\CirculationLoanReadService();
        $loan = $service->findLoan($context['loanId']);

        $this->assertNotNull($loan);
        $this->assertTrue($loan['canRenew']);
        $this->assertNull($loan['renewBlockReason']);
        $this->assertEquals(3, $loan['maxRenewals']);
    }

    public function test_overdue_loan_read_includes_block_reason(): void
    {
        $this->requireLivePgsql();

        $context = $this->createLinkedReaderWithLoan([
            'status' => 'active',
            'renew_count' => 0,
            'due_at' => Carbon::now('UTC')->subDays(5),
        ]);

        $service = new \App\Services\Library\CirculationLoanReadService();
        $loan = $service->findLoan($context['loanId']);

        $this->assertNotNull($loan);
        $this->assertFalse($loan['canRenew']);
        $this->assertNotNull($loan['renewBlockReason']);
        $this->assertStringContainsString('Просроченную', $loan['renewBlockReason']);
    }

    public function test_max_renewals_loan_read_includes_block_reason(): void
    {
        $this->requireLivePgsql();

        $context = $this->createLinkedReaderWithLoan([
            'status' => 'active',
            'renew_count' => 3,
            'due_at' => Carbon::now('UTC')->addDays(5),
        ]);

        $service = new \App\Services\Library\CirculationLoanReadService();
        $loan = $service->findLoan($context['loanId']);

        $this->assertNotNull($loan);
        $this->assertFalse($loan['canRenew']);
        $this->assertNotNull($loan['renewBlockReason']);
        $this->assertStringContainsString('лимит', $loan['renewBlockReason']);
    }

    public function test_returned_loan_read_includes_block_reason(): void
    {
        $this->requireLivePgsql();

        $context = $this->createLinkedReaderWithLoan([
            'status' => 'returned',
            'renew_count' => 0,
            'due_at' => Carbon::now('UTC')->addDays(5),
            'returned_at' => Carbon::now('UTC'),
        ]);

        $service = new \App\Services\Library\CirculationLoanReadService();
        $loan = $service->findLoan($context['loanId']);

        $this->assertNotNull($loan);
        $this->assertFalse($loan['canRenew']);
        $this->assertNotNull($loan['renewBlockReason']);
        $this->assertStringContainsString('Возвращённые', $loan['renewBlockReason']);
    }

    public function test_renewal_response_includes_max_renewals(): void
    {
        $this->requireLivePgsql();

        $context = $this->createLinkedReaderWithLoan([
            'status' => 'active',
            'renew_count' => 1,
            'due_at' => Carbon::now('UTC')->addDays(5),
        ]);

        $response = $this->withSession($context['session'])
            ->postJson("/api/v1/account/loans/{$context['loanId']}/renew");

        $response->assertOk();
        $response->assertJson(['success' => true]);
        $this->assertEquals(3, $response->json('data.maxRenewals'));
        $this->assertEquals(2, $response->json('data.renewCount'));
    }

    public function test_max_renewals_reached_returns_409(): void
    {
        $this->requireLivePgsql();

        $context = $this->createLinkedReaderWithLoan([
            'status' => 'active',
            'renew_count' => 3,
            'due_at' => Carbon::now('UTC')->addDays(5),
        ]);

        $response = $this->withSession($context['session'])
            ->postJson("/api/v1/account/loans/{$context['loanId']}/renew");

        $response->assertStatus(409);
        $response->assertJson(['success' => false, 'error' => 'max_renewals_reached']);
    }

    // ═══════════════════════════════════════════════════════════
    // 8. Account page UX assertions
    // ═══════════════════════════════════════════════════════════

    public function test_account_page_has_renewal_confirmation_modal(): void
    {
        $response = $this->withSession([
            'library.user' => [
                'id' => 'test-1', 'name' => 'Test', 'email' => 't@t.kz', 'role' => 'reader',
            ],
        ])->get('/account');

        $response->assertOk();
        $response->assertSee('confirm-modal');
        $response->assertSee('readerRenew');
    }

    public function test_account_page_has_loading_state(): void
    {
        $response = $this->withSession([
            'library.user' => [
                'id' => 'test-1', 'name' => 'Test', 'email' => 't@t.kz', 'role' => 'reader',
            ],
        ])->get('/account');

        $response->assertOk();
        $response->assertSee('renew-btn-');
    }
}
