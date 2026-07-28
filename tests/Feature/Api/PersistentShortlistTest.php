<?php

namespace Tests\Feature\Api;

use App\Models\LiteratureDraft;
use App\Models\LiteratureDraftItem;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PersistentShortlistTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    private function authSession(string $userId = 'u-test-1', string $role = 'reader'): array
    {
        return [
            'library.user' => [
                'id' => $userId,
                'name' => 'Test User',
                'email' => 'test@example.com',
                'login' => 'testuser',
                'ad_login' => 'testuser',
                'role' => $role,
            ],
            'library.crm_token' => 'test-token',
            'library.authenticated_at' => now()->toIso8601String(),
        ];
    }

    private function bookPayload(string $identifier = 'isbn-001', string $title = 'Test Book'): array
    {
        return [
            'identifier' => $identifier,
            'title' => $title,
            'type' => 'book',
            'author' => 'Author A',
            'publisher' => 'Publisher X',
            'year' => '2024',
            'isbn' => $identifier,
        ];
    }

    // ── Guest: session-based (no regression) ──────────────────────

    public function test_guest_add_uses_session_storage(): void
    {
        $response = $this->withSession([])
            ->postJson('/api/v1/shortlist', $this->bookPayload());

        $response->assertCreated()
            ->assertJsonPath('data.identifier', 'isbn-001');

        $list = $this->withSession($response->headers->getCookies()[0]->getValue() ? [] : [])
            ->getJson('/api/v1/shortlist');

        // Guest items survive in session but no DB record
        $this->assertDatabaseMissing('literature_drafts', ['user_id' => 'guest']);
    }

    public function test_guest_shortlist_operations_work(): void
    {
        $session = $this->withSession([]);

        $session->postJson('/api/v1/shortlist', $this->bookPayload('isbn-001', 'Book 1'))
            ->assertCreated();

        $session->postJson('/api/v1/shortlist', $this->bookPayload('isbn-002', 'Book 2'))
            ->assertCreated();

        $session->getJson('/api/v1/shortlist')
            ->assertOk()
            ->assertJsonPath('meta.total', 2);

        $session->deleteJson('/api/v1/shortlist/isbn-001')
            ->assertOk();

        $session->getJson('/api/v1/shortlist')
            ->assertOk()
            ->assertJsonPath('meta.total', 1);
    }

    // ── Authenticated: persistent DB storage ──────────────────────

    public function test_authenticated_add_creates_db_record(): void
    {
        $response = $this->withSession($this->authSession())
            ->postJson('/api/v1/shortlist', $this->bookPayload());

        $response->assertCreated()
            ->assertJsonPath('data.identifier', 'isbn-001');

        $this->assertDatabaseHas('literature_drafts', ['user_id' => 'u-test-1']);
        $this->assertDatabaseHas('literature_draft_items', [
            'identifier' => 'isbn-001',
            'title' => 'Test Book',
            'type' => 'book',
        ]);
    }

    public function test_authenticated_items_persist_across_sessions(): void
    {
        // Session 1: add items
        $this->withSession($this->authSession())
            ->postJson('/api/v1/shortlist', $this->bookPayload('isbn-001', 'Book 1'))
            ->assertCreated();

        $this->withSession($this->authSession())
            ->postJson('/api/v1/shortlist', $this->bookPayload('isbn-002', 'Book 2'))
            ->assertCreated();

        // Session 2: new session, same user — items should still be there
        $response = $this->withSession($this->authSession())
            ->getJson('/api/v1/shortlist');

        $response->assertOk()
            ->assertJsonPath('meta.total', 2);

        $identifiers = collect($response->json('data'))->pluck('identifier')->sort()->values()->all();
        $this->assertEquals(['isbn-001', 'isbn-002'], $identifiers);
    }

    public function test_authenticated_remove_deletes_from_db(): void
    {
        $session = $this->withSession($this->authSession());

        $session->postJson('/api/v1/shortlist', $this->bookPayload())
            ->assertCreated();

        $session->deleteJson('/api/v1/shortlist/isbn-001')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->assertDatabaseMissing('literature_draft_items', ['identifier' => 'isbn-001']);
    }

    public function test_authenticated_clear_removes_all_items(): void
    {
        $session = $this->withSession($this->authSession());

        $session->postJson('/api/v1/shortlist', $this->bookPayload('isbn-001', 'Book 1'));
        $session->postJson('/api/v1/shortlist', $this->bookPayload('isbn-002', 'Book 2'));

        $session->postJson('/api/v1/shortlist/clear')
            ->assertOk()
            ->assertJsonPath('meta.total', 0);

        $this->assertDatabaseHas('literature_drafts', ['user_id' => 'u-test-1']);
        $this->assertDatabaseCount('literature_draft_items', 0);
    }

    // ── Duplicate prevention ──────────────────────────────────────

    public function test_authenticated_duplicate_returns_409(): void
    {
        $session = $this->withSession($this->authSession());

        $session->postJson('/api/v1/shortlist', $this->bookPayload())
            ->assertCreated();

        $session->postJson('/api/v1/shortlist', $this->bookPayload())
            ->assertStatus(409)
            ->assertJsonPath('duplicate', true);

        $this->assertDatabaseCount('literature_draft_items', 1);
    }

    // ── Mixed items (book + external) ─────────────────────────────

    public function test_authenticated_supports_mixed_item_types(): void
    {
        $session = $this->withSession($this->authSession());

        $session->postJson('/api/v1/shortlist', $this->bookPayload())
            ->assertCreated();

        $session->postJson('/api/v1/shortlist', [
            'identifier' => 'ext-scopus',
            'title' => 'Scopus Database',
            'type' => 'external_resource',
            'url' => 'https://www.scopus.com',
            'provider' => 'Elsevier',
            'access_type' => 'subscription',
        ])->assertCreated();

        $response = $session->getJson('/api/v1/shortlist/summary');
        $response->assertOk()
            ->assertJsonPath('data.total', 2)
            ->assertJsonPath('data.books', 1)
            ->assertJsonPath('data.external', 1);
    }

    // ── Draft metadata persistence ────────────────────────────────

    public function test_authenticated_draft_meta_persists(): void
    {
        $this->withSession($this->authSession())
            ->patchJson('/api/v1/shortlist/draft', [
                'title' => 'Информатика 2025',
                'notes' => 'Для 1 курса',
            ])
            ->assertOk()
            ->assertJsonPath('data.persistent', true)
            ->assertJsonPath('data.title', 'Информатика 2025');

        $this->assertDatabaseHas('literature_drafts', [
            'user_id' => 'u-test-1',
            'title' => 'Информатика 2025',
            'notes' => 'Для 1 курса',
        ]);

        // New session, same user: draft meta survives
        $response = $this->withSession($this->authSession())
            ->getJson('/api/v1/shortlist/summary');

        $response->assertOk()
            ->assertJsonPath('data.draft.title', 'Информатика 2025')
            ->assertJsonPath('data.draft.persistent', true);
    }

    // ── Session → DB migration ────────────────────────────────────

    public function test_session_items_migrate_to_db_on_auth(): void
    {
        $sessionWithItems = array_merge($this->authSession(), [
            'library.shortlist' => [
                'isbn-session-1' => [
                    'identifier' => 'isbn-session-1',
                    'title' => 'Session Book 1',
                    'type' => 'book',
                    'author' => 'Session Author',
                    'addedAt' => '2025-01-10T09:00:00+00:00',
                ],
                'isbn-session-2' => [
                    'identifier' => 'isbn-session-2',
                    'title' => 'Session Book 2',
                    'type' => 'book',
                    'addedAt' => '2025-01-11T10:00:00+00:00',
                ],
            ],
        ]);

        $response = $this->withSession($sessionWithItems)
            ->getJson('/api/v1/shortlist');

        $response->assertOk()
            ->assertJsonPath('meta.total', 2);

        $this->assertDatabaseHas('literature_draft_items', ['identifier' => 'isbn-session-1']);
        $this->assertDatabaseHas('literature_draft_items', ['identifier' => 'isbn-session-2']);
    }

    public function test_session_draft_meta_migrates_to_db(): void
    {
        $sessionWithDraft = array_merge($this->authSession(), [
            'library.shortlist_draft' => [
                'title' => 'Session Draft Title',
                'notes' => 'Session Notes',
                'updatedAt' => '2025-01-15T08:00:00+00:00',
            ],
        ]);

        $this->withSession($sessionWithDraft)
            ->getJson('/api/v1/shortlist/summary')
            ->assertOk()
            ->assertJsonPath('data.draft.title', 'Session Draft Title')
            ->assertJsonPath('data.draft.notes', 'Session Notes')
            ->assertJsonPath('data.draft.persistent', true);

        $this->assertDatabaseHas('literature_drafts', [
            'user_id' => 'u-test-1',
            'title' => 'Session Draft Title',
            'notes' => 'Session Notes',
        ]);
    }

    public function test_migration_deduplicates_existing_db_items(): void
    {
        // Pre-existing DB item
        $draft = LiteratureDraft::create([
            'user_id' => 'u-test-1',
            'title' => null,
            'notes' => null,
        ]);
        $draft->items()->create([
            'identifier' => 'isbn-existing',
            'title' => 'Existing DB Book',
            'type' => 'book',
            'added_at' => now(),
        ]);

        // Session has same item plus new one
        $sessionWithItems = array_merge($this->authSession(), [
            'library.shortlist' => [
                'isbn-existing' => [
                    'identifier' => 'isbn-existing',
                    'title' => 'Existing DB Book (session copy)',
                    'type' => 'book',
                    'addedAt' => '2025-01-01T00:00:00+00:00',
                ],
                'isbn-new' => [
                    'identifier' => 'isbn-new',
                    'title' => 'New Session Book',
                    'type' => 'book',
                    'addedAt' => '2025-01-02T00:00:00+00:00',
                ],
            ],
        ]);

        $response = $this->withSession($sessionWithItems)
            ->getJson('/api/v1/shortlist');

        $response->assertOk()
            ->assertJsonPath('meta.total', 2);

        // Original DB item title preserved (not overwritten by session copy)
        $this->assertDatabaseHas('literature_draft_items', [
            'identifier' => 'isbn-existing',
            'title' => 'Existing DB Book',
        ]);

        $this->assertDatabaseHas('literature_draft_items', [
            'identifier' => 'isbn-new',
            'title' => 'New Session Book',
        ]);

        // Only 2 items total (no duplicate)
        $this->assertDatabaseCount('literature_draft_items', 2);
    }

    public function test_migration_preserves_db_draft_meta_over_session(): void
    {
        // Pre-existing DB draft with metadata
        LiteratureDraft::create([
            'user_id' => 'u-test-1',
            'title' => 'DB Title',
            'notes' => 'DB Notes',
        ]);

        $sessionWithDraft = array_merge($this->authSession(), [
            'library.shortlist_draft' => [
                'title' => 'Session Title (should be ignored)',
                'notes' => 'Session Notes (should be ignored)',
            ],
        ]);

        $response = $this->withSession($sessionWithDraft)
            ->getJson('/api/v1/shortlist/summary');

        $response->assertOk()
            ->assertJsonPath('data.draft.title', 'DB Title')
            ->assertJsonPath('data.draft.notes', 'DB Notes');
    }

    // ── Export with persistent items ──────────────────────────────

    public function test_export_works_with_persistent_items(): void
    {
        $session = $this->withSession($this->authSession());

        $session->postJson('/api/v1/shortlist', [
            'identifier' => 'isbn-111',
            'title' => 'Алгоритмы и структуры данных',
            'type' => 'book',
            'author' => 'Кнут Д.',
            'publisher' => 'Вильямс',
            'year' => '2020',
            'isbn' => '978-5-111-11111-1',
        ])->assertCreated();

        $response = $session->getJson('/api/v1/shortlist/export?format=numbered');

        $response->assertOk()
            ->assertJsonPath('meta.total', 1);

        $this->assertStringContainsString('Алгоритмы', $response->json('data.text'));
    }

    // ── Check identifiers with persistent items ───────────────────

    public function test_check_works_with_persistent_items(): void
    {
        $session = $this->withSession($this->authSession());

        $session->postJson('/api/v1/shortlist', $this->bookPayload('isbn-001', 'Book 1'));

        $response = $session->postJson('/api/v1/shortlist/check', [
            'identifiers' => ['isbn-001', 'isbn-999'],
        ]);

        $response->assertOk()
            ->assertJsonPath('data.isbn-001', true)
            ->assertJsonPath('data.isbn-999', false);
    }

    // ── Different users see different drafts ───────────────────────

    public function test_different_users_have_separate_drafts(): void
    {
        $this->withSession($this->authSession('user-a'))
            ->postJson('/api/v1/shortlist', $this->bookPayload('isbn-A', 'Book A'))
            ->assertCreated();

        $this->withSession($this->authSession('user-b'))
            ->postJson('/api/v1/shortlist', $this->bookPayload('isbn-B', 'Book B'))
            ->assertCreated();

        $responseA = $this->withSession($this->authSession('user-a'))
            ->getJson('/api/v1/shortlist');
        $responseA->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertEquals('isbn-A', $responseA->json('data.0.identifier'));

        $responseB = $this->withSession($this->authSession('user-b'))
            ->getJson('/api/v1/shortlist');
        $responseB->assertOk()->assertJsonPath('meta.total', 1);
        $this->assertEquals('isbn-B', $responseB->json('data.0.identifier'));
    }
}
