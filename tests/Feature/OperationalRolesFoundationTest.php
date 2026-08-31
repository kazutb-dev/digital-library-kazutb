<?php

namespace Tests\Feature;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\Loan;
use App\Models\Catalog\Reservation;
use App\Models\ContactMessage;
use App\Models\MessageCategory;
use App\Models\User;
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

        // Administrator capabilities are explicit: approval of official
        // reader-facing responses remains outside the technical role.
        $this->assertCount(count(RoleSeeder::ADMIN), Role::findByName('admin')->permissions);
    }

    public function test_operational_roles_are_not_exposed_through_demo_login_routes(): void
    {
        foreach ([
            'director',
            'senior_librarian',
            'acquisitions',
            'cataloguer',
            'bibliographer',
        ] as $slug) {
            $this->withoutMiddleware(PreventRequestForgery::class)
                ->withSession([])
                ->post("/login/demo/{$slug}")
                ->assertStatus(405);

            $this->assertGuest();
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

    public function test_acquisitions_can_create_a_draft_for_intake_but_cannot_edit_catalogue_records(): void
    {
        $acquisitions = $this->makeControlPlaneUser('acquisitions');
        $record = BibliographicRecord::factory()->create();

        $this->signInToLibraryAs($acquisitions)
            ->get('/librarian')
            ->assertOk()
            ->assertSee(route('librarian.catalog.index'), false)
            ->assertSee(route('librarian.copies.index'), false)
            ->assertDontSee(route('librarian.circulation'), false)
            ->assertSee('data-section="acquisitions-operational-dashboard"', false)
            ->assertSee(__('librarian.overview.roles.acquisitions.title'))
            ->assertSee(__('librarian.overview.roles.acquisitions.subtitle'));

        $this->signInToLibraryAs($acquisitions)
            ->get(route('librarian.catalog.create', ['return_to' => 'acquisitions']))
            ->assertOk()
            ->assertSee('name="return_to" value="acquisitions"', false);

        $draftTitle = 'Acquisition intake draft '.str()->random(8);
        $this->signInToLibraryAs($acquisitions)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.catalog.store'), [
                'title' => $draftTitle,
                'language' => 'ru',
                'resource_type' => 'book',
                'return_to' => 'acquisitions',
            ])
            ->assertRedirect(route('librarian.acquisitions.index', ['record_q' => $draftTitle]));

        $draft = BibliographicRecord::query()->where('title', $draftTitle)->firstOrFail();
        $this->assertTrue($draft->is_draft);
        $this->assertSame($acquisitions->getKey(), $draft->responsible_librarian_id);

        $this->signInToLibraryAs($acquisitions)
            ->get(route('librarian.catalog.edit', $record))
            ->assertForbidden();

        $originalTitle = $record->title;
        $this->signInToLibraryAs($acquisitions)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->patch(route('librarian.catalog.update', $record), [
                'title' => 'Arbitrary acquisition overwrite',
                'language' => 'ru',
                'resource_type' => 'book',
            ])
            ->assertForbidden();

        $this->assertSame($originalTitle, $record->refresh()->title);
    }

    public function test_director_cannot_reach_destructive_library_operations(): void
    {
        $director = $this->makeControlPlaneUser('director');
        $record = BibliographicRecord::factory()->create();
        $copy = BookCopy::factory()->create(['bibliographic_record_id' => $record->getKey()]);
        $reader = $this->makeControlPlaneUser('member');
        $reservation = Reservation::factory()->create([
            'user_id' => $reader->getKey(),
            'bibliographic_record_id' => $record->getKey(),
        ]);
        $originalRecordTitle = $record->title;
        $originalCopyStatus = $copy->status;

        $requests = [
            fn () => $this->delete(route('librarian.catalog.destroy', $record)),
            fn () => $this->post(route('librarian.copies.write-off.store'), [
                'copy_ids' => [$copy->getKey()],
                'reason' => 'Director must not write off copies',
            ]),
            fn () => $this->post(route('librarian.inventory.store'), [
                'name' => 'Director must not start inventory',
            ]),
            fn () => $this->post(route('librarian.acquisitions.store'), [
                'supplier_name' => 'Director must not intake stock',
            ]),
            fn () => $this->post(route('librarian.ksu.conflicts.resolve-group'), [
                'conflict_ids' => [1],
                'resolution' => 'accept',
            ]),
            fn () => $this->post(route('librarian.reservations.pass-to-next', $reservation), [
                'reason' => 'Director must not reorder the queue',
            ]),
        ];

        foreach ($requests as $request) {
            $this->signInToLibraryAs($director)
                ->withoutMiddleware(PreventRequestForgery::class);
            $request()->assertForbidden();
        }

        $this->assertDatabaseHas('bibliographic_records', [
            'id' => $record->getKey(),
            'title' => $originalRecordTitle,
        ]);
        $this->assertSame($originalCopyStatus, $copy->refresh()->status);
        $this->assertDatabaseHas('reservations', [
            'id' => $reservation->getKey(),
            'status' => $reservation->status,
        ]);
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
                    'condition_on_return' => 'unchanged',
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
                'condition_on_return' => 'unchanged',
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

            $this->signInToLibraryAs($staff)->get('/internal/review')->assertRedirect('/librarian/data-cleanup');
            $canonical = $this->signInToLibraryAs($staff)->get('/librarian/data-cleanup');
            $roleName === 'cataloguer' ? $canonical->assertOk() : $canonical->assertForbidden();
            $this->signInToLibraryAs($staff)->get('/internal/ai-chat')->assertForbidden();
        }

        $senior = $this->makeControlPlaneUser('senior_librarian');
        $this->signInToLibraryAs($senior)->get('/internal/review')->assertRedirect('/librarian/data-cleanup');
        $this->signInToLibraryAs($senior)->get('/librarian/data-cleanup')->assertOk();
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

    public function test_bibliographer_sees_assigned_bibliographic_requests_but_cannot_approve_them(): void
    {
        $bibliographer = $this->makeControlPlaneUser('bibliographer');
        $requestCategory = MessageCategory::query()->where('slug', 'bibliographic-reference')->firstOrFail();
        $complaintCategory = MessageCategory::query()->where('slug', 'complaint-other')->firstOrFail();
        $requestMessage = ContactMessage::query()->create([
            'category' => 'request', 'type' => 'request', 'category_id' => $requestCategory->getKey(),
            'subject' => 'Подберите литературу по экономике',
            'body' => 'Нужен библиографический список.',
            'sender_email' => 'reader-request@example.test',
            'status' => 'open',
            'priority' => 'medium', 'assigned_to' => $bibliographer->getKey(),
        ]);
        $complaint = ContactMessage::query()->create([
            'category' => 'complaint', 'type' => 'complaint', 'category_id' => $complaintCategory->getKey(),
            'subject' => 'Жалоба на обслуживание',
            'body' => 'Это обращение не относится к библиографическому поиску.',
            'sender_email' => 'reader-complaint@example.test',
            'status' => 'open',
            'priority' => 'medium', 'assigned_to' => $bibliographer->getKey(),
        ]);

        $this->signInToLibraryAs($bibliographer)
            ->get(route('librarian.messages.index'))
            ->assertOk()
            ->assertSee($requestMessage->subject)
            ->assertDontSee($complaint->subject)
            ->assertSee(__('messages.bibliographer_scope'));

        $this->signInToLibraryAs($bibliographer)
            ->get(route('librarian.messages.show', $complaint))
            ->assertNotFound();

        $this->signInToLibraryAs($bibliographer)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.messages.approve', $requestMessage))
            ->assertForbidden();

        $this->assertSame('open', $requestMessage->refresh()->status);
    }

    public function test_admin_roles_page_lists_new_roles_with_permission_counts(): void
    {
        $response = $this->signInToLibraryAs($this->adminUser)->get('/admin/roles');

        $response->assertOk();

        foreach ([
            'director' => count(RoleSeeder::DIRECTOR),
            'senior_librarian' => count(array_unique([...RoleSeeder::MEMBER, ...RoleSeeder::LIBRARIAN_EXTRA, ...RoleSeeder::SENIOR_LIBRARIAN_EXTRA])),
            'acquisitions' => count(RoleSeeder::ACQUISITIONS),
            'cataloguer' => count(RoleSeeder::CATALOGUER),
            'bibliographer' => count(RoleSeeder::BIBLIOGRAPHER),
        ] as $role => $count) {
            $response
                ->assertSee(__('roles.names.'.$role))
                ->assertSee($role)
                ->assertSee($count.' · '.__('roles.permissions_count'));
        }
    }
}
