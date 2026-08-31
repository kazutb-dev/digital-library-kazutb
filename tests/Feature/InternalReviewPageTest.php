<?php

namespace Tests\Feature;

use Tests\TestCase;

class InternalReviewPageTest extends TestCase
{
    public function test_legacy_review_page_redirects_to_the_localized_data_quality_workspace(): void
    {
        $this->get('/internal/review')
            ->assertStatus(301)
            ->assertRedirect('/librarian/data-cleanup');
    }
}
