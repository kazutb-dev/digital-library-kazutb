<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpaShellTest extends TestCase
{
    public function test_spa_root_renders_successfully(): void
    {
        $response = $this->get('/app');

        $response
            ->assertOk()
            ->assertSee('<div id="spa-root"></div>', false)
            ->assertSee('spa', false)
            ->assertSee('main', false);
    }

    public function test_spa_subroute_renders_same_shell(): void
    {
        $response = $this->get('/app/catalog');

        $response
            ->assertOk()
            ->assertSee('<div id="spa-root"></div>', false);
    }

    public function test_spa_deep_subroute_renders_same_shell(): void
    {
        $response = $this->get('/app/some/deep/path');

        $response
            ->assertOk()
            ->assertSee('<div id="spa-root"></div>', false);
    }

    public function test_spa_has_csrf_meta_tag(): void
    {
        $response = $this->get('/app');

        $response
            ->assertOk()
            ->assertSee('meta name="csrf-token"', false);
    }

    public function test_spa_has_correct_lang_attribute(): void
    {
        $response = $this->get('/app');

        $response
            ->assertOk()
            ->assertSee('<html lang="ru">', false);
    }
}
