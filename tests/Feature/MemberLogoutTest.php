<?php

namespace Tests\Feature;

use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class MemberLogoutTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        $this->withoutMiddleware([VerifyCsrfToken::class, ValidateCsrfToken::class]);
    }

    public function test_authenticated_member_post_logout_redirects_to_login(): void
    {
        $member = $this->makeControlPlaneUser('member', ['locale' => 'ru']);
        $this->signInToLibraryAs($member);

        $this->assertIsArray(session('library.user'));

        $response = $this->post('/logout', ['_token' => csrf_token()]);

        $response->assertStatus(302);
        $response->assertRedirect('/login');

        $this->assertNull(session('library.user'));
        $this->assertNull(session('library.crm_token'));
        $this->assertNull(session('library.authenticated_at'));
        $this->assertGuest();
    }

    public function test_logout_form_rendered_in_member_dashboard(): void
    {
        $member = $this->makeControlPlaneUser('member', ['locale' => 'ru']);
        $this->signInToLibraryAs($member);

        $response = $this->get('/dashboard?lang=ru');

        $response->assertOk();
        $response->assertSee('action="/logout"', false);
        $response->assertSee('Выйти', false);
    }

    public function test_after_logout_dashboard_requires_login_again(): void
    {
        $member = $this->makeControlPlaneUser('member', ['locale' => 'ru']);
        $this->signInToLibraryAs($member);
        $this->post('/logout', ['_token' => csrf_token()]);

        $response = $this->get('/dashboard');

        $response->assertStatus(302);
        $response->assertRedirectContains('/login');
    }
}
