<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class ShortlistSummaryTest extends TestCase
{
    public function test_summary_returns_zero_counts_when_empty(): void
    {
        $response = $this->withSession([])->getJson('/api/v1/shortlist/summary');

        $response
            ->assertOk()
            ->assertJsonPath('data.total', 0)
            ->assertJsonPath('data.books', 0)
            ->assertJsonPath('data.external', 0)
            ->assertJsonPath('data.lastAddedAt', null)
            ->assertJsonPath('data.draft.title', null)
            ->assertJsonPath('data.draft.notes', null);
    }

    public function test_summary_counts_books_and_external_resources(): void
    {
        $shortlist = [
            'isbn-111' => [
                'identifier' => 'isbn-111',
                'title' => 'Алгоритмы',
                'type' => 'book',
                'addedAt' => '2025-01-10T09:00:00+00:00',
            ],
            'isbn-222' => [
                'identifier' => 'isbn-222',
                'title' => 'Базы данных',
                'type' => 'book',
                'addedAt' => '2025-01-11T10:00:00+00:00',
            ],
            'ext-1' => [
                'identifier' => 'ext-1',
                'title' => 'Scopus',
                'type' => 'external_resource',
                'addedAt' => '2025-01-12T11:00:00+00:00',
            ],
        ];

        $response = $this->withSession(['library.shortlist' => $shortlist])
            ->getJson('/api/v1/shortlist/summary');

        $response
            ->assertOk()
            ->assertJsonPath('data.total', 3)
            ->assertJsonPath('data.books', 2)
            ->assertJsonPath('data.external', 1)
            ->assertJsonPath('data.lastAddedAt', '2025-01-12T11:00:00+00:00');
    }

    public function test_summary_includes_draft_metadata(): void
    {
        $draft = [
            'title' => 'Информатика 2025',
            'notes' => 'Для 1 курса',
            'updatedAt' => '2025-01-15T08:00:00+00:00',
        ];

        $response = $this->withSession([
            'library.shortlist' => [],
            'library.shortlist_draft' => $draft,
        ])->getJson('/api/v1/shortlist/summary');

        $response
            ->assertOk()
            ->assertJsonPath('data.draft.title', 'Информатика 2025')
            ->assertJsonPath('data.draft.notes', 'Для 1 курса');
    }
}
