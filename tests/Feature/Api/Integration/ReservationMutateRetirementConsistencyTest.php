<?php

namespace Tests\Feature\Api\Integration;

use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class ReservationMutateRetirementConsistencyTest extends TestCase
{
    private bool $transactionStarted = false;

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

    public function test_approve_returns_409_when_reservation_copy_is_retired(): void
    {
        $this->requireLivePgsql();

        $base = $this->pickBaseReservationForeignKeys();
        $reservationCopyId = $this->createMappedAppCopyForReservation(retired: true);
        $reservationId = $this->insertPendingCopyBoundReservation($base, $reservationCopyId);

        $response = $this->withHeaders($this->integrationHeaders(
            branchId: $base['branch_id'],
            idempotencyKey: 'idem-retired-approve-001',
        ))->postJson('/api/integration/v1/reservations/' . $reservationId . '/approve');

        $response
            ->assertStatus(409)
            ->assertJsonPath('error.error_code', 'conflict')
            ->assertJsonPath('error.reason_code', 'copy_retired');

        $this->assertSame(
            'PENDING',
            (string) DB::connection('pgsql')->table(DB::raw('"Reservation"'))->where('id', $reservationId)->value('status')
        );
        $this->assertNull(
            DB::connection('pgsql')->table(DB::raw('"Reservation"'))->where('id', $reservationId)->value('processedAt')
        );
    }

    public function test_approve_still_succeeds_for_non_retired_copy_bound_reservation(): void
    {
        $this->requireLivePgsql();

        $base = $this->pickBaseReservationForeignKeys();
        $reservationCopyId = $this->createMappedAppCopyForReservation(retired: false);
        $reservationId = $this->insertPendingCopyBoundReservation($base, $reservationCopyId);

        $response = $this->withHeaders($this->integrationHeaders(
            branchId: $base['branch_id'],
            idempotencyKey: 'idem-active-approve-001',
        ))->postJson('/api/integration/v1/reservations/' . $reservationId . '/approve');

        $response
            ->assertOk()
            ->assertJsonPath('data.id', $reservationId)
            ->assertJsonPath('data.status', 'READY');

        $this->assertSame(
            'READY',
            (string) DB::connection('pgsql')->table(DB::raw('"Reservation"'))->where('id', $reservationId)->value('status')
        );
        $this->assertNotNull(
            DB::connection('pgsql')->table(DB::raw('"Reservation"'))->where('id', $reservationId)->value('processedAt')
        );
    }

    /**
     * @return array{user_id:string, book_id:string, branch_id:string}
     */
    private function pickBaseReservationForeignKeys(): array
    {
        $row = DB::connection('pgsql')
            ->table(DB::raw('public."Reservation" as r'))
            ->select([
                DB::raw('r."userId"::text as user_id'),
                DB::raw('r."bookId"::text as book_id'),
                DB::raw('r."libraryBranchId"::text as branch_id'),
            ])
            ->first();

        if ($row === null) {
            $this->markTestSkipped('No base Reservation row is available for integration mutate fixture setup.');
        }

        return [
            'user_id' => (string) $row->user_id,
            'book_id' => (string) $row->book_id,
            'branch_id' => (string) $row->branch_id,
        ];
    }

    private function createMappedAppCopyForReservation(bool $retired): string
    {
        $bookCopyId = DB::connection('pgsql')->selectOne(
            'SELECT bc.id::text AS id
             FROM public."BookCopy" bc
             WHERE NOT EXISTS (
                 SELECT 1 FROM app.book_copies ac WHERE ac.core_copy_id::text = bc.id::text
             )
             LIMIT 1'
        );

        if ($bookCopyId === null || ! isset($bookCopyId->id) || (string) $bookCopyId->id === '') {
            $this->markTestSkipped('No unlinked BookCopy id is available to build deterministic reservation retirement fixtures.');
        }

        $copyId = (string) $bookCopyId->id;

        DB::connection('pgsql')->table('app.book_copies')->insert([
            'id' => (string) DB::scalar('select gen_random_uuid()::text'),
            'core_copy_id' => $copyId,
            'legacy_inv_id' => random_int(1000000, 9000000),
            'source_payload' => json_encode([], JSON_UNESCAPED_SLASHES),
            'retired_at' => $retired ? now('UTC') : null,
            'retirement_reason_code' => $retired ? 'WRITTEN_OFF' : null,
            'retirement_note' => $retired ? 'fixture for reservation mutate retirement consistency test' : null,
        ]);

        return $copyId;
    }

    /**
     * @param array{user_id:string, book_id:string, branch_id:string} $base
     */
    private function insertPendingCopyBoundReservation(array $base, string $copyId): string
    {
        $reservationId = (string) DB::scalar('select gen_random_uuid()::text');
        $now = now('UTC')->toDateTimeString();

        DB::connection('pgsql')->table(DB::raw('"Reservation"'))->insert([
            'id' => $reservationId,
            'status' => 'PENDING',
            'reservedAt' => $now,
            'expiresAt' => now('UTC')->addDays(5)->toDateTimeString(),
            'processedAt' => null,
            'notes' => null,
            'createdAt' => $now,
            'updatedAt' => $now,
            'userId' => $base['user_id'],
            'bookId' => $base['book_id'],
            'libraryBranchId' => $base['branch_id'],
            'copyId' => $copyId,
            'processedByUserId' => null,
        ]);

        return $reservationId;
    }

    /**
     * @return array<string, string>
     */
    private function integrationHeaders(string $branchId, string $idempotencyKey): array
    {
        return [
            'Authorization' => 'Bearer integration-test-token',
            'X-Request-Id' => 'req-mut-retirement-001',
            'X-Correlation-Id' => 'corr-mut-retirement-001',
            'X-Source-System' => 'crm',
            'X-Operator-Id' => 'crm-op-99',
            'X-Operator-Roles' => 'reservations.approve,reservations.reject',
            'X-Operator-Org-Context' => json_encode(['branch_id' => $branchId], JSON_UNESCAPED_SLASHES),
            'Idempotency-Key' => $idempotencyKey,
        ];
    }

    private function requireLivePgsql(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('Live PostgreSQL is required for reservation mutate retirement consistency tests.');
        }
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
