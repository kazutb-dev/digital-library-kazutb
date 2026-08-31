<?php

namespace Tests\Feature;

use Tests\TestCase;

class InternalCirculationPageTest extends TestCase
{
    public function test_legacy_internal_page_permanently_redirects_to_the_supported_workspace(): void
    {
        $this->get('/internal/circulation')
            ->assertStatus(301)
            ->assertRedirect('/librarian/circulation');
    }
}
