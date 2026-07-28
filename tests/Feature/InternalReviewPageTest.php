<?php

namespace Tests\Feature;

use Tests\TestCase;

class InternalReviewPageTest extends TestCase
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

    public function test_internal_review_page_renders_successfully(): void
    {
        $response = $this->withSession($this->staffSession())->get('/internal/review');

        $response
            ->assertOk()
            ->assertSee('Quality Issues Overview')
            ->assertSee('/internal/dashboard', false)
            ->assertSee('Вернуться к dashboard')
            ->assertSee('/api/v1/review/issues', false)
            ->assertSee('Severity')
            ->assertSee('Status');
    }
}
