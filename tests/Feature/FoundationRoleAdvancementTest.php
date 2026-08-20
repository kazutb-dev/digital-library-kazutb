<?php

namespace Tests\Feature;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\ReaderProfile;
use App\Models\User;
use App\Services\Catalog\LibraryVisitService;
use App\Services\Catalog\ReservationQueueService;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

/**
 * The foundational roles must gain real function as the ДИР is worked through,
 * not sit behind a permanent "coming next" banner. This locks in what §9 of
 * this pass actually delivered to each of them.
 */
class FoundationRoleAdvancementTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
    }

    private function reader(): User
    {
        $reader = $this->makeControlPlaneUser('member');
        ReaderProfile::forUser($reader);

        return $reader;
    }

    /**
     * ДИР §2.2 promises leadership "посещаемость, выдача книг, возвраты", so the
     * new attendance report must reach the director immediately.
     */
    public function test_director_sees_the_attendance_report(): void
    {
        $director = $this->makeControlPlaneUser('director');
        app(LibraryVisitService::class)->record($this->reader(), null, null, 'kiosk');

        $response = $this->signInToLibraryAs($director)->get('/librarian/reports');

        $response->assertOk();
        $response->assertSee(__('librarian.reports.visits'));
        $response->assertSee(__('librarian.reports.visit_metrics.total'));
        $response->assertSee(__('librarian.reports.visit_metrics.busiest_day'));
    }

    public function test_director_can_export_the_attendance_report(): void
    {
        $director = $this->makeControlPlaneUser('director');
        app(LibraryVisitService::class)->record($this->reader(), null, null, 'desk');

        $this->signInToLibraryAs($director)
            ->get('/librarian/reports/visits/export')
            ->assertOk()
            ->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    /**
     * Recording attendance is a desk duty; the director observes rather than
     * staffs the door.
     */
    public function test_director_does_not_staff_the_attendance_desk(): void
    {
        $this->signInToLibraryAs($this->makeControlPlaneUser('director'))
            ->get('/librarian/visits')
            ->assertForbidden();
    }

    /**
     * senior_librarian inherits the librarian toolset — confirm the §8
     * reservation actions and the new attendance desk are not accidentally
     * gated away from it.
     */
    public function test_senior_librarian_reaches_every_reservation_action_and_the_visit_desk(): void
    {
        $senior = $this->makeControlPlaneUser('senior_librarian');

        foreach (['reservation.confirm', 'reservation.cancel_any', 'visits.record', 'circulation.issue'] as $permission) {
            $this->assertTrue($senior->can($permission), "senior_librarian must hold {$permission}");
        }

        $this->signInToLibraryAs($senior)->get('/librarian/reservations')->assertOk();
        $this->signInToLibraryAs($senior)->get('/librarian/visits')->assertOk();
        $this->signInToLibraryAs($senior)->get('/librarian/reports')->assertOk();
    }

    public function test_senior_librarian_can_perform_the_reservation_handover_actions(): void
    {
        $senior = $this->makeControlPlaneUser('senior_librarian');
        $record = BibliographicRecord::factory()->create();
        BookCopy::factory()->create(['bibliographic_record_id' => $record->getKey(), 'status' => 'available']);

        $queue = app(ReservationQueueService::class);
        $hold = $queue->create($this->reader(), $record, $this->adminUser);
        $this->assertSame('ready_for_pickup', $hold->status);

        // Extend is allowed while nobody waits.
        $this->signInToLibraryAs($senior)
            ->post('/librarian/reservations/'.$hold->getKey().'/extend')
            ->assertRedirect();

        $queue->create($this->reader(), $record);

        $this->signInToLibraryAs($senior)
            ->post('/librarian/reservations/'.$hold->getKey().'/pass-to-next', ['reason' => 'Reader declined at the desk'])
            ->assertRedirect();

        $this->assertSame('cancelled', $hold->fresh()->status);
    }

    /**
     * §9.3 — the cataloguer registers copies, so they need to see that the count
     * drives the reader's loan period. Read-only: the scale is an admin setting.
     */
    public function test_cataloguer_sees_the_loan_period_consequence_of_copy_count(): void
    {
        $cataloguer = $this->makeControlPlaneUser('cataloguer');
        $record = BibliographicRecord::factory()->create();
        BookCopy::factory()->create(['bibliographic_record_id' => $record->getKey(), 'status' => 'available']);

        $response = $this->signInToLibraryAs($cataloguer)->get('/librarian/catalog/'.$record->getKey().'/edit');

        $response->assertOk();
        $response->assertSee(__('librarian.catalog.loan_period_hint', ['copies' => 1, 'days' => 3]));
    }

    public function test_cataloguer_cannot_edit_the_loan_scale_itself(): void
    {
        $this->signInToLibraryAs($this->makeControlPlaneUser('cataloguer'))
            ->get('/admin/settings')
            ->assertForbidden();
    }

    public function test_the_workspace_shows_the_localized_role_label(): void
    {
        foreach (['director' => 'reports.view_full', 'cataloguer' => 'catalog.edit_record'] as $role => $_) {
            $user = $this->makeControlPlaneUser($role);

            $response = $this->signInToLibraryAs($user)->get('/librarian');

            $response->assertOk();
            $response->assertSee(__('brand.workspace.'.$role));
        }
    }

    public function test_workspace_role_labels_exist_in_every_locale(): void
    {
        foreach (['ru', 'kk', 'en'] as $locale) {
            app()->setLocale($locale);
            foreach (['director', 'senior_librarian', 'cataloguer'] as $role) {
                $key = 'brand.workspace.'.$role;
                $this->assertNotSame($key, __($key), "Missing {$locale} role label for {$role}");
            }
        }
        app()->setLocale('ru');
    }
}
