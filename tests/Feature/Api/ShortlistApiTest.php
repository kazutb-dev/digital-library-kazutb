<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\TestCase;

class ShortlistApiTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }
    public function test_shortlist_list_returns_empty_initially(): void
    {
        $response = $this->withSession([])->getJson('/api/v1/shortlist');

        $response
            ->assertOk()
            ->assertJson([
                'data' => [],
                'meta' => ['total' => 0],
            ]);
    }

    public function test_shortlist_add_item(): void
    {
        $response = $this->withSession([])->postJson('/api/v1/shortlist', [
            'identifier' => '978-5-358-09150-5',
            'title' => 'Test Book Title',
            'author' => 'Test Author',
            'publisher' => 'Test Publisher',
            'year' => '2024',
            'language' => 'ru',
            'isbn' => '978-5-358-09150-5',
        ]);

        $response
            ->assertCreated()
            ->assertJsonStructure([
                'message',
                'data' => ['identifier', 'title', 'author', 'publisher', 'year', 'addedAt'],
                'meta' => ['total'],
            ])
            ->assertJsonPath('data.identifier', '978-5-358-09150-5')
            ->assertJsonPath('meta.total', 1);
    }

    public function test_shortlist_prevents_duplicate_entries(): void
    {
        $session = [
            'library.shortlist' => [
                '978-5-358-09150-5' => [
                    'identifier' => '978-5-358-09150-5',
                    'title' => 'Existing Book',
                    'author' => 'Author',
                    'addedAt' => '2024-01-01T00:00:00Z',
                ],
            ],
        ];

        $response = $this->withSession($session)->postJson('/api/v1/shortlist', [
            'identifier' => '978-5-358-09150-5',
            'title' => 'Existing Book',
        ]);

        $response
            ->assertStatus(409)
            ->assertJsonPath('duplicate', true);
    }

    public function test_shortlist_remove_item(): void
    {
        $session = [
            'library.shortlist' => [
                'test-isbn' => [
                    'identifier' => 'test-isbn',
                    'title' => 'Book to Remove',
                    'addedAt' => '2024-01-01T00:00:00Z',
                ],
            ],
        ];

        $response = $this->withSession($session)->deleteJson('/api/v1/shortlist/test-isbn');

        $response
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_shortlist_remove_nonexistent_returns_404(): void
    {
        $response = $this->withSession([])->deleteJson('/api/v1/shortlist/nonexistent');

        $response->assertNotFound();
    }

    public function test_shortlist_clear_removes_all_items(): void
    {
        $session = [
            'library.shortlist' => [
                'isbn-1' => ['identifier' => 'isbn-1', 'title' => 'Book 1', 'addedAt' => '2024-01-01T00:00:00Z'],
                'isbn-2' => ['identifier' => 'isbn-2', 'title' => 'Book 2', 'addedAt' => '2024-01-01T00:00:00Z'],
            ],
        ];

        $response = $this->withSession($session)->postJson('/api/v1/shortlist/clear');

        $response
            ->assertOk()
            ->assertJsonPath('meta.total', 0);
    }

    public function test_shortlist_check_returns_status_for_identifiers(): void
    {
        $session = [
            'library.shortlist' => [
                'isbn-1' => ['identifier' => 'isbn-1', 'title' => 'Book 1', 'addedAt' => '2024-01-01T00:00:00Z'],
            ],
        ];

        $response = $this->withSession($session)->postJson('/api/v1/shortlist/check', [
            'identifiers' => ['isbn-1', 'isbn-2', 'isbn-3'],
        ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.isbn-1', true)
            ->assertJsonPath('data.isbn-2', false)
            ->assertJsonPath('data.isbn-3', false);
    }

    public function test_shortlist_add_validates_required_fields(): void
    {
        $response = $this->withSession([])->postJson('/api/v1/shortlist', []);

        $response->assertUnprocessable();
    }

    public function test_shortlist_list_returns_items_after_add(): void
    {
        $session = [
            'library.shortlist' => [
                'isbn-a' => [
                    'identifier' => 'isbn-a',
                    'title' => 'Alpha Book',
                    'author' => 'Author A',
                    'publisher' => 'Pub A',
                    'year' => '2023',
                    'language' => 'en',
                    'isbn' => 'isbn-a',
                    'addedAt' => '2024-01-01T00:00:00Z',
                ],
                'isbn-b' => [
                    'identifier' => 'isbn-b',
                    'title' => 'Beta Book',
                    'author' => 'Author B',
                    'addedAt' => '2024-01-02T00:00:00Z',
                ],
            ],
        ];

        $response = $this->withSession($session)->getJson('/api/v1/shortlist');

        $response
            ->assertOk()
            ->assertJsonPath('meta.total', 2)
            ->assertJsonCount(2, 'data');
    }

    public function test_shortlist_check_validates_identifiers_required(): void
    {
        $response = $this->withSession([])->postJson('/api/v1/shortlist/check', []);

        $response->assertUnprocessable();
    }
}
