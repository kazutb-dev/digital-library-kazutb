<?php

namespace Tests\Feature;

use Tests\TestCase;

class InternalAccessBoundaryTest extends TestCase
{
    public function test_retired_internal_pages_redirect_to_canonical_librarian_workspaces(): void
    {
        foreach ([
            '/internal/dashboard' => '/librarian',
            '/internal/circulation' => '/librarian/circulation',
            '/internal/stewardship' => '/librarian/data-cleanup',
            '/internal/review' => '/librarian/data-cleanup',
        ] as $legacy => $canonical) {
            $this->get($legacy)->assertStatus(301)->assertRedirect($canonical);
        }
    }

    public function test_remaining_internal_prototypes_reject_guests(): void
    {
        foreach (['/internal/ai-chat'] as $uri) {
            $response = $this->get($uri)->assertRedirect();
            $this->assertStringContainsString('/login?redirect=', (string) $response->headers->get('Location'));
        }
    }

    public function test_a_forged_legacy_staff_session_cannot_cross_the_permission_boundary(): void
    {
        $session = ['library.user' => [
            'id' => 'not-a-real-user',
            'name' => 'Forged Staff',
            'email' => 'forged@example.test',
            'login' => 'forged',
            'role' => 'librarian',
        ]];

        foreach (['/internal/ai-chat'] as $uri) {
            $this->withSession($session)->get($uri)->assertForbidden();
        }
    }

    public function test_canonical_librarian_workspace_rejects_guests(): void
    {
        $response = $this->get('/librarian')->assertRedirect();
        $this->assertStringContainsString('/login?redirect=', (string) $response->headers->get('Location'));
    }
}
