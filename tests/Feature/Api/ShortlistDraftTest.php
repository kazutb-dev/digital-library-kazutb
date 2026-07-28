<?php

namespace Tests\Feature\Api;

use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\TestCase;

class ShortlistDraftTest extends TestCase
{
    public function test_draft_update_stores_title_and_notes(): void
    {
        $response = $this->withoutMiddleware(PreventRequestForgery::class)
            ->withSession([])
            ->patchJson('/api/v1/shortlist/draft', [
                'title' => 'Физика — весна 2025',
                'notes' => 'Группы ИТ-201, ИТ-202',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.title', 'Физика — весна 2025')
            ->assertJsonPath('data.notes', 'Группы ИТ-201, ИТ-202')
            ->assertJsonPath('message', 'Данные черновика обновлены.');
    }

    public function test_draft_update_preserves_existing_fields(): void
    {
        $draft = [
            'title' => 'Existing Title',
            'notes' => 'Existing Notes',
            'updatedAt' => '2025-01-01T00:00:00+00:00',
        ];

        $response = $this->withoutMiddleware(PreventRequestForgery::class)
            ->withSession(['library.shortlist_draft' => $draft])
            ->patchJson('/api/v1/shortlist/draft', [
                'title' => 'New Title',
            ]);

        $response
            ->assertOk()
            ->assertJsonPath('data.title', 'New Title')
            ->assertJsonPath('data.notes', 'Existing Notes');
    }

    public function test_draft_title_validates_max_length(): void
    {
        $response = $this->withoutMiddleware(PreventRequestForgery::class)
            ->withSession([])
            ->patchJson('/api/v1/shortlist/draft', [
                'title' => str_repeat('x', 501),
            ]);

        $response->assertUnprocessable();
    }

    public function test_draft_notes_validates_max_length(): void
    {
        $response = $this->withoutMiddleware(PreventRequestForgery::class)
            ->withSession([])
            ->patchJson('/api/v1/shortlist/draft', [
                'notes' => str_repeat('x', 2001),
            ]);

        $response->assertUnprocessable();
    }

    public function test_draft_update_sets_updated_at(): void
    {
        $response = $this->withoutMiddleware(PreventRequestForgery::class)
            ->withSession([])
            ->patchJson('/api/v1/shortlist/draft', [
                'title' => 'Test',
            ]);

        $response
            ->assertOk()
            ->assertJsonStructure(['data' => ['updatedAt']]);

        $this->assertNotNull($response->json('data.updatedAt'));
    }

    public function test_draft_visible_in_summary_after_update(): void
    {
        $session = $this->withoutMiddleware(PreventRequestForgery::class)
            ->withSession([]);

        $session->patchJson('/api/v1/shortlist/draft', [
            'title' => 'Математика',
            'notes' => 'Семестр 2',
        ])->assertOk();

        $summary = $session->getJson('/api/v1/shortlist/summary');

        $summary
            ->assertOk()
            ->assertJsonPath('data.draft.title', 'Математика')
            ->assertJsonPath('data.draft.notes', 'Семестр 2');
    }
}
