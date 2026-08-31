<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class PublicErrorPagePrivacyTest extends TestCase
{
    public function test_public_error_pages_are_localized_and_never_render_internal_exception_details(): void
    {
        config(['app.debug' => false]);

        $secret = 'INTERNAL-QUALITY-GATE-HOST db-internal.example.test:5432';
        Route::middleware('web')->get('/_quality-gate/error/{status}', static function (int $status) use ($secret): never {
            abort($status, $secret);
        });

        foreach (['kk', 'ru', 'en'] as $locale) {
            foreach ([403, 404, 419, 422, 500] as $status) {
                $this->get("/_quality-gate/error/{$status}?lang={$locale}")
                    ->assertStatus($status)
                    ->assertSee(trans("errors.pages.{$status}.title", [], $locale))
                    ->assertDontSee($secret)
                    ->assertDontSee('db-internal.example.test')
                    ->assertDontSee('Stack trace')
                    ->assertDontSee('vendor/laravel');
            }
        }
    }
}
