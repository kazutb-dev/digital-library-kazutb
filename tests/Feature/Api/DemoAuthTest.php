<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class DemoAuthTest extends TestCase
{
    public function test_demo_auth_api_endpoints_do_not_exist_even_when_legacy_flags_are_enabled(): void
    {
        config(['demo_auth.enabled' => true, 'demo_users.enabled' => true]);

        $this->getJson('/api/demo-auth/identities')->assertNotFound();
        $this->withoutMiddleware(PreventRequestForgery::class)
            ->postJson('/api/demo-auth/login', ['role' => 'director'])
            ->assertStatus(405);
    }

    public function test_demo_web_login_endpoint_does_not_exist(): void
    {
        config(['demo_users.enabled' => true]);

        $this->withoutMiddleware(PreventRequestForgery::class)
            ->post('/login/demo/director')
            ->assertStatus(405);
    }

    public function test_login_page_is_active_directory_only_in_every_locale(): void
    {
        config(['demo_auth.enabled' => true, 'demo_users.enabled' => true]);

        $expectedCopy = [
            'ru' => 'Введите корпоративный логин и пароль университета, чтобы продолжить.',
            'kk' => 'Жалғастыру үшін университеттің корпоративтік логині мен құпиясөзін енгізіңіз.',
            'en' => 'Enter your university login and password to continue.',
        ];

        foreach ($expectedCopy as $locale => $copy) {
            $response = $this->get('/login?lang='.$locale);

            $response
                ->assertOk()
                ->assertSee($copy)
                ->assertSee('id="login-form"', false)
                ->assertSee('method="POST"', false)
                ->assertDontSee('demo-login-block', false)
                ->assertDontSee('data-demo-slug', false)
                ->assertDontSee('/login/demo/', false)
                ->assertDontSee('/api/demo-auth/', false);
        }
    }

    public function test_login_page_is_not_cached_with_a_stale_csrf_token(): void
    {
        $response = $this->get('/login')->assertOk();

        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
    }

    public function test_real_login_route_remains_available(): void
    {
        $route = collect(Route::getRoutes()->getRoutes())
            ->first(fn ($route) => $route->uri() === 'api/login' && in_array('POST', $route->methods(), true));

        $this->assertNotNull($route);
        $this->assertSame('App\\Http\\Controllers\\Api\\AuthController@login', $route->getActionName());
    }
}
