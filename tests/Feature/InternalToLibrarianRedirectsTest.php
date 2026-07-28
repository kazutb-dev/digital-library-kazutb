<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Tests\TestCase;

class InternalToLibrarianRedirectsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config()->set('demo_auth.enabled', true);
        $this->withoutMiddleware([VerifyCsrfToken::class, ValidateCsrfToken::class]);
    }

    public function test_internal_dashboard_redirects_to_librarian_overview(): void
    {
        $response = $this->get('/internal/dashboard');

        $response->assertStatus(301);
        $response->assertRedirect('/librarian');
    }

    public function test_internal_circulation_redirects_to_librarian_circulation(): void
    {
        $response = $this->get('/internal/circulation');

        $response->assertStatus(301);
        $response->assertRedirect('/librarian/circulation');
    }

    public function test_internal_stewardship_redirects_to_librarian_data_cleanup(): void
    {
        $response = $this->get('/internal/stewardship');

        $response->assertStatus(301);
        $response->assertRedirect('/librarian/data-cleanup');
    }

    public function test_internal_review_remains_transitional(): void
    {
        // Guest still gated by library.auth.
        $response = $this->get('/internal/review');

        $response->assertStatus(302);
        $response->assertRedirectContains('/login');
    }

    public function test_internal_ai_chat_remains_transitional(): void
    {
        $response = $this->get('/internal/ai-chat');

        $response->assertStatus(302);
        $response->assertRedirectContains('/login');
    }
}
