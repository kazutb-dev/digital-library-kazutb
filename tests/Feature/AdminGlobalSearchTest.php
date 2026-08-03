<?php

namespace Tests\Feature;

use App\Models\ExternalResource;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class AdminGlobalSearchTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
    }

    public function test_guests_are_redirected_from_search(): void
    {
        $this->get('/admin/search?q=demo')->assertRedirect(route('login'));
    }

    public function test_admin_search_finds_users_and_resources(): void
    {
        $this->makeControlPlaneUser('member', [
            'name' => 'Searchable Reader',
            'email' => 'searchable.reader@example.test',
        ]);

        $response = $this
            ->signInToLibraryAs($this->adminUser)
            ->get('/admin/search?q=searchable');

        $response->assertOk()->assertSee('Searchable Reader');
    }

    public function test_json_mode_returns_grouped_results(): void
    {
        $resource = ExternalResource::query()->firstOrFail();

        $payload = $this
            ->signInToLibraryAs($this->adminUser)
            ->getJson('/admin/search?format=json&q='.urlencode(mb_substr($resource->title, 0, 8)))
            ->assertOk()
            ->json();

        $this->assertNotEmpty($payload['groups']);
        $keys = array_column($payload['groups'], 'key');
        $this->assertContains('external_resources', $keys);
    }

    public function test_groups_are_permission_scoped(): void
    {
        // A user with only news access must not receive user-directory hits.
        $editor = $this->makeControlPlaneUser('member', [
            'name' => 'News Editor',
            'email' => 'news.editor@example.test',
        ]);
        $editor->givePermissionTo('news.edit_any');

        $payload = $this
            ->signInToLibraryAs($editor)
            ->getJson('/admin/search?format=json&q=demo')
            ->assertOk()
            ->json();

        $this->assertNotContains('users', array_column($payload['groups'], 'key'));
    }

    public function test_short_query_returns_no_groups(): void
    {
        $payload = $this
            ->signInToLibraryAs($this->adminUser)
            ->getJson('/admin/search?format=json&q=a')
            ->assertOk()
            ->json();

        $this->assertSame([], $payload['groups']);
    }
}
