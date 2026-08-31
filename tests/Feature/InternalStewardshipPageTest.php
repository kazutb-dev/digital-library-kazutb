<?php

namespace Tests\Feature;

use Tests\TestCase;

class InternalStewardshipPageTest extends TestCase
{
    public function test_legacy_stewardship_page_redirects_to_the_data_quality_workspace(): void
    {
        $this->get('/internal/stewardship')
            ->assertStatus(301)
            ->assertRedirect('/librarian/data-cleanup');
    }
}
