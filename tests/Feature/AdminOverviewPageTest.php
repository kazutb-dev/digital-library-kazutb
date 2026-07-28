<?php

namespace Tests\Feature;

use Tests\TestCase;

class AdminOverviewPageTest extends TestCase
{
    private function staffSession(string $role = 'admin'): array
    {
        return [
            'library.user' => [
                'id' => 'admin-1',
                'name' => 'Demo Admin',
                'email' => 'admin@example.com',
                'login' => 'admin01',
                'ad_login' => 'admin01',
                'role' => $role,
            ],
            'library.crm_token' => 'test-admin-token',
            'library.authenticated_at' => now()->toIso8601String(),
        ];
    }

    public function test_admin_overview_redirects_guests_to_login(): void
    {
        $this->get('/admin')->assertRedirect('/login?redirect=%2Fadmin');
    }

    public function test_admin_routes_reject_non_admin_staff_and_readers(): void
    {
        foreach (['librarian', 'reader', ''] as $role) {
            foreach (['/admin', '/admin/users', '/admin/logs', '/admin/news', '/admin/feedback', '/admin/settings', '/admin/reports'] as $uri) {
                $this->withSession($this->staffSession($role))->get($uri)->assertForbidden();
            }
        }
    }

    public function test_admin_overview_renders_for_admin_session(): void
    {
        $response = $this->withSession($this->staffSession('admin'))->get('/admin');

        $response->assertOk()
            ->assertSee('Admin Portal', false)
            ->assertSee('Governance Overview', false)
            ->assertSee('Platform Health', false)
            ->assertSee('Governance Queue', false)
            ->assertSee('User &amp; Role Management', false)
            ->assertSee('Reports &amp; Analytics', false);
    }

    public function test_admin_follow_on_placeholders_render_for_admin_session(): void
    {
        // All previously-planned admin pages are now fully implemented.
        // This test intentionally has no placeholder URIs to assert against.
        $this->assertTrue(true);
    }

    public function test_admin_reports_page_renders_analytics_surface(): void
    {
        $response = $this->withSession($this->staffSession('admin'))->get('/admin/reports');

        $response->assertOk()
            ->assertSee('Reports &amp; Analytics', false)
            ->assertSee('Total Circulation', false)
            ->assertSee('Active Digital Patrons', false)
            ->assertSee('Acquisition Fund Utilization', false)
            ->assertSee('Controlled Digital Views', false)
            ->assertSee('Circulation Trends', false)
            ->assertSee('Resource Allocation', false)
            ->assertSee('Licensed Digital Journals', false)
            ->assertSee('Rare &amp; Special Collections', false)
            ->assertSee("Curator's Insight", false)
            ->assertSee('Circulation by Branch', false)
            ->assertSee('Main Library — Astana Campus', false)
            ->assertSee('Engineering Reading Hall', false)
            ->assertSee('Top Performing Collections', false)
            ->assertSee('Central Asian Studies Archive', false)
            ->assertSee('School of Engineering', false)
            ->assertSee('Scheduled Report Dispatches', false)
            ->assertSee('Monthly Circulation Summary', false)
            ->assertSee('Data Readiness', false)
            ->assertDontSee('Phase 3 analytical surface', false);
    }

    public function test_admin_settings_page_renders_configuration_surface(): void
    {
        $response = $this->withSession($this->staffSession('admin'))->get('/admin/settings');

        $response->assertOk()
            ->assertSee('System Settings', false)
            ->assertSee('Integrations', false)
            ->assertSee('Institutional Structure', false)
            ->assertSee('Authentication', false)
            ->assertSee('Global Preferences', false)
            ->assertSee('CRM Authentication', false)
            ->assertSee('Research Repositories', false)
            ->assertSee('JSTOR Data API', false)
            ->assertSee('EBSCOhost Integration', false)
            ->assertSee('KazNEB National Repository', false)
            ->assertSee('Main Library — Astana Campus', false)
            ->assertSee('Rare Collections Vault C', false)
            ->assertSee('System Health', false)
            ->assertSee('Operational Policies', false)
            ->assertSee('Controlled Digital Viewer', false)
            ->assertSee('Maintenance', false)
            ->assertSee('Rebuild Search Index', false)
            ->assertSee('Clear Global Cache', false)
            ->assertDontSee('Phase 2 management surface', false);
    }

