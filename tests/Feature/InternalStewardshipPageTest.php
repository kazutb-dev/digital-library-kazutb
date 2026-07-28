<?php

namespace Tests\Feature;

use Tests\TestCase;

class InternalStewardshipPageTest extends TestCase
{
    private function staffSession(): array
    {
        return [
            'library.user' => [
                'id' => 'staff-1',
                'name' => 'Library Staff',
                'email' => 'staff@example.com',
                'login' => 'staff01',
                'ad_login' => 'staff01',
                'role' => 'librarian',
            ],
            'library.crm_token' => 'test-staff-token',
            'library.authenticated_at' => now()->toIso8601String(),
        ];
    }

    public function test_stewardship_page_renders_successfully(): void
    {
        $response = $this->withSession($this->staffSession())->get('/internal/stewardship');

        $response
            ->assertOk()
            ->assertSee('Data Stewardship', false)
            ->assertSee('Очереди проверки данных')
            ->assertSee('/api/v1/internal/review/stewardship-metrics', false)
            ->assertSee('/api/v1/internal/review/copies', false)
            ->assertSee('/api/v1/internal/review/documents', false)
            ->assertSee('/api/v1/internal/review/readers', false);
    }

    public function test_stewardship_page_has_tab_navigation(): void
    {
        $response = $this->withSession($this->staffSession())->get('/internal/stewardship');

        $response
            ->assertOk()
            ->assertSee('data-tab="overview"', false)
            ->assertSee('data-tab="copies"', false)
            ->assertSee('data-tab="documents"', false)
            ->assertSee('data-tab="readers"', false);
    }

    public function test_stewardship_page_has_action_endpoints(): void
    {
        $response = $this->withSession($this->staffSession())->get('/internal/stewardship');

        $response
            ->assertOk()
            ->assertSee('/resolve', false)
            ->assertSee('/flag', false)
            ->assertSee('resolution_note', false);
    }

    public function test_stewardship_page_has_bulk_action_ui(): void
    {
        $response = $this->withSession($this->staffSession())->get('/internal/stewardship');

        $response
            ->assertOk()
            ->assertSee('copy-bulk-bar', false)
            ->assertSee('doc-bulk-bar', false)
            ->assertSee('reader-bulk-bar', false)
            ->assertSee('bulk-resolve', false)
            ->assertSee('bulkResolveCopies', false)
            ->assertSee('bulkResolveDocs', false)
            ->assertSee('bulkResolveReaders', false)
            ->assertSee('bulkFlagDocs', false)
            ->assertSee('cb-all', false);
    }

    public function test_stewardship_page_links_to_other_internal_pages(): void
    {
        $response = $this->withSession($this->staffSession())->get('/internal/stewardship');

        $response
            ->assertOk()
            ->assertSee('/internal/dashboard', false)
            ->assertSee('/internal/review', false);
    }
}
