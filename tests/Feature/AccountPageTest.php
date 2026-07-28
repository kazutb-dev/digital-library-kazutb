<?php

namespace Tests\Feature;

use Tests\TestCase;

class AccountPageTest extends TestCase
{
    private function authenticatedSession(array $userOverrides = []): array
    {
        return [
            'library.user' => array_merge([
                'id' => 'u-test-1', 'name' => 'Test', 'email' => 'test@example.com',
                'login' => 'test01', 'ad_login' => 'test01', 'role' => 'reader',
            ], $userOverrides),
            'library.crm_token' => 'test-token',
            'library.authenticated_at' => now()->toIso8601String(),
        ];
    }

    public function test_account_page_renders_successfully(): void
    {
        $response = $this->withSession($this->authenticatedSession())->get('/account?lang=ru');

        $response
            ->assertOk()
            ->assertSee('Кабинет читателя', false)
            ->assertSee('/api/v1/account/summary', false);
    }

    public function test_account_page_loads_real_loans_not_catalog(): void
    {
        $response = $this->withSession($this->authenticatedSession())->get('/account');

        $response
            ->assertOk()
            ->assertSee('/api/v1/account/loans', false)
            ->assertDontSee('/api/v1/catalog-db?limit=6', false);
    }

    public function test_account_page_shows_loan_section(): void
    {
        $response = $this->withSession($this->authenticatedSession())->get('/account?lang=ru');

        $response
            ->assertOk()
            ->assertSee('Мои книги')
            ->assertSee('Текущие и недавние выдачи из библиотечного фонда');
    }

    public function test_account_page_redirects_unauthenticated(): void
    {
        $response = $this->get('/account');
        $response->assertRedirect('/login?redirect=%2Faccount');
    }

    public function test_account_page_shows_workbench_section(): void
    {
        $response = $this->withSession($this->authenticatedSession([
            'profile_type' => 'teacher',
        ]))->get('/account?lang=ru');

        $response
            ->assertOk()
            ->assertSee('workbench-section', false)
            ->assertSee('Подборка и сохранённые действия', false)
            ->assertSee('loadWorkbench', false);
    }

    public function test_account_page_uses_export_dashboard_sections_with_stable_shell_and_safe_routes(): void
    {
        $response = $this->withSession($this->authenticatedSession())->get('/account?lang=en');

        $response
            ->assertOk()
            ->assertSee('data-member-dashboard-page', false)
            ->assertSee('data-member-dashboard-hero', false)
            ->assertSee('data-member-dashboard-overview', false)
            ->assertSee('data-member-dashboard-reservations', false)
            ->assertSee('data-member-dashboard-activity', false)
            ->assertSee('data-member-dashboard-note', false)
            ->assertSee('Member Dashboard')
            ->assertSee('Current Reservations')
            ->assertSee('Quick Actions')
            ->assertSee('Recent Activity')
            ->assertSee("Librarian's Note")
            ->assertSee('/catalog', false)
            ->assertSee('/shortlist', false)
            ->assertSee('/resources', false)
            ->assertSee('My Desk')
            ->assertSee('Research Folders')
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Search archive...')
            ->assertDontSee('Archive');
    }

    public function test_account_page_workbench_calls_summary_api(): void
    {
        $response = $this->withSession($this->authenticatedSession([
            'profile_type' => 'teacher',
        ]))->get('/account');

        $response
            ->assertOk()
            ->assertSee('/api/v1/shortlist/summary', false);
    }
}