    public function test_admin_news_page_renders_editorial_management_surface(): void
    {
        $response = $this->withSession($this->staffSession('admin'))->get('/admin/news');

        $response->assertOk()
            ->assertSee('Editorial Desk', false)
            ->assertSee('News &amp; Announcements', false)
            ->assertSee('Publication Ledger', false)
            ->assertSee('Compose Dispatch', false)
            ->assertSee('Published', false)
            ->assertSee('Draft', false)
            ->assertSee('Scheduled', false)
            ->assertSee('Archived', false)
            ->assertSee('Event', false)
            ->assertSee('Announcement', false)
            ->assertSee('Update', false)
            ->assertSee('Schedule / Meeting', false)
            ->assertSee('Digital Humanities Symposium', false)
            ->assertSee('Revised Access Protocols', false)
            ->assertSee('Extended Reading Hall Hours', false)
            ->assertSee('Faculty Librarians Council', false)
            ->assertSee('Feature on Homepage', false)
            ->assertSee('Save Draft', false)
            ->assertSee('Publish', false);
    }

    public function test_admin_feedback_page_renders_two_column_inbox_surface(): void
    {
        $response = $this->withSession($this->staffSession('admin'))->get('/admin/feedback');

        $response->assertOk()
            ->assertSee('Inbox', false)
            ->assertSee('Institutional Feedback &amp; Requests', false)
            ->assertSee('Request', false)
            ->assertSee('Complaint', false)
            ->assertSee('Improvement Suggestion', false)
            ->assertSee('Question', false)
            ->assertSee('Other', false)
            ->assertSee('Access to Rare Archives', false)
            ->assertSee('Study Room Booking Conflict', false)
            ->assertSee('Extended Weekend Hours', false)
            ->assertSee('Reply to User', false)
            ->assertSee('Add Internal Note', false)
            ->assertSee('Send Response', false);
    }

    public function test_admin_users_page_renders_full_user_management(): void
    {
        $response = $this->withSession($this->staffSession('admin'))->get('/admin/users');

        $response->assertOk()
            ->assertSee('User &amp; Role Management', false)
            ->assertSee('Manage institutional identities', false)
            ->assertSee('User Identity', false)
            ->assertSee('System Role', false)
            ->assertSee('Identity Dossier', false)
            ->assertSee('Directory Integration', false)
            ->assertSee('System Permissions', false)
            ->assertSee('Export Roster', false)
            ->assertSee('Provision Identity', false)
            ->assertSee('Primary Role', false)
            ->assertSee('CRM Sync Status', false)
            ->assertSee('Dr. Robert Chen', false)
            ->assertSee('Amina Kasymova', false)
            ->assertSee('Sarah Jenkins', false)
            ->assertSee('Marcus Johnson', false);
    }

    public function test_admin_governance_page_renders_full_audit_surface(): void
    {
        $response = $this->withSession($this->staffSession('admin'))->get('/admin/logs');

        $response->assertOk()
            ->assertSee('System Audit', false)
            ->assertSee('Governance, Logs &amp; Monitoring', false)
            ->assertSee('Comprehensive oversight of system events', false)
            ->assertSee('Advanced Filter', false)
            ->assertSee('Export Report', false)
            // System Pulse
            ->assertSee('System Pulse', false)
            ->assertSee('Authentication', false)
            ->assertSee('Catalog DB', false)
            ->assertSee('External API', false)
            ->assertSee('Degraded', false)
            // Security Flags
            ->assertSee('Recent Security Flags', false)
            ->assertSee('Multiple Failed Logins', false)
            ->assertSee('Policy Override Attempt', false)
            ->assertSee('High Severity', false)
            ->assertSee('Medium Severity', false)
            // Audit Log table headers
            ->assertSee('Timestamp', false)
            ->assertSee('Event / Context', false)
            ->assertSee('Severity', false)
            // Audit Log rows
            ->assertSee('Record Modification: Collection Metadata', false)
            ->assertSee('E. Kassenov', false)
            ->assertSee('Authentication Failure', false)
            ->assertSee('Configuration Change: API Keys', false)
            ->assertSee('User Role Change', false)
            ->assertSee('Digital Access Policy Update', false)
            // Drawer
            ->assertSee('Event Details', false)
            ->assertSee('Acknowledge Event', false)
            // Pagination
            ->assertSee('Showing 1 to 6 of 1,284 entries', false);
    }
}
