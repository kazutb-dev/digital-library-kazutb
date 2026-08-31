<?php

namespace Tests\Feature;

use Tests\TestCase;

class InternalDashboardPageTest extends TestCase
{
    public function test_legacy_internal_dashboard_permanently_redirects_to_supported_workspace(): void
    {
        $this->get('/internal/dashboard')
            ->assertStatus(301)
            ->assertRedirect('/librarian');
    }
}
