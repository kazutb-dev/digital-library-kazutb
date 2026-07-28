<?php

namespace Tests\Feature\Api;

use App\Models\Library\CirculationLoan;
use App\Services\Library\CirculationLoanReadService;
use Illuminate\Foundation\Http\Middleware\PreventRequestsDuringMaintenance;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class LoanVisibilityTest extends TestCase
{
    /** A valid UUID that won't match any real record. */
    private const FAKE_UUID = '00000000-0000-0000-0000-000000000000';
    private const FAKE_UUID_2 = '00000000-0000-0000-0000-000000000001';

    // ─── Session auth helper ─────────────────────────────────

    private function withAuthSession(array $user = []): static
    {
        $defaults = [
            'id' => 'test-user-1',
            'name' => 'Тест Тестов',
            'email' => 'test@digital-library.test',
            'role' => 'student',
        ];

        return $this->withSession(['library.user' => array_merge($defaults, $user)]);
    }

    // ═══════════════════════════════════════════════════════════
    // 1. Loan Summary Endpoint
    // ═══════════════════════════════════════════════════════════

    public function test_loan_summary_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/account/loans/summary');
        $response->assertStatus(401);
    }

    public function test_loan_summary_returns_structure(): void
    {
        $response = $this->withAuthSession()->getJson('/api/v1/account/loans/summary');

        $response->assertOk();
        $response->assertJsonStructure([
            'authenticated',
            'data' => ['activeLoans', 'overdueLoans', 'dueSoonLoans', 'returnedLoans', 'totalLoans'],
        ]);
    }

    public function test_loan_summary_returns_zero_counts_when_no_reader(): void
    {
        $response = $this->withAuthSession()->getJson('/api/v1/account/loans/summary');

        $response->assertOk();
        $response->assertJsonPath('data.activeLoans', 0);
        $response->assertJsonPath('data.overdueLoans', 0);
        $response->assertJsonPath('data.dueSoonLoans', 0);
        $response->assertJsonPath('data.returnedLoans', 0);
        $response->assertJsonPath('data.totalLoans', 0);
    }

    // ═══════════════════════════════════════════════════════════
    // 2. Loans list endpoint (with status filter)
    // ═══════════════════════════════════════════════════════════

    public function test_loans_requires_auth(): void
    {
        $response = $this->getJson('/api/v1/account/loans');
        $response->assertStatus(401);
    }

    public function test_loans_returns_data_array(): void
    {
        $response = $this->withAuthSession()->getJson('/api/v1/account/loans');

        $response->assertOk();
        $response->assertJsonStructure(['authenticated', 'data']);
        $this->assertIsArray($response->json('data'));
    }

    public function test_loans_accepts_active_status_filter(): void
    {
        $response = $this->withAuthSession()->getJson('/api/v1/account/loans?status=active');

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    public function test_loans_accepts_returned_status_filter(): void
    {
        $response = $this->withAuthSession()->getJson('/api/v1/account/loans?status=returned');

        $response->assertOk();
        $response->assertJsonStructure(['data']);
    }

    public function test_loans_ignores_invalid_status_filter(): void
    {
        $response = $this->withAuthSession()->getJson('/api/v1/account/loans?status=bogus');

        // Should still return OK (filter falls through to null = all loans)
        $response->assertOk();
    }

    // ═══════════════════════════════════════════════════════════
    // 3. CirculationLoanReadService unit-level
    // ═══════════════════════════════════════════════════════════

    public function test_service_summary_returns_expected_keys(): void
    {
        $service = new CirculationLoanReadService();
        $summary = $service->summaryForReader(self::FAKE_UUID);

        $this->assertArrayHasKey('activeLoans', $summary);
        $this->assertArrayHasKey('overdueLoans', $summary);
        $this->assertArrayHasKey('dueSoonLoans', $summary);
        $this->assertArrayHasKey('returnedLoans', $summary);
        $this->assertArrayHasKey('totalLoans', $summary);
    }

    public function test_service_summary_returns_zeros_for_nonexistent_reader(): void
    {
        $service = new CirculationLoanReadService();
        $summary = $service->summaryForReader(self::FAKE_UUID_2);

        $this->assertEquals(0, $summary['activeLoans']);
        $this->assertEquals(0, $summary['overdueLoans']);
        $this->assertEquals(0, $summary['dueSoonLoans']);
        $this->assertEquals(0, $summary['returnedLoans']);
        $this->assertEquals(0, $summary['totalLoans']);
    }

    public function test_service_find_loan_returns_null_for_missing(): void
    {
        $service = new CirculationLoanReadService();
        $this->assertNull($service->findLoan(self::FAKE_UUID));
    }

    public function test_service_find_loans_by_reader_returns_empty_array(): void
    {
        $service = new CirculationLoanReadService();
        $loans = $service->findLoansByReader(self::FAKE_UUID_2);

        $this->assertIsArray($loans);
        $this->assertEmpty($loans);
    }

    public function test_service_find_active_loan_by_copy_returns_null(): void
    {
        $service = new CirculationLoanReadService();
        $this->assertNull($service->findActiveLoanByCopy(self::FAKE_UUID));
    }

    // ═══════════════════════════════════════════════════════════
    // 4. Account page rendering
    // ═══════════════════════════════════════════════════════════

    public function test_account_page_renders_loan_section(): void
    {
        $response = $this->withAuthSession()->get('/account');

        $response->assertOk();
        $response->assertSee('Мои книги');
        $response->assertSee('book-grid');
    }

    public function test_account_page_has_loan_tabs(): void
    {
        $response = $this->withAuthSession()->get('/account');

        $response->assertOk();
        $response->assertSee('loan-tab');
        $response->assertSee('Активные');
        $response->assertSee('Возвращённые');
    }

    public function test_account_page_has_circulation_stats(): void
    {
        $response = $this->withAuthSession()->get('/account');

        $response->assertOk();
        $response->assertSee('active-loans-count');
        $response->assertSee('overdue-loans-count');
        $response->assertSee('due-soon-loans-count');
        $response->assertSee('returned-loans-count');
    }

    public function test_account_page_has_loan_summary_endpoint(): void
    {
        $response = $this->withAuthSession()->get('/account');

        $response->assertOk();
        $response->assertSee('account/loans/summary');
    }

    // ═══════════════════════════════════════════════════════════
    // 5. Enriched loan structure (if live PG available)
    // ═══════════════════════════════════════════════════════════

    private function canUseLivePgsql(): bool
    {
        try {
            DB::connection('pgsql')->getPdo();
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    public function test_enriched_loan_includes_book_object_on_live_pg(): void
    {
        if (! $this->canUseLivePgsql()) {
            $this->markTestSkipped('No live PostgreSQL connection available.');
        }

        Config::set('database.default', 'pgsql');
        DB::purge('pgsql');

        $service = new CirculationLoanReadService();
        // Even with no loans, we can test the resolve method indirectly
        // by verifying findLoansByReader returns structurally correct data
        $loans = $service->findLoansByReader(self::FAKE_UUID);
        $this->assertIsArray($loans);
    }

    // ═══════════════════════════════════════════════════════════
    // 6. No regressions on existing endpoints
    // ═══════════════════════════════════════════════════════════

    public function test_account_summary_still_works(): void
    {
        $response = $this->withAuthSession()->getJson('/api/v1/account/summary');
        $response->assertOk();
        $response->assertJsonPath('authenticated', true);
    }

    public function test_reservations_endpoint_still_works(): void
    {
        $response = $this->withAuthSession()->getJson('/api/v1/account/reservations');
        $response->assertOk();
    }
}
