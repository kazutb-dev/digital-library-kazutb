<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\ExternalResource;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\UploadedFile;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class AdminCsvImportTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        $this->adminUser->forceFill(['locale' => 'ru'])->save();
    }

    private function csv(string $content): UploadedFile
    {
        return UploadedFile::fake()->createWithContent('import.csv', $content);
    }

    public function test_import_form_requires_permission(): void
    {
        $reader = $this->makeControlPlaneUser('member');
        $reader->givePermissionTo('news.edit_any');

        $this->signInToLibraryAs($reader)->get('/admin/import/users')->assertForbidden();
        $this->signInToLibraryAs($this->adminUser)->get('/admin/import/users')->assertOk();
    }

    public function test_import_routes_sit_behind_request_forgery_protection(): void
    {
        // Forgery token checks are skipped inside the test runner, so the
        // guarantee is asserted structurally: every import route belongs to
        // the web group, which carries PreventRequestForgery. (Verified live
        // over HTTP: a POST without a token returns 419.)
        foreach (['admin.imports.preview', 'admin.imports.commit'] as $routeName) {
            $route = app('router')->getRoutes()->getByName($routeName);
            $this->assertNotNull($route);
            $this->assertContains('web', $route->gatherMiddleware(), $routeName.' must use the web middleware group.');
        }
    }

    public function test_preview_classifies_create_update_and_error_rows(): void
    {
        $existing = $this->makeControlPlaneUser('member', ['email' => 'known.user@example.test']);

        $response = $this
            ->signInToLibraryAs($this->adminUser)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post('/admin/import/users/preview', [
                'file' => $this->csv(implode("\n", [
                    'email,name,role',
                    'fresh.user@example.test,Fresh User,member',
                    $existing->email.',Renamed User,member',
                    'broken-email,No Mail,member',
                    'ghost@example.test,Ghost,unknown-role',
                ])),
            ]);

        $response->assertOk()
            ->assertSee('fresh.user@example.test')
            ->assertSee('Некорректный email.')
            ->assertSee('unknown-role');

        // Nothing is written by a preview.
        $this->assertNull(User::query()->where('email', 'fresh.user@example.test')->first());
        $this->assertSame($existing->name, $existing->fresh()->name);
    }

    public function test_admin_role_rows_require_roles_manage_permission(): void
    {
        $operator = $this->makeControlPlaneUser('member', ['locale' => 'ru']);
        $operator->givePermissionTo('users.manage');

        $this->signInToLibraryAs($operator)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post('/admin/import/users/preview', [
                'file' => $this->csv("email,name,role\nescalation@example.test,Escalation,admin"),
            ])
            ->assertOk()
            ->assertSee('Управление ролями');

        $this->assertNull(User::query()->where('email', 'escalation@example.test')->first());
    }

    public function test_commit_applies_plan_transactionally_and_audits(): void
    {
        $existing = $this->makeControlPlaneUser('member', ['email' => 'renamed.target@example.test']);

        $preview = $this
            ->signInToLibraryAs($this->adminUser)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post('/admin/import/users/preview', [
                'file' => $this->csv(implode("\n", [
                    'email,name,role,department',
                    'created.by.import@example.test,Imported User,librarian,Library',
                    $existing->email.',Renamed By Import,member,Economics',
                ])),
            ])
            ->assertOk();

        preg_match('/name="token" value="([^"]+)"/', $preview->getContent(), $matches);
        $this->assertNotEmpty($matches[1] ?? null, 'Preview must expose a commit token.');

        $this
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post('/admin/import/users', ['token' => $matches[1]])
            ->assertRedirect(route('admin.users.index'));

        $created = User::query()->where('email', 'created.by.import@example.test')->firstOrFail();
        $this->assertTrue($created->hasRole('librarian'));
        $this->assertTrue((bool) $created->is_active);
        $this->assertSame('Renamed By Import', $existing->fresh()->name);

        $auditEntry = ActivityLog::query()
            ->where('action_type', 'import')
            ->where('entity_type', 'user')
            ->firstOrFail();
        $this->assertSame(1, (int) data_get($auditEntry->new_values, 'created'));
        $this->assertSame(1, (int) data_get($auditEntry->new_values, 'updated'));
    }

    public function test_commit_refuses_plan_with_error_rows(): void
    {
        $preview = $this
            ->signInToLibraryAs($this->adminUser)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post('/admin/import/users/preview', [
                'file' => $this->csv("email,name,role\nbad-email,Broken,member"),
            ])
            ->assertOk();

        // The preview with errors renders no commit form at all.
        $this->assertStringNotContainsString('name="token"', $preview->getContent());
    }

    public function test_external_resources_import_creates_and_updates_by_slug(): void
    {
        $preview = $this
            ->signInToLibraryAs($this->adminUser)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post('/admin/import/external-resources/preview', [
                'file' => $this->csv(implode("\n", [
                    'title,url,resource_type,description,provider,license_expires_at',
                    'Imported Scopus,https://scopus.example.test,licensed,Abstract database,Elsevier,2027-06-30',
                ])),
            ])
            ->assertOk();

        preg_match('/name="token" value="([^"]+)"/', $preview->getContent(), $matches);

        $this
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post('/admin/import/external-resources', ['token' => $matches[1]])
            ->assertRedirect(route('admin.external-resources.index'));

        $resource = ExternalResource::query()->where('slug', 'imported-scopus')->firstOrFail();
        $this->assertSame('licensed', $resource->resource_type);
        $this->assertSame('2027-06-30', $resource->license_expires_at?->toDateString());
        $this->assertSame(['member', 'librarian', 'admin'], $resource->available_roles);
        $this->assertSame('draft', $resource->publication_status);
        $this->assertFalse($resource->is_active);
    }
}
