<?php

namespace Tests\Feature;

use Tests\TestCase;

class SpaShellTest extends TestCase
{
    public function test_retired_spa_root_redirects_to_the_canonical_catalog(): void
    {
        $this->get('/app')->assertStatus(301)->assertRedirect('/catalog');
    }

    public function test_retired_spa_subroutes_redirect_to_the_canonical_catalog(): void
    {
        $this->get('/app/catalog')->assertStatus(301)->assertRedirect('/catalog');
        $this->get('/app/some/deep/path')->assertStatus(301)->assertRedirect('/catalog');
    }
}
