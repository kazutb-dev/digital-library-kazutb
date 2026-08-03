<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

class RequestContextMiddlewareTest extends TestCase
{
    public function test_request_and_correlation_ids_are_returned_without_exposing_an_exception(): void
    {
        config(['app.debug' => false]);
        Route::middleware('web')->get('/_runtime-test/failure', function (): never {
            throw new \RuntimeException('private-stack-message');
        })->name('runtime.test.failure');

        $response = $this->withHeaders([
            'X-Request-Id' => 'runtime-test-request',
            'X-Correlation-Id' => 'runtime-test-correlation',
        ])->get('/_runtime-test/failure');

        $response->assertStatus(500)
            ->assertHeader('X-Request-Id', 'runtime-test-request')
            ->assertHeader('X-Correlation-Id', 'runtime-test-correlation')
            ->assertDontSee('private-stack-message')
            ->assertDontSee('RuntimeException')
            ->assertDontSee('Stack trace');
    }

    public function test_invalid_external_request_id_is_replaced(): void
    {
        Route::middleware('web')->get('/_runtime-test/ok', fn () => response('ok'));

        $response = $this->withHeader('X-Request-Id', "unsafe\nheader")->get('/_runtime-test/ok');

        $response->assertOk();
        $this->assertMatchesRegularExpression('/^[0-9a-f-]{36}$/', (string) $response->headers->get('X-Request-Id'));
    }
}
