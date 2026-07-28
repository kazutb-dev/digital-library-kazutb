<?php

namespace Tests\Feature\Api;

use App\Models\Library\CirculationLoan;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class InternalCirculationReadbackTest extends TestCase
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

    public function test_loan_lookup_endpoint_returns_minimal_loan_payload(): void
    {
        [$readerId, $copyId] = $this->pickReaderAndCopy();

        $loan = $this->createLoan($readerId, $copyId, 'active', null);

        $response = $this->withSession($this->staffSession())->getJson('/api/v1/internal/circulation/loans/' . $loan->id);

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', (string) $loan->id)
            ->assertJsonPath('data.readerId', $readerId)
            ->assertJsonPath('data.copyId', $copyId)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_copy_active_loan_endpoint_returns_active_loan(): void
    {
        [$readerId, $copyId] = $this->pickReaderAndCopy();

        $loan = $this->createLoan($readerId, $copyId, 'active', null);

        $response = $this->withSession($this->staffSession())->getJson('/api/v1/internal/circulation/copies/' . $copyId . '/active-loan');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.id', (string) $loan->id)
            ->assertJsonPath('data.copyId', $copyId)
            ->assertJsonPath('data.status', 'active');
    }

    public function test_copy_active_loan_endpoint_returns_not_found_when_no_active_loan_exists(): void
    {
        [, $copyId] = $this->pickReaderAndCopy();

        $response = $this->withSession($this->staffSession())->getJson('/api/v1/internal/circulation/copies/' . $copyId . '/active-loan');

        $response
            ->assertNotFound()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'active_loan_not_found');
    }

    public function test_reader_loans_endpoint_supports_status_filter(): void
    {
        [$readerId, $copyId] = $this->pickReaderAndCopy();
        $secondCopyId = $this->pickAnotherLoanFreeCopy($copyId);

        $this->createLoan($readerId, $copyId, 'active', null);
        $this->createLoan($readerId, $secondCopyId, 'returned', now('UTC')->subDay());

        $response = $this->withSession($this->staffSession())->getJson('/api/v1/internal/circulation/readers/' . $readerId . '/loans?status=active');

        $response
            ->assertOk()
            ->assertJsonPath('success', true)
            ->assertJsonCount(1, 'data')
            ->assertJsonPath('data.0.copyId', $copyId)
            ->assertJsonPath('data.0.status', 'active');
    }

    public function test_readback_requires_staff_session(): void
    {
        [$readerId, $copyId] = $this->pickReaderAndCopy();
        $loan = $this->createLoan($readerId, $copyId, 'active', null);

        $response = $this->getJson('/api/v1/internal/circulation/loans/' . $loan->id);

        $response
            ->assertForbidden()
            ->assertJsonPath('success', false)
            ->assertJsonPath('error', 'staff_authorization_required');
    }

    private function createLoan(string $readerId, string $copyId, string $status, ?\Illuminate\Support\Carbon $returnedAt): CirculationLoan
    {
        return CirculationLoan::query()->create([
            'id' => (string) DB::scalar('select gen_random_uuid()::text'),
            'reader_id' => $readerId,
            'copy_id' => $copyId,
            'status' => $status,
            'issued_at' => now('UTC')->subDays(2),
            'due_at' => now('UTC')->addDays(12),
            'returned_at' => $returnedAt,
            'renew_count' => 0,
        ]);
    }

    /**
     * @return array{string, string}
     */
    private function pickReaderAndCopy(): array
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL is not available for internal circulation readback tests.');
        }

        $readerId = DB::connection('pgsql')->table('app.readers')->value('id');
        $copyId = DB::connection('pgsql')->table('app.book_copies as bc')
            ->leftJoin('app.circulation_loans as cl', function ($join): void {
                $join->on('cl.copy_id', '=', 'bc.id')
                    ->where('cl.status', '=', 'active')
                    ->whereNull('cl.returned_at');
            })
            ->whereNull('cl.id')
            ->value('bc.id');

        if (! is_string($readerId) || $readerId === '' || ! is_string($copyId) || $copyId === '') {
            $this->markTestSkipped('Required readers or loan-free copies are not available for readback tests.');
        }

        return [$readerId, $copyId];
    }

    private function pickAnotherLoanFreeCopy(string $excludeCopyId): string
    {
        $copyId = DB::connection('pgsql')->table('app.book_copies as bc')
            ->leftJoin('app.circulation_loans as cl', function ($join): void {
                $join->on('cl.copy_id', '=', 'bc.id')
                    ->where('cl.status', '=', 'active')
                    ->whereNull('cl.returned_at');
            })
            ->whereNull('cl.id')
            ->where('bc.id', '!=', $excludeCopyId)
            ->value('bc.id');

        if (! is_string($copyId) || $copyId === '') {
            $this->markTestSkipped('A second loan-free copy is not available for readback tests.');
        }

        return $copyId;
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
