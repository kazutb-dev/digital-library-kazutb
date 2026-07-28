<?php

namespace Tests\Feature;

use Tests\TestCase;

class InternalDashboardPageTest extends TestCase
{
    private function staffSession(array $userOverrides = []): array
    {
        return [
            'library.user' => array_merge([
                'id' => 'staff-1',
                'name' => 'Library Staff',
                'email' => 'staff@example.com',
                'login' => 'staff01',
                'ad_login' => 'staff01',
                'role' => 'librarian',
            ], $userOverrides),
            'library.crm_token' => 'test-staff-token',
            'library.authenticated_at' => now()->toIso8601String(),
        ];
    }

    public function test_internal_dashboard_page_renders_admin_overview_export_surface(): void
    {
        $response = $this->withSession($this->staffSession([ 'role' => 'admin' ]))->get('/internal/dashboard');

        $response
            ->assertOk()
            ->assertSee('data-admin-overview-page', false)
            ->assertSee('data-admin-overview-hero', false)
            ->assertSee('data-admin-overview-health', false)
            ->assertSee('data-admin-overview-activity', false)
            ->assertSee('data-admin-overview-actions', false)
            ->assertSee('System Oversight')
            ->assertSee('Health Summary')
            ->assertSee('System-wide Activity')
            ->assertSee('Quick Links')
            ->assertSee('/internal/review', false)
            ->assertSee('/internal/stewardship', false)
            ->assertSee('/internal/circulation', false)
            ->assertSee('/internal/ai-chat', false)
            ->assertSee('/catalog', false)
            ->assertSee('/contacts', false)
            ->assertSee('/api/v1/internal/review/triage-summary?top_limit=6', false)
            ->assertSee('/api/v1/internal/review/readers-summary?top_limit=5', false)
            ->assertSee('/api/v1/internal/reader-contacts/stats', false)
            ->assertSee('/api/v1/internal/review/stewardship-metrics', false)
            ->assertSee('/api/v1/internal/enrichment/stats', false)
            ->assertDontSee('href="#"', false)
            ->assertDontSee('Scholarly Works');
    }
}
