<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class CorsPolicyTest extends TestCase
{
    public function test_private_api_does_not_allow_an_untrusted_origin(): void
    {
        $this->withHeader('Origin', 'https://evil.example')
            ->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertHeader('Access-Control-Allow-Origin', rtrim((string) config('app.url'), '/'));
    }

    public function test_application_origin_can_use_the_session_aware_api(): void
    {
        $origin = rtrim((string) config('app.url'), '/');

        $this->withHeader('Origin', $origin)
            ->getJson('/api/v1/me')
            ->assertUnauthorized()
            ->assertHeader('Access-Control-Allow-Origin', $origin)
            ->assertHeader('Access-Control-Allow-Credentials', 'true');
    }
}
