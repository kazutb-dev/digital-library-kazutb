<?php

namespace Tests\Feature;

use Tests\TestCase;

class ExternalResourcePageTest extends TestCase
{
    public function test_resources_page_displays_featured_resource_section(): void
    {
        $response = $this->get('/resources');

        $response
            ->assertOk()
            ->assertSee('resource-card--featured', false);
    }

    public function test_resources_page_renders_successfully(): void
    {
        $response = $this->get('/resources');

        $response
            ->assertOk()
            ->assertSee('Электронные ресурсы', false)
            ->assertSee('Внешние лицензированные ресурсы', false);
    }

    public function test_resources_page_has_institutional_support_section(): void
    {
        $response = $this->get('/resources');

        $response
            ->assertOk()
            ->assertSee('Digital Library', false)
            ->assertSee('Поддержка и обучение', false)
            ->assertSee('href="/contacts"', false);
    }

    public function test_resources_page_has_filter_bar(): void
    {
        $response = $this->get('/resources');

        $response
            ->assertOk()
            ->assertSee('resources-filter-bar', false)
            ->assertSee('resources-bento', false);
    }

    public function test_resources_page_displays_resource_cards(): void
    {
        $response = $this->get('/resources');

        $response
            ->assertOk()
            ->assertSee('resource-card--small', false)
            ->assertSee('resource-icon-tile', false);
    }

    public function test_resources_page_has_access_badges(): void
    {
        $response = $this->get('/resources');

        $response
            ->assertOk()
            ->assertSee('resource-badge', false)
            ->assertSee('access-badge', false);
    }

    public function test_for_teachers_redirects_to_resources(): void
    {
        $response = $this->get('/for-teachers');

        $response
            ->assertRedirect('/resources')
            ->assertStatus(301);
    }

    public function test_resources_page_has_proper_links(): void
    {
        $response = $this->get('/resources');

        $response
            ->assertOk()
            ->assertSee('target="_blank"', false)
            ->assertSee('rel="noopener noreferrer"', false)
            ->assertDontSee('href="#"', false);
    }

    public function test_shortlist_page_still_renders(): void
    {
        $response = $this->get('/shortlist');

        $response
            ->assertOk()
            ->assertSee('data-shortlist-page', false);
    }

    public function test_catalog_page_still_renders(): void
    {
        $response = $this->get('/catalog');

        $response->assertOk();
    }

    public function test_welcome_page_still_renders(): void
    {
        $response = $this->get('/');

        $response->assertOk();
    }

    public function test_about_page_renders_from_public_shell(): void
    {
        $response = $this->get('/about');

        $response
            ->assertOk()
            ->assertSee('КазТБУ Digital Library');
    }
}

