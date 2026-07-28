<?php

namespace Tests\Feature\Api;

use Tests\TestCase;

class AdminPrivilegeNegativePathTest extends TestCase
{
    /**
     * Helper to create authenticated session with a specific role
     */
    private function staffSession(string $role = 'admin'): array
    {
        return [
            'library.user' => [
                'id' => 'staff-' . $role,
                'name' => ucfirst($role) . ' User',
                'email' => $role . '@example.com',
                'role' => $role,
            ],
        ];
    }

    // --- Guest Access Tests ---

    public function test_guest_cannot_access_admin_overview(): void
    {
        $response = $this->get('/admin');

        $response->assertRedirect();
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
    }

    public function test_guest_cannot_access_admin_users(): void
    {
        $response = $this->get('/admin/users');

        $response->assertRedirect();
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
    }

    public function test_guest_cannot_access_admin_logs(): void
    {
        $response = $this->get('/admin/logs');

        $response->assertRedirect();
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
    }

    public function test_guest_cannot_access_admin_news(): void
    {
        $response = $this->get('/admin/news');

        $response->assertRedirect();
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
    }

    public function test_guest_cannot_access_admin_settings(): void
    {
        $response = $this->get('/admin/settings');

        $response->assertRedirect();
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
    }

    public function test_guest_cannot_access_admin_reports(): void
    {
        $response = $this->get('/admin/reports');

        $response->assertRedirect();
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
    }

    public function test_guest_cannot_access_admin_feedback(): void
    {
        $response = $this->get('/admin/feedback');

        $response->assertRedirect();
        $this->assertStringContainsString('/login', $response->headers->get('Location'));
    }

    // --- Non-Admin Authenticated Users Tests ---

    public function test_reader_cannot_access_admin_overview(): void
    {
        $response = $this->withSession($this->staffSession('reader'))->get('/admin');

        $response->assertForbidden();
    }

    public function test_reader_cannot_access_admin_users(): void
    {
        $response = $this->withSession($this->staffSession('reader'))->get('/admin/users');

        $response->assertForbidden();
    }

    public function test_reader_cannot_access_admin_logs(): void
    {
        $response = $this->withSession($this->staffSession('reader'))->get('/admin/logs');

        $response->assertForbidden();
    }

    public function test_reader_cannot_access_admin_news(): void
    {
        $response = $this->withSession($this->staffSession('reader'))->get('/admin/news');

        $response->assertForbidden();
    }

    public function test_reader_cannot_access_admin_settings(): void
    {
        $response = $this->withSession($this->staffSession('reader'))->get('/admin/settings');

        $response->assertForbidden();
    }

    public function test_reader_cannot_access_admin_reports(): void
    {
        $response = $this->withSession($this->staffSession('reader'))->get('/admin/reports');

        $response->assertForbidden();
    }

    public function test_reader_cannot_access_admin_feedback(): void
    {
        $response = $this->withSession($this->staffSession('reader'))->get('/admin/feedback');

        $response->assertForbidden();
    }

    // --- Librarian (non-admin staff) Privilege Escalation Tests ---

    public function test_librarian_cannot_access_admin_overview(): void
    {
        $response = $this->withSession($this->staffSession('librarian'))->get('/admin');

        $response->assertForbidden();
    }

    public function test_librarian_cannot_access_admin_users(): void
    {
        $response = $this->withSession($this->staffSession('librarian'))->get('/admin/users');

        $response->assertForbidden();
    }

    public function test_librarian_cannot_access_admin_logs(): void
    {
        $response = $this->withSession($this->staffSession('librarian'))->get('/admin/logs');

        $response->assertForbidden();
    }

    public function test_librarian_cannot_access_admin_news(): void
    {
        $response = $this->withSession($this->staffSession('librarian'))->get('/admin/news');

        $response->assertForbidden();
    }

    public function test_librarian_cannot_access_admin_settings(): void
    {
        $response = $this->withSession($this->staffSession('librarian'))->get('/admin/settings');

        $response->assertForbidden();
    }

    public function test_librarian_cannot_access_admin_reports(): void
    {
        $response = $this->withSession($this->staffSession('librarian'))->get('/admin/reports');

        $response->assertForbidden();
    }

    public function test_librarian_cannot_access_admin_feedback(): void
    {
        $response = $this->withSession($this->staffSession('librarian'))->get('/admin/feedback');

        $response->assertForbidden();
    }

    // --- Privilege Verification Tests ---

    public function test_admin_can_access_admin_overview(): void
    {
        $response = $this->withSession($this->staffSession('admin'))->get('/admin');

        $response->assertOk();
    }

    public function test_admin_can_access_admin_users(): void
    {
        $response = $this->withSession($this->staffSession('admin'))->get('/admin/users');

        $response->assertOk();
    }

    public function test_admin_can_access_admin_logs(): void
    {
        $response = $this->withSession($this->staffSession('admin'))->get('/admin/logs');

        $response->assertOk();
    }

    public function test_admin_can_access_admin_news(): void
    {
        $response = $this->withSession($this->staffSession('admin'))->get('/admin/news');

        $response->assertOk();
    }

    public function test_admin_can_access_admin_settings(): void
    {
        $response = $this->withSession($this->staffSession('admin'))->get('/admin/settings');

        $response->assertOk();
    }

    public function test_admin_can_access_admin_reports(): void
    {
        $response = $this->withSession($this->staffSession('admin'))->get('/admin/reports');

        $response->assertOk();
    }

    public function test_admin_can_access_admin_feedback(): void
    {
        $response = $this->withSession($this->staffSession('admin'))->get('/admin/feedback');

        $response->assertOk();
    }
}
