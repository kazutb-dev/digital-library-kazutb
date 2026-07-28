<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\TestCase;

class ShortlistExternalResourceTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_shortlist_accepts_external_resource_type(): void
    {
        $response = $this->withSession([])->postJson('/api/v1/shortlist', [
            'identifier' => 'ext:ipr-smart',
            'title' => 'IPR SMART',
            'type' => 'external_resource',
            'provider' => 'IPR Media',
            'url' => 'https://www.iprbookshop.ru/',
            'access_type' => 'remote_auth',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.type', 'external_resource')
            ->assertJsonPath('data.provider', 'IPR Media')
            ->assertJsonPath('data.url', 'https://www.iprbookshop.ru/')
            ->assertJsonPath('data.access_type', 'remote_auth');
    }

    public function test_shortlist_defaults_type_to_book(): void
    {
        $response = $this->withSession([])->postJson('/api/v1/shortlist', [
            'identifier' => '978-5-000-00000-0',
            'title' => 'Regular Book',
        ]);

        $response
            ->assertCreated()
            ->assertJsonPath('data.type', 'book');
    }

    public function test_shortlist_rejects_invalid_type(): void
    {
        $response = $this->withSession([])->postJson('/api/v1/shortlist', [
            'identifier' => 'test',
            'title' => 'Test',
            'type' => 'invalid_type',
        ]);

        $response->assertUnprocessable();
    }

    public function test_shortlist_rejects_invalid_url(): void
    {
        $response = $this->withSession([])->postJson('/api/v1/shortlist', [
            'identifier' => 'test',
            'title' => 'Test',
            'url' => 'not-a-url',
        ]);

        $response->assertUnprocessable();
    }

    public function test_shortlist_external_and_book_coexist(): void
    {
        $session = [
            'library.shortlist' => [
                'isbn-1' => [
                    'identifier' => 'isbn-1',
                    'title' => 'Regular Book',
                    'type' => 'book',
                    'addedAt' => '2024-01-01T00:00:00Z',
                ],
                'ext:ipr-smart' => [
                    'identifier' => 'ext:ipr-smart',
                    'title' => 'IPR SMART',
                    'type' => 'external_resource',
                    'provider' => 'IPR Media',
                    'addedAt' => '2024-01-01T00:00:00Z',
                ],
            ],
        ];

        $response = $this->withSession($session)->getJson('/api/v1/shortlist');

        $response
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $types = collect($response->json('data'))->pluck('type')->all();
        $this->assertContains('book', $types);
        $this->assertContains('external_resource', $types);
    }

    public function test_shortlist_prevents_duplicate_external_resource(): void
    {
        $session = [
            'library.shortlist' => [
                'ext:ipr-smart' => [
                    'identifier' => 'ext:ipr-smart',
                    'title' => 'IPR SMART',
                    'type' => 'external_resource',
                    'addedAt' => '2024-01-01T00:00:00Z',
                ],
            ],
        ];

        $response = $this->withSession($session)->postJson('/api/v1/shortlist', [
            'identifier' => 'ext:ipr-smart',
            'title' => 'IPR SMART',
            'type' => 'external_resource',
        ]);

        $response
            ->assertStatus(409)
            ->assertJsonPath('duplicate', true);
    }

    public function test_shortlist_remove_external_resource(): void
    {
        $session = [
            'library.shortlist' => [
                'ext:ipr-smart' => [
                    'identifier' => 'ext:ipr-smart',
                    'title' => 'IPR SMART',
                    'type' => 'external_resource',
                    'addedAt' => '2024-01-01T00:00:00Z',
                ],
            ],
        ];

        $response = $this->withSession($session)->deleteJson('/api/v1/shortlist/' . urlencode('ext:ipr-smart'));

        $response
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }
}
