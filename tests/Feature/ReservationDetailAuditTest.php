<?php

namespace Tests\Feature;

use App\Exceptions\CirculationException;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\Loan;
use App\Models\Catalog\ReaderNotification;
use App\Models\Catalog\ReaderProfile;
use App\Models\Catalog\Reservation;
use App\Models\Setting;
use App\Models\User;
use App\Services\Catalog\CirculationService;
use App\Services\Catalog\LoanPeriodPolicy;
use App\Services\Catalog\ReservationInsightService;
use App\Services\Catalog\ReservationQueueService;
use Spatie\Permission\Models\Role;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

/**
 * ДИР §8 additions on top of the queue mechanics of ReservationQueueTest:
 * manual copy assignment, hold extension gated on the queue, the manual
 * "pass to the next reader" action, the availability forecast, the per-hold
 * notification log, and the informational inter-branch transit note.
 */
class ReservationDetailAuditTest extends TestCase
{
    use BuildsAdminControlPlane;

    private ReservationQueueService $reservations;

    private ReservationInsightService $insights;

    private BibliographicRecord $record;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();

        $this->reservations = app(ReservationQueueService::class);
        $this->insights = app(ReservationInsightService::class);
        $this->record = BibliographicRecord::factory()->create();

        // These scenarios explicitly exercise the librarian's confirmation
        // screen. Production defaults to automatic confirmation when a local
        // copy is free; keep the manual policy local to this test class.
        Setting::query()->where('key', 'reservation_manual_confirmation_required')->update(['value' => true]);
    }

    private function reader(): User
    {
        $reader = $this->makeControlPlaneUser('member');
        ReaderProfile::forUser($reader);

        return $reader;
    }

    /** Park a copy with a loan so the edition has no free stock. */
    private function issuedCopy(): BookCopy
    {
        $copy = BookCopy::factory()->create([
            'bibliographic_record_id' => $this->record->getKey(),
            'status' => 'issued',
        ]);
        Loan::factory()->create(['copy_id' => $copy->getKey(), 'user_id' => $this->adminUser->getKey()]);

        return $copy;
    }

    private function readyHold(Reservation $reservation): Reservation
    {
        return $this->reservations->markReady(
            $this->reservations->confirm($reservation, $this->adminUser),
            $this->adminUser,
        );
    }

    public function test_librarian_can_pin_a_specific_copy_instead_of_the_automatic_one(): void
    {
        $auto = BookCopy::factory()->create([
            'bibliographic_record_id' => $this->record->getKey(),
            'status' => 'available',
            'inventory_number' => 'INV-A',
        ]);
        $chosen = BookCopy::factory()->create([
            'bibliographic_record_id' => $this->record->getKey(),
            'status' => 'available',
            'inventory_number' => 'INV-B',
        ]);

        $reservation = $this->reservations->create($this->reader(), $this->record);
        $this->assertSame((int) $auto->getKey(), (int) $reservation->assigned_copy_id, 'The system pins the first free copy on create.');

        $confirmed = $this->reservations->confirm($reservation, $this->adminUser, $chosen);

        $this->assertSame((int) $chosen->getKey(), (int) $confirmed->assigned_copy_id);
        $this->assertSame('reserved', $chosen->fresh()->status);
        $this->assertSame('available', $auto->fresh()->status, 'The copy the system had picked goes back on the shelf.');
        ActivityLog::query()->where('action_type', 'reservation.assign_copy')->firstOrFail();
    }

    public function test_a_copy_from_another_edition_cannot_be_pinned(): void
    {
        BookCopy::factory()->create(['bibliographic_record_id' => $this->record->getKey(), 'status' => 'available']);
        $foreign = BookCopy::factory()->create([
            'bibliographic_record_id' => BibliographicRecord::factory()->create()->getKey(),
            'status' => 'available',
        ]);

        $reservation = $this->reservations->create($this->reader(), $this->record);

        try {
            $this->reservations->confirm($reservation, $this->adminUser, $foreign);
            $this->fail('A copy of a different edition must not be assignable.');
        } catch (CirculationException $exception) {
            $this->assertSame('reservation_copy_mismatch', $exception->reasonCode);
        }
    }

    public function test_a_busy_copy_cannot_be_pinned(): void
    {
        BookCopy::factory()->create(['bibliographic_record_id' => $this->record->getKey(), 'status' => 'available']);
        $busy = $this->issuedCopy();

        $reservation = $this->reservations->create($this->reader(), $this->record);

        try {
            $this->reservations->confirm($reservation, $this->adminUser, $busy);
            $this->fail('An issued copy must not be assignable.');
        } catch (CirculationException $exception) {
            $this->assertSame('reservation_copy_unavailable', $exception->reasonCode);
        }
    }

    public function test_a_pickup_hold_can_be_extended_when_nobody_is_waiting(): void
    {
        BookCopy::factory()->create(['bibliographic_record_id' => $this->record->getKey(), 'status' => 'available']);
        $reservation = $this->reservations->create($this->reader(), $this->record);
        $ready = $this->readyHold($reservation);
        $original = $ready->expires_at;

        $extended = $this->reservations->extend($ready, $this->adminUser);

        $lifespan = (int) Setting::valueFor('reservation_lifespan_days', 3);
        $this->assertTrue($extended->expires_at->greaterThan($original));
        $this->assertSame(
            $original->copy()->addDays($lifespan)->toDateString(),
            $extended->expires_at->toDateString(),
        );
        ActivityLog::query()->where('action_type', 'reservation.extend')->firstOrFail();
    }

    public function test_a_pickup_hold_cannot_be_extended_while_the_queue_has_readers(): void
    {
        BookCopy::factory()->create(['bibliographic_record_id' => $this->record->getKey(), 'status' => 'available']);
        $reservation = $this->reservations->create($this->reader(), $this->record);
        $ready = $this->readyHold($reservation);

        // A second reader now waits for the same edition.
        $this->reservations->create($this->reader(), $this->record);

        try {
            $this->reservations->extend($ready, $this->adminUser);
            $this->fail('The queue must outrank a reader who has not collected yet.');
        } catch (CirculationException $exception) {
            $this->assertSame('reservation_queue_waiting', $exception->reasonCode);
        }
    }

    public function test_only_a_ready_for_pickup_hold_can_be_extended(): void
    {
        BookCopy::factory()->create(['bibliographic_record_id' => $this->record->getKey(), 'status' => 'available']);
        $reservation = $this->reservations->create($this->reader(), $this->record);

        try {
            $this->reservations->extend($reservation, $this->adminUser);
            $this->fail('A pending reservation has no hold to extend.');
        } catch (CirculationException $exception) {
            $this->assertSame('reservation_not_extendable', $exception->reasonCode);
        }
    }

    public function test_pass_to_next_hands_the_copy_over_early_and_compacts_the_queue(): void
    {
        BookCopy::factory()->create(['bibliographic_record_id' => $this->record->getKey(), 'status' => 'available']);
        $holder = $this->reader();
        $hold = $this->readyHold($this->reservations->create($holder, $this->record));

        $next = $this->reservations->create($this->reader(), $this->record);
        $third = $this->reservations->create($this->reader(), $this->record);
        $this->assertSame([1, 2], [
            $this->insights->queuePosition($next),
            $this->insights->queuePosition($third),
        ]);

        $released = $this->reservations->passToNext($hold, $this->adminUser, 'Reader declined at the desk');

        $this->assertSame('cancelled', $released->status);
        $this->assertNull($released->assigned_copy_id, 'The released hold must no longer claim the copy.');
        $this->assertNull($released->expires_at);

        $next = $next->fresh();
        $this->assertSame('ready_for_pickup', $next->status, 'The next reader in line receives the copy immediately.');
        $this->assertNotNull($next->assigned_copy_id);
        $this->assertNotNull($next->expires_at);

        $this->assertSame(1, $this->insights->queuePosition($third->fresh()), 'The remaining reader moves up.');
        ActivityLog::query()->where('action_type', 'reservation.pass_to_next')->firstOrFail();
        $this->assertSame(1, ReaderNotification::query()
            ->where('user_id', $holder->getKey())
            ->where('event_type', 'reservation_cancelled')
            ->count());
    }

    public function test_pass_to_next_is_refused_when_the_queue_is_empty(): void
    {
        BookCopy::factory()->create(['bibliographic_record_id' => $this->record->getKey(), 'status' => 'available']);
        $hold = $this->readyHold($this->reservations->create($this->reader(), $this->record));

        try {
            $this->reservations->passToNext($hold, $this->adminUser, 'Reader declined at the desk');
            $this->fail('With nobody waiting this must be a cancellation, not a handover.');
        } catch (CirculationException $exception) {
            $this->assertSame('reservation_no_next_in_queue', $exception->reasonCode);
        }

        $this->assertSame('ready_for_pickup', $hold->fresh()->status, 'A refused handover must leave the hold untouched.');
    }

    public function test_availability_forecast_scales_with_queue_position_and_copy_count(): void
    {
        // §9.3 — the turnover assumption is the period this edition actually
        // gets. Two copies fall in the scarce tier, so 3 days, not a flat 14.
        $this->issuedCopy();
        $this->issuedCopy();
        $period = app(LoanPeriodPolicy::class)->daysForCopyCount(2);
        $this->assertSame(3, $period, 'Two copies must resolve to the scarce tier.');

        $first = $this->reservations->create($this->reader(), $this->record);
        $second = $this->reservations->create($this->reader(), $this->record);
        $fourth = $this->reservations->create($this->reader(), $this->record);
        $this->reservations->create($this->reader(), $this->record);

        // ceil(position * 3 / 2 copies)
        $this->assertSame(2, $this->insights->estimatedDaysUntilAvailable($first));
        $this->assertSame(3, $this->insights->estimatedDaysUntilAvailable($second));
        $this->assertSame(5, $this->insights->estimatedDaysUntilAvailable($fourth));
    }

    public function test_forecast_is_absent_once_a_copy_is_pinned(): void
    {
        BookCopy::factory()->create(['bibliographic_record_id' => $this->record->getKey(), 'status' => 'available']);
        $reservation = $this->reservations->create($this->reader(), $this->record);

        $this->assertNull(
            $this->insights->estimatedDaysUntilAvailable($reservation),
            'A reservation that already holds a copy needs no estimate.',
        );
    }

    public function test_lost_and_written_off_copies_are_excluded_from_the_forecast(): void
    {
        BookCopy::factory()->create(['bibliographic_record_id' => $this->record->getKey(), 'status' => 'lost']);
        BookCopy::factory()->create(['bibliographic_record_id' => $this->record->getKey(), 'status' => 'written_off']);

        $reservation = $this->reservations->create($this->reader(), $this->record);

        $this->assertSame(1, $this->insights->queuePosition($reservation));
        $this->assertNull(
            $this->insights->estimatedDaysUntilAvailable($reservation),
            'Stock that will never circulate cannot satisfy a queue.',
        );
    }

    public function test_queue_depth_counts_every_pending_reader_for_the_edition(): void
    {
        $this->issuedCopy();
        $this->reservations->create($this->reader(), $this->record);
        $this->reservations->create($this->reader(), $this->record);

        $depths = $this->insights->queueDepths([$this->record->getKey()]);

        $this->assertSame(2, (int) $depths[$this->record->getKey()]);
    }

    public function test_notification_log_returns_only_this_reservations_notifications(): void
    {
        BookCopy::factory()->create(['bibliographic_record_id' => $this->record->getKey(), 'status' => 'available']);
        BookCopy::factory()->create(['bibliographic_record_id' => $this->record->getKey(), 'status' => 'available']);

        $reader = $this->reader();
        $first = $this->reservations->create($reader, $this->record);
        $this->reservations->markReady($this->reservations->confirm($first, $this->adminUser), $this->adminUser);

        // The same reader also holds a request on a different edition.
        $otherRecord = BibliographicRecord::factory()->create();
        BookCopy::factory()->create(['bibliographic_record_id' => $otherRecord->getKey(), 'status' => 'available']);
        $second = $this->reservations->create($reader, $otherRecord);

        $log = $this->insights->notificationLog($first->fresh());

        $this->assertSame(3, $log->count(), 'created + confirmed + ready for this reservation only.');
        $this->assertEqualsCanonicalizing(
            ['reservation_created', 'reservation_confirmed', 'reservation_ready'],
            $log->pluck('event_type')->all(),
        );
        $this->assertSame(1, $this->insights->notificationLog($second)->count());
    }

    public function test_transfer_note_is_an_informational_marker_that_moves_no_stock(): void
    {
        $copy = BookCopy::factory()->create(['bibliographic_record_id' => $this->record->getKey(), 'status' => 'available']);
        $reservation = $this->reservations->create($this->reader(), $this->record);
        $branch = Branch::query()->create([
            'code' => 'BR-TRANSIT',
            'name' => 'Transit branch',
            'type' => 'library',
            'is_active' => true,
        ]);

        $noted = $this->reservations->setTransferNote($reservation, $this->adminUser, (int) $branch->getKey());

        $this->assertSame((int) $branch->getKey(), (int) $noted->pending_transfer_branch_id);
        $this->assertSame('at_library', $noted->logisticsState(), 'An old informational note must never fake physical transit.');
        $this->assertSame((int) $copy->branch_id, (int) $copy->fresh()->branch_id, 'The note must not relocate the copy.');
        $this->assertSame('reserved', $copy->fresh()->status);

        $cleared = $this->reservations->setTransferNote($noted, $this->adminUser, null);
        $this->assertNull($cleared->pending_transfer_branch_id);
        $this->assertSame('at_library', $cleared->logisticsState());
    }

    public function test_logistics_state_reports_a_copy_that_is_with_a_reader(): void
    {
        $this->issuedCopy();
        $queued = $this->reservations->create($this->reader(), $this->record);

        $this->assertNull($queued->logisticsState(), 'No copy is assigned yet.');

        $held = BookCopy::factory()->create(['bibliographic_record_id' => $this->record->getKey(), 'status' => 'issued']);
        $queued->forceFill(['assigned_copy_id' => $held->getKey()])->save();

        $this->assertSame('with_reader', $queued->fresh()->logisticsState());
    }

    public function test_librarian_screen_shows_contacts_status_dates_queue_and_forecast(): void
    {
        $librarian = $this->makeControlPlaneUser('librarian');
        $this->issuedCopy();
        $this->issuedCopy();

        $reader = $this->reader();
        $reader->forceFill(['email' => 'queued.reader@example.test'])->save();
        $this->reservations->create($reader, $this->record);
        $this->reservations->create($this->reader(), $this->record);

        $response = $this->signInToLibraryAs($librarian)->get('/librarian/reservations');

        $response->assertOk();
        // §8: contacts, the honest phone gap, and the reader's standing.
        $response->assertSee('queued.reader@example.test');
        $response->assertSee(__('librarian.reservations.phone_not_tracked'));
        $response->assertSee(__('librarian.circulation.reader_statuses.active'));
        // §8: queue depth, the hold period in force, and the hedged forecast.
        $response->assertSee(__('librarian.reservations.queue_depth', ['count' => 2]));
        $response->assertSee(__('librarian.reservations.lifespan_hint', [
            'days' => (int) Setting::valueFor('reservation_lifespan_days', 3),
        ]), false);
        // 2 copies → scarce tier (3 days); position 1 ÷ 2 copies → ceil(1.5) = 2.
        $response->assertSee(__('librarian.reservations.forecast_value', ['days' => 2]));
        $response->assertSee(__('librarian.reservations.notifications_log'));
        $response->assertSee(__('librarian.reservations.notifications_log_disclaimer'));
        $response->assertDontSee(__('librarian.reservations.transfer_note'), false);
    }

    public function test_librarian_screen_offers_a_copy_choice_only_when_several_are_free(): void
    {
        $librarian = $this->makeControlPlaneUser('librarian');
        BookCopy::factory()->create([
            'bibliographic_record_id' => $this->record->getKey(),
            'status' => 'available',
            'inventory_number' => 'PICK-1',
        ]);
        BookCopy::factory()->create([
            'bibliographic_record_id' => $this->record->getKey(),
            'status' => 'available',
            'inventory_number' => 'PICK-2',
        ]);

        $this->reservations->create($this->reader(), $this->record);

        $response = $this->signInToLibraryAs($librarian)->get('/librarian/reservations');

        $response->assertOk();
        $response->assertSee(__('librarian.reservations.assign_copy'));
        $response->assertSee(__('librarian.reservations.assign_copy_auto'));
        $response->assertSee('PICK-1');
        $response->assertSee('PICK-2');
    }

    public function test_extend_and_pass_to_next_routes_work_end_to_end(): void
    {
        $librarian = $this->makeControlPlaneUser('senior_librarian');
        BookCopy::factory()->create(['bibliographic_record_id' => $this->record->getKey(), 'status' => 'available']);
        $hold = $this->readyHold($this->reservations->create($this->reader(), $this->record));
        $originalExpiry = $hold->expires_at;

        // Nobody waiting: extending is allowed, handing over is not.
        $this->signInToLibraryAs($librarian)
            ->post('/librarian/reservations/'.$hold->getKey().'/extend')
            ->assertRedirect();
        $this->assertTrue($hold->fresh()->expires_at->greaterThan($originalExpiry));

        $next = $this->reservations->create($this->reader(), $this->record);

        // Now someone is waiting: extending is refused, handing over succeeds.
        $this->signInToLibraryAs($librarian)
            ->post('/librarian/reservations/'.$hold->getKey().'/extend')
            ->assertSessionHasErrors('reservation');

        $this->signInToLibraryAs($librarian)
            ->post('/librarian/reservations/'.$hold->getKey().'/pass-to-next', ['reason' => 'Reader declined at the desk'])
            ->assertRedirect();

        $this->assertSame('cancelled', $hold->fresh()->status);
        $this->assertSame('ready_for_pickup', $next->fresh()->status);
    }

    public function test_confirm_route_honours_a_hand_picked_copy(): void
    {
        $librarian = $this->makeControlPlaneUser('librarian');
        BookCopy::factory()->create([
            'bibliographic_record_id' => $this->record->getKey(),
            'status' => 'available',
            'inventory_number' => 'AUTO-1',
        ]);
        $chosen = BookCopy::factory()->create([
            'bibliographic_record_id' => $this->record->getKey(),
            'status' => 'available',
            'inventory_number' => 'CHOSEN-1',
        ]);

        $reservation = $this->reservations->create($this->reader(), $this->record);

        $this->signInToLibraryAs($librarian)
            ->post('/librarian/reservations/'.$reservation->getKey().'/confirm', [
                'assigned_copy_id' => $chosen->getKey(),
            ])
            ->assertRedirect();

        $this->assertSame((int) $chosen->getKey(), (int) $reservation->fresh()->assigned_copy_id);
    }

    public function test_reservation_action_permissions_cannot_be_bypassed_through_confirm_routes(): void
    {
        $librarian = $this->makeControlPlaneUser('librarian');
        $automatic = BookCopy::factory()->create([
            'bibliographic_record_id' => $this->record->getKey(),
            'status' => 'available',
            'inventory_number' => 'AUTO-PERMISSION',
        ]);
        $chosen = BookCopy::factory()->create([
            'bibliographic_record_id' => $this->record->getKey(),
            'status' => 'available',
            'inventory_number' => 'CHOSEN-PERMISSION',
        ]);
        $pending = $this->reservations->create($this->reader(), $this->record);

        $role = Role::findByName('librarian');
        $role->revokePermissionTo(['reservation.assign_copy', 'reservation.fulfill']);
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        $librarian->unsetRelation('roles')->unsetRelation('permissions');

        $this->signInToLibraryAs($librarian)
            ->post(route('librarian.reservations.confirm', $pending), [
                'assigned_copy_id' => $chosen->getKey(),
            ])
            ->assertForbidden();

        $this->assertSame((int) $automatic->getKey(), (int) $pending->fresh()->assigned_copy_id);
        $this->assertSame('pending', $pending->fresh()->status);

        $this->signInToLibraryAs($librarian)
            ->post(route('librarian.reservations.ready', $pending))
            ->assertForbidden();

        $this->assertFalse($librarian->can('reservation.extend'));
        $this->signInToLibraryAs($librarian)
            ->post(route('librarian.reservations.extend', $pending))
            ->assertForbidden();
    }

    public function test_reader_sees_the_hedged_forecast_on_their_reservations_page(): void
    {
        $this->issuedCopy();
        $reader = $this->reader();
        $this->reservations->create($reader, $this->record);

        $response = $this->signInToLibraryAs($reader)->get('/dashboard/reservations');

        $response->assertOk();
        $response->assertSee(__('librarian.reservations.forecast'));
        // 1 copy → scarce tier (3 days); position 1 ÷ 1 copy → 3.
        $response->assertSee(__('librarian.reservations.forecast_value', ['days' => 3]));
        $response->assertSee(__('librarian.member.reservations.forecast_disclaimer'));
    }

    public function test_a_ready_hold_notifies_the_reader_in_their_cabinet(): void
    {
        BookCopy::factory()->create(['bibliographic_record_id' => $this->record->getKey(), 'status' => 'available']);
        $reader = $this->reader();
        $this->readyHold($this->reservations->create($reader, $this->record));

        $response = $this->signInToLibraryAs($reader)->get('/dashboard/notifications');

        $response->assertOk();
        $response->assertSee(__('librarian.notifications.reservation_ready_title'));
    }

    /**
     * §8 action "mark as issued": no separate button exists because the real
     * checkout closes the hold. This guards that wiring.
     */
    public function test_issuing_the_copy_marks_the_reservation_fulfilled(): void
    {
        $copy = BookCopy::factory()->create([
            'bibliographic_record_id' => $this->record->getKey(),
            'status' => 'available',
        ]);
        $reader = $this->reader();
        $hold = $this->readyHold($this->reservations->create($reader, $this->record));

        $loan = app(CirculationService::class)->issue($reader, $copy, $this->adminUser);

        $this->assertSame('fulfilled', $hold->fresh()->status);
        $this->assertNull($hold->fresh()->queue_position);
        $this->assertSame('issued', $copy->fresh()->status);
        ActivityLog::query()
            ->where('action_type', 'reservation.fulfill')
            ->where('entity_id', $hold->getKey())
            ->firstOrFail();
        $this->assertSame((int) $reader->getKey(), (int) $loan->user_id);
    }

    public function test_a_reservation_cannot_be_handed_to_a_different_reader(): void
    {
        $copy = BookCopy::factory()->create([
            'bibliographic_record_id' => $this->record->getKey(),
            'status' => 'available',
        ]);
        $this->readyHold($this->reservations->create($this->reader(), $this->record));

        try {
            app(CirculationService::class)->issue($this->reader(), $copy, $this->adminUser);
            $this->fail('A copy held for one reader must not be issued to another.');
        } catch (CirculationException $exception) {
            $this->assertNotSame('', $exception->reasonCode);
        }
    }

    public function test_new_reservation_labels_exist_in_every_locale(): void
    {
        $keys = [
            'librarian.reservations.details',
            'librarian.reservations.contacts',
            'librarian.reservations.phone_not_tracked',
            'librarian.reservations.reader_status',
            'librarian.reservations.queue_depth',
            'librarian.reservations.lifespan_hint',
            'librarian.reservations.forecast',
            'librarian.reservations.forecast_value',
            'librarian.reservations.forecast_approximate',
            'librarian.reservations.forecast_unavailable',
            'librarian.reservations.logistics',
            'librarian.reservations.transfer_note',
            'librarian.reservations.transfer_note_hint',
            'librarian.reservations.notifications_log',
            'librarian.reservations.notifications_log_disclaimer',
            'librarian.reservations.channel_in_app',
            'librarian.reservations.assign_copy',
            'librarian.reservations.assign_copy_auto',
            'librarian.reservations.extend',
            'librarian.reservations.extend_blocked',
            'librarian.reservations.pass_to_next',
            'librarian.reservations.pass_to_next_reason',
            'librarian.reservations.pass_to_next_hint',
            'librarian.reservations.pass_to_next_blocked',
            'librarian.member.reservations.forecast_disclaimer',
            'librarian.errors.reservation_not_extendable',
            'librarian.errors.reservation_queue_waiting',
            'librarian.errors.reservation_not_passable',
            'librarian.errors.reservation_no_next_in_queue',
            'librarian.errors.reservation_copy_mismatch',
            'librarian.errors.reservation_copy_unavailable',
            'librarian.notifications.reservation_extended_title',
            'librarian.notifications.reservation_extended_body',
            'librarian.notifications.reservation_passed_title',
            'librarian.notifications.reservation_passed_body',
        ];

        foreach (['ru', 'kk', 'en'] as $locale) {
            app()->setLocale($locale);
            foreach ($keys as $key) {
                $this->assertNotSame($key, __($key), "Missing {$locale} translation for {$key}");
            }
            foreach (['with_reader', 'at_library', 'in_transit', 'unknown'] as $state) {
                $key = 'librarian.reservations.logistics_states.'.$state;
                $this->assertNotSame($key, __($key), "Missing {$locale} label for logistics state {$state}");
            }
        }
        app()->setLocale('ru');
    }
}
