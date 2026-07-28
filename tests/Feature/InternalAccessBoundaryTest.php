<?php

namespace Tests\Feature;

use Tests\TestCase;

class InternalAccessBoundaryTest extends TestCase
{
    private function staffSession(string $role = 'librarian'): array
    {
        return [
            'library.user' => [
                'id' => 'staff-1',
                'name' => 'Library Staff',
                'email' => 'staff@example.com',
                'login' => 'staff01',
                'ad_login' => 'staff01',
                'role' => $role,
            ],
            'library.crm_token' => 'test-staff-token',
            'library.authenticated_at' => now()->toIso8601String(),
        ];
    }

    public function test_internal_pages_reject_guests(): void
    {
        foreach ([
            '/internal/dashboard',
            '/internal/review',
            '/internal/stewardship',
            '/internal/circulation',
            '/internal/ai-chat',
        ] as $uri) {
            $this->get($uri)->assertForbidden();
        }
    }

    public function test_internal_pages_allow_librarian_sessions(): void
    {
        foreach ([
            '/internal/dashboard',
            '/internal/review',
            '/internal/stewardship',
            '/internal/circulation',
            '/internal/ai-chat',
        ] as $uri) {
            $this->withSession($this->staffSession('librarian'))->get($uri)->assertOk();
        }
    }

    public function test_internal_pages_allow_admin_sessions(): void
    {
        foreach ([
            '/internal/dashboard',
            '/internal/review',
            '/internal/stewardship',
            '/internal/circulation',
            '/internal/ai-chat',
        ] as $uri) {
            $this->withSession($this->staffSession('admin'))->get($uri)->assertOk();
        }
    }

    public function test_internal_pages_reject_authenticated_sessions_without_staff_role(): void
    {
        foreach ([
            '/internal/dashboard',
            '/internal/review',
            '/internal/stewardship',
            '/internal/circulation',
            '/internal/ai-chat',
        ] as $uri) {
            $this->withSession($this->staffSession(''))->get($uri)->assertForbidden();
        }
    }

    public function test_internal_pages_reject_reader_sessions(): void
    {
        foreach ([
            '/internal/dashboard',
            '/internal/review',
            '/internal/stewardship',
            '/internal/circulation',
            '/internal/ai-chat',
        ] as $uri) {
            $this->withSession($this->staffSession('reader'))->get($uri)->assertForbidden();
        }
    }
}
