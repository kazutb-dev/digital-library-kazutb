<?php

namespace Tests\Feature;

use Tests\TestCase;

class ShortlistPageTest extends TestCase
{
    public function test_shortlist_page_renders_the_research_shortlist_shell(): void
    {
        $response = $this->get('/shortlist');

        $response
            ->assertOk()
            ->assertSee('data-shortlist-page', false)
            ->assertSee('data-shortlist-hero', false)
            ->assertSee('data-shortlist-items', false)
            ->assertSee('data-shortlist-sidebar', false)
            ->assertSee('/api/v1/shortlist', false)
            ->assertSee('shortlist-loading', false)
            ->assertSee('shortlist-empty', false);
    }

    public function test_shortlist_page_preserves_export_actions_and_sections(): void
    {
        $response = $this->get('/shortlist');

        $response
            ->assertOk()
            ->assertSee('copyBibliography()', false)
            ->assertSee('clearShortlist()', false)
            ->assertSee('loadExport()', false)
            ->assertSee('loadDraftMeta()', false)
            ->assertSee('data-smart-export', false)
            ->assertSee('data-shortlist-bridge', false)
            ->assertSee('href="/catalog"', false)
            ->assertSee('href="/resources"', false)
            ->assertDontSee('href="/for-teachers"', false);
    }

    public function test_shortlist_page_has_export_controls_and_summary_cards(): void
    {
        $response = $this->get('/shortlist');

        $response
            ->assertOk()
            ->assertSee('bib-format', false)
            ->assertSee('numbered', false)
            ->assertSee('grouped', false)
            ->assertSee('syllabus', false)
            ->assertSee('bibliography-text', false)
            ->assertSee('shortlist-summary-total', false)
            ->assertSee('shortlist-summary-digital', false)
            ->assertSee('shortlist-summary-physical', false);
    }

    public function test_shortlist_page_shows_cabinet_link_when_authenticated(): void
    {
        $session = [
            'library.user' => [
                'id' => 'u1', 'name' => 'Test', 'email' => 'test@example.com',
                'login' => 'test', 'ad_login' => 'test', 'role' => 'reader',
            ],
        ];

        $response = $this->withSession($session)->get('/shortlist');

        $response
            ->assertOk()
            ->assertSee('href="/dashboard"', false);
    }
}
