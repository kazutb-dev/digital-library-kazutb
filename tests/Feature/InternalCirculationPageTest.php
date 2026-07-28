<?php

namespace Tests\Feature;

use Tests\TestCase;

class InternalCirculationPageTest extends TestCase
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

    public function test_circulation_page_renders_successfully(): void
    {
        $response = $this->withSession($this->staffSession())->get('/internal/circulation');

        $response
            ->assertOk()
            ->assertSee('Circulation Desk', false)
            ->assertSee('/api/v1/internal/circulation', false)
            ->assertSee('Выдача книги', false)
            ->assertSee('Возврат книги', false);
    }

    public function test_circulation_page_has_checkout_form(): void
    {
        $response = $this->withSession($this->staffSession())->get('/internal/circulation');

        $response
            ->assertOk()
            ->assertSee('checkout-reader-id', false)
            ->assertSee('checkout-copy-id', false)
            ->assertSee('checkout-due-at', false)
            ->assertSee('/checkouts', false);
    }

    public function test_circulation_page_has_return_form(): void
    {
        $response = $this->withSession($this->staffSession())->get('/internal/circulation');

        $response
            ->assertOk()
            ->assertSee('return-copy-id', false)
            ->assertSee('/returns', false);
    }

    public function test_circulation_page_has_reader_lookup(): void
    {
        $response = $this->withSession($this->staffSession())->get('/internal/circulation');

        $response
            ->assertOk()
            ->assertSee('reader-lookup-id', false)
            ->assertSee('reader-status-filter', false)
            ->assertSee('Выдачи читателя', false);
    }

    public function test_circulation_page_links_to_other_internal_pages(): void
    {
        $response = $this->withSession($this->staffSession())->get('/internal/circulation');

        $response
            ->assertOk()
            ->assertSee('/internal/dashboard', false)
            ->assertSee('/internal/stewardship', false)
            ->assertSee('/internal/review', false);
    }
}
