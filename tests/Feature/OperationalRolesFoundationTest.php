<?php

namespace Tests\Feature;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\Loan;
use App\Models\ContactMessage;
use App\Models\User;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Spatie\Permission\Models\Role;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class OperationalRolesFoundationTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        config(['demo_users.enabled' => true]);
        $this->setUpAdminControlPlane();
    }

    public function test_operational_role_permission_matrix_is_exact(): void
    {
        $expected = [
            'director' => RoleSeeder::DIRECTOR,
            'senior_librarian' => array_values(array_unique([
                ...RoleSeeder::MEMBER,
                ...RoleSeeder::LIBRARIAN_EXTRA,
                ...RoleSeeder::SENIOR_LIBRARIAN_EXTRA,
            ])),
            'acquisitions' => RoleSeeder::ACQUISITIONS,
            'cataloguer' => RoleSeeder::CATALOGUER,
            'bibliographer' => RoleSeeder::BIBLIOGRAPHER,
        ];

        foreach ($expected as $roleName => $permissions) {
            $actual = Role::findByName($roleName)
                ->permissions
                ->pluck('name')
                ->sort()
                ->values()
                ->all();
            sort($permissions);

            $this->assertSame($permissions, $actual, "Unexpected matrix for {$roleName}");
        }

        // Admin holds the whole catalogue, so this tracks PermissionSeeder
        // rather than a literal that has to be bumped on every addition.
        $this->assertCount(count(PermissionSeeder::PERMISSIONS), Role::findByName('admin')->permissions);
    }

    public function test_each_new_demo_identity_logs_in_and_lands_in_operational_workspace(): void
    {
        foreach ([
            'director',
            'senior_librarian',
            'acquisitions',
            'cataloguer',
            'bibliographer',
        ] as $slug) {
            $this->app['auth']->forgetGuards();

            $this->withoutMiddleware(PreventRequestForgery::class)
                ->withSession([])
                ->post("/login/demo/{$slug}")
                ->assertRedirect('/librarian');

            $this->assertAuthenticated();
            $this->assertSame($slug, auth()->user()->getRoleNames()->first());
        }
    }

    public function test_director_sidebar_and_access_are_read_only_and_executive_scoped(): void
    {
        $director = $this->makeControlPlaneUser('director');
        $record = BibliographicRecord::factory()->create();

        $response = $this->signInToLibraryAs($director)->get('/librarian');

        $response
            ->assertOk()
            ->assertSee(route('librarian.repository'), false)
            ->assertSee(route('librarian.reports.index'), false)
            ->assertSee(route('librarian.messages.index'), false)
            ->assertDontSee(route('librarian.circulation'), false)
            ->assertDontSee(route('librarian.catalog.index'), false)
            ->assertDontSee(route('librarian.copies.index'), false);

        $this->signInToLibraryAs($director)
            ->get('/librarian/circulation/issue')
            ->assertForbidden();

        $this->signInToLibraryAs($director)
            ->get(route('librarian.catalog.edit', $record))
            ->assertForbidden();

        $this->signInToLibraryAs($director)
            ->get(route('librarian.reports.index'))
            ->assertOk()
            ->assertSee(__('librarian.reports.totals.issued'))
            ->assertSee(__('librarian.reports.totals.returned'))
            ->assertSee(__('librarian.reports.fund_usage'))
            ->assertSee(__('librarian.reports.acquisitions'))
            ->assertSee('Фонд по УДК-разделам')
            ->assertSee('data-report="udc-fund"', false);

        $this->signInToLibraryAs($director)
            ->get('/librarian/staff-performance')
            ->assertNotFound();

        $this->signInToLibraryAs($director)
            ->get('/admin')
            ->assertForbidden();
    }

    public function test_senior_librarian_inherits_librarian_and_sensitive_permissions(): void
    {
        $senior = $this->makeControlPlaneUser('senior_librarian');

        $this->assertTrue($senior->can('circulation.issue'));
        $this->assertTrue($senior->can('circulation.override_limits'));
        $this->assertTrue($senior->can('catalog.delete_record'));
        $this->assertTrue($senior->can('copies.delete'));
        $this->assertFalse($senior->can('messages.delete'));

        $this->signInToLibraryAs($senior)
            ->get('/librarian')
            ->assertOk()
            ->assertSee(route('librarian.circulation'), false)
            ->assertSee(route('librarian.data-cleanup'), false);
    }

    public function test_acquisitions_sidebar_and_catalog_create_boundary(): void
    {
        $acquisitions = $this->makeControlPlaneUser('acquisitions');

        $this->signInToLibraryAs($acquisitions)
            ->get('/librarian')
            ->assertOk()
            ->assertSee(route('librarian.catalog.index'), false)
            ->assertSee(route('librarian.copies.index'), false)
            ->assertDontSee(route('librarian.circulation'), false)
            ->assertSee(__('roles.upcoming.acquisitions'));

        $this->signInToLibraryAs($acquisitions)
            ->get('/librarian/catalog/create')
            ->assertForbidden();
    }

    public function test_cataloguer_can_edit_but_cannot_issue_or_delete_records(): void
    {
        $cataloguer = $this->makeControlPlaneUser('cataloguer');
        $record = BibliographicRecord::factory()->create();

        $this->signInToLibraryAs($cataloguer)
            ->get(route('librarian.catalog.edit', $record))
            ->assertOk()
            ->assertSee('name="udc_code"', false)
            ->assertDontSee('name="udc_code" disabled', false);

        $this->signInToLibraryAs($cataloguer)
            ->get('/librarian/circulation/issue')
            ->assertForbidden();

        $this->assertFalse($cataloguer->can('catalog.delete_record'));
    }

    public function test_librarian_dir_requirements_are_covered_except_reader_registration(): void
    {
        $librarian = $this->makeControlPlaneUser('librarian');

        foreach ([
            'circulation.issue',
            'circulation.return',
            'circulation.view_any_history',
            'catalog.search',
            'catalog.create_record',
            'catalog.edit_record',
            'copies.create',
            'copies.edit',
            'fines.view',
            'fines.manage',
            'reports.view_ops',
        ] as $permission) {
            $this->assertTrue($librarian->can($permission), "Librarian lacks {$permission}");
        }

        $this->assertFalse($librarian->can('users.manage'));
    }

    public function test_senior_librarian_sees_existing_quality_aggregates(): void
    {
        $senior = $this->makeControlPlaneUser('senior_librarian');

        $this->signInToLibraryAs($senior)
            ->get(route('librarian.data-cleanup'))
            ->assertOk()
            ->assertSee(__('librarian.data_cleanup.issues.drafts'))
            ->assertSee(__('librarian.data_cleanup.issues.missing_udc'))
            ->assertSee(__('librarian.data_cleanup.issues.duplicates'))
            ->assertSee(__('librarian.data_cleanup.issues.unplaced_copies'));
    }

    public function test_cataloguer_and_senior_librarian_can_open_unplaced_copy_editor(): void
    {
        $copy = BookCopy::factory()->create(['shelf_location' => null]);

        foreach (['cataloguer', 'senior_librarian'] as $role) {
            $user = $this->makeControlPlaneUser($role);

            $this->signInToLibraryAs($user)
                ->get(route('librarian.data-cleanup', ['issue' => 'unplaced_copies']))
                ->assertOk()
                ->assertSee(route('librarian.copies.edit', $copy), false);
        }
    }

    public function test_bibliographer_enters_workspace_but_cannot_edit_catalog(): void
    {
        $bibliographer = $this->makeControlPlaneUser('bibliographer');
        $record = BibliographicRecord::factory()->create();

        $this->signInToLibraryAs($bibliographer)
            ->get('/librarian')
            ->assertOk()
            ->assertSee(route('librarian.catalog.index'), false)
            ->assertDontSee(route('librarian.circulation'), false)
            ->assertDontSee(route('librarian.copies.index'), false);

        $this->signInToLibraryAs($bibliographer)
            ->get(route('librarian.catalog.edit', $record))
            ->assertForbidden();
    }

    public function test_circulation_mutations_require_real_permissions_despite_legacy_librarian_session(): void
    {
        $reader = User::factory()->create(['is_active' => true]);

        foreach (['director', 'acquisitions', 'cataloguer', 'bibliographer'] as $roleName) {
            $staff = $this->makeControlPlaneUser($roleName);
            $availableCopy = BookCopy::factory()->create();

            $this->signInToLibraryAs($staff)
                ->withoutMiddleware(PreventRequestForgery::class)
                ->post(route('librarian.circulation.issue.store'), [
                    'reader_id' => $reader->getKey(),
                    'copy_code' => $availableCopy->barcode,
                ])
                ->assertForbidden();

            $this->assertDatabaseMissing('loans', ['copy_id' => $availableCopy->getKey()]);
            $this->assertSame('available', $availableCopy->refresh()->status);

            $issuedCopy = BookCopy::factory()->issued()->create();
            $loan = Loan::factory()->create([
                'user_id' => $reader->getKey(),
                'copy_id' => $issuedCopy->getKey(),
            ]);

            $this->signInToLibraryAs($staff)
                ->withoutMiddleware(PreventRequestForgery::class)
                ->post(route('librarian.circulation.return.store'), [
                    'copy_code' => $issuedCopy->barcode,
                    'incident' => 'none',
                ])
                ->assertForbidden();

            $this->assertSame('active', $loan->refresh()->status);
            $this->assertSame('issued', $issuedCopy->refresh()->status);
        }

        $senior = $this->makeControlPlaneUser('senior_librarian');
        $seniorCopy = BookCopy::factory()->create();

        $this->signInToLibraryAs($senior)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.circulation.issue.store'), [
                'reader_id' => $reader->getKey(),
                'copy_code' => $seniorCopy->barcode,
            ])
            ->assertRedirect();

        $seniorLoan = Loan::query()->where('copy_id', $seniorCopy->getKey())->firstOrFail();
        $this->assertSame('active', $seniorLoan->status);
        $this->assertSame('issued', $seniorCopy->refresh()->status);

        $this->signInToLibraryAs($senior)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.circulation.return.store'), [
                'copy_code' => $seniorCopy->barcode,
                'incident' => 'none',
            ])
            ->assertRedirect();

        $this->assertSame('returned', $seniorLoan->refresh()->status);
        $this->assertSame('available', $seniorCopy->refresh()->status);
    }

    public function test_transitional_staff_tools_require_data_cleanup_permission(): void
    {
        foreach (['director', 'acquisitions', 'cataloguer', 'bibliographer'] as $roleName) {
            $staff = $this->makeControlPlaneUser($roleName);

            $this->signInToLibraryAs($staff)->get('/internal/review')->assertForbidden();
            $this->signInToLibraryAs($staff)->get('/internal/ai-chat')->assertForbidden();
        }

        $senior = $this->makeControlPlaneUser('senior_librarian');
        $this->signInToLibraryAs($senior)->get('/internal/review')->assertOk();
        $this->signInToLibraryAs($senior)->get('/internal/ai-chat')->assertOk();
    }

    public function test_operational_roles_cannot_use_reader_only_account_api(): void
    {
        foreach (['director', 'senior_librarian', 'acquisitions', 'cataloguer', 'bibliographer'] as $roleName) {
            $staff = $this->makeControlPlaneUser($roleName);

            $this->signInToLibraryAs($staff)
                ->getJson('/api/v1/account/reservations')
                ->assertForbidden();
        }
    }

    public function test_bibliographer_sees_only_bibliographic_requests_and_cannot_process_them(): void
    {
        $bibliographer = $this->makeControlPlaneUser('bibliographer');
        $requestMessage = ContactMessage::query()->create([
            'category' => 'request',
            'subject' => 'Подберите литературу по экономике',
            'body' => 'Нужен библиографический список.',
            'sender_email' => 'reader-request@example.test',
            'status' => 'open',
            'priority' => 'normal',
        ]);
        $complaint = ContactMessage::query()->create([
            'category' => 'complaint',
            'subject' => 'Жалоба на обслуживание',
            'body' => 'Это обращение не относится к библиографическому поиску.',
            'sender_email' => 'reader-complaint@example.test',
            'status' => 'open',
            'priority' => 'normal',
        ]);

        $this->signInToLibraryAs($bibliographer)
            ->get(route('librarian.messages.index'))
            ->assertOk()
            ->assertSee($requestMessage->subject)
            ->assertDontSee($complaint->subject)
            ->assertSee(__('messages.bibliographer_scope'));

        $this->signInToLibraryAs($bibliographer)
            ->get(route('librarian.messages.show', $complaint))
            ->assertForbidden();

        $this->signInToLibraryAs($bibliographer)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->patch(route('librarian.messages.update', $requestMessage), [
                'status' => 'resolved',
                'resolution_comment' => 'Попытка закрытия.',
            ])
            ->assertForbidden();

        $this->assertSame('open', $requestMessage->refresh()->status);
    }

    public function test_admin_roles_page_lists_new_roles_with_permission_counts(): void
    {
        $response = $this->signInToLibraryAs($this->adminUser)->get('/admin/roles');

        $response->assertOk();

        foreach ([
            'director' => 5,
            'senior_librarian' => 45,
            'acquisitions' => 6,
            'cataloguer' => 6,
            'bibliographer' => 8,
        ] as $role => $count) {
            $response
                ->assertSee(__('roles.names.'.$role))
                ->assertSee($role)
                ->assertSee($count.' · '.__('roles.permissions_count'));
        }
    }
}
