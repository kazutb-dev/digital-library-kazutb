<?php

namespace Tests\Feature;

use App\Exceptions\CirculationException;
use App\Models\ActivityLog;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\Loan;
use App\Models\Catalog\ReaderNotification;
use App\Models\Catalog\ReaderProfile;
use App\Models\Catalog\Reservation;
use App\Models\Setting;
use App\Models\User;
use App\Services\Catalog\ReservationInsightService;
use App\Services\Catalog\ReservationQueueService;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

/**
 * The three reservation scenarios of Master.md §13.2 plus the limits of §13.3.
 */
class ReservationQueueTest extends TestCase
{
    use BuildsAdminControlPlane;

    private ReservationQueueService $reservations;

    private User $reader;

    private BibliographicRecord $record;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();

        $this->reservations = app(ReservationQueueService::class);
        $this->reader = $this->makeControlPlaneUser('member');
        ReaderProfile::forUser($this->reader);
        $this->record = BibliographicRecord::factory()->create();
    }

    private function reader(): User
    {
        $reader = $this->makeControlPlaneUser('member');
        ReaderProfile::forUser($reader);

        return $reader;
    }

    public function test_scenario_one_free_copy_is_pinned_to_the_reservation(): void
    {
        $copy = BookCopy::factory()->create([
            'bibliographic_record_id' => $this->record->getKey(),
            'status' => 'available',
        ]);

        $reservation = $this->reservations->create($this->reader, $this->record);

        $this->assertSame('ready_for_pickup', $reservation->status);
        $this->assertSame((int) $copy->getKey(), (int) $reservation->assigned_copy_id);
        $this->assertNull($reservation->queue_position);
        $this->assertSame('reserved', $copy->fresh()->status, 'A pinned copy must leave the open shelf immediately.');

        ActivityLog::query()->where('action_type', 'reservation.create')->firstOrFail();
        $this->assertSame(1, ReaderNotification::query()->where('event_type', 'reservation_created')->count());
    }

    public function test_scenario_two_all_copies_busy_puts_readers_in_an_ordered_queue(): void
    {
        $copy = BookCopy::factory()->create([
            'bibliographic_record_id' => $this->record->getKey(),
            'status' => 'issued',
        ]);
        Loan::factory()->create(['copy_id' => $copy->getKey(), 'user_id' => $this->adminUser->getKey()]);

        $first = $this->reservations->create($this->reader, $this->record);
        $second = $this->reservations->create($this->reader(), $this->record);
        $third = $this->reservations->create($this->reader(), $this->record);

        $insights = app(ReservationInsightService::class);
        $this->assertSame([1, 2, 3], [$insights->queuePosition($first), $insights->queuePosition($second), $insights->queuePosition($third)]);
        $this->assertSame(['queued', 'queued', 'queued'], [$first->status, $second->status, $third->status]);
        $this->assertNull($first->assigned_copy_id);
    }

    public function test_cancelling_a_queued_reservation_changes_computed_positions_without_rewriting_rows(): void
    {
        $copy = BookCopy::factory()->create(['bibliographic_record_id' => $this->record->getKey(), 'status' => 'issued']);
        Loan::factory()->create(['copy_id' => $copy->getKey(), 'user_id' => $this->adminUser->getKey()]);

        $first = $this->reservations->create($this->reader, $this->record);
        $secondReader = $this->reader();
        $second = $this->reservations->create($secondReader, $this->record);
        $third = $this->reservations->create($this->reader(), $this->record);

        $this->reservations->cancel($second, $secondReader, 'Reader no longer needs the book');

        $this->assertSame('cancelled', $second->fresh()->status);
        $insights = app(ReservationInsightService::class);
        $this->assertSame(1, $insights->queuePosition($first->fresh()));
        $this->assertSame(2, $insights->queuePosition($third->fresh()));
        $this->assertSame(3, $third->queue_sequence, 'Stable FIFO sequence must never be rewritten.');
    }

    public function test_confirm_then_ready_starts_the_pickup_countdown_and_notifies(): void
    {
        BookCopy::factory()->create(['bibliographic_record_id' => $this->record->getKey(), 'status' => 'available']);
        Setting::query()->where('key', 'reservation_manual_confirmation_required')->update(['value' => true]);
        $reservation = $this->reservations->create($this->reader, $this->record);

        $confirmed = $this->reservations->confirm($reservation, $this->adminUser);
        $this->assertSame('confirmed', $confirmed->status);

        $ready = $this->reservations->markReady($confirmed, $this->adminUser);
        $this->assertSame('ready_for_pickup', $ready->status);

        $lifespan = (int) Setting::valueFor('reservation_hold_days', 1);
        $this->assertSame(now()->addDays($lifespan)->toDateString(), $ready->expires_at->toDateString());

        $this->assertSame(1, ReaderNotification::query()->where('event_type', 'reservation_confirmed')->count());
        $this->assertSame(1, ReaderNotification::query()->where('event_type', 'reservation_ready')->count());
    }

    public function test_scenario_three_an_uncollected_hold_expires_and_frees_the_copy(): void
    {
        $copy = BookCopy::factory()->create(['bibliographic_record_id' => $this->record->getKey(), 'status' => 'available']);
        $reservation = $this->reservations->create($this->reader, $this->record);
        $reservation->fresh()->update(['expires_at' => now()->subDay()]);

        $stats = $this->reservations->sweepExpired();

        $this->assertSame(1, $stats['expired']);
        $this->assertSame('expired', $reservation->fresh()->status);
        $this->assertSame('available', $copy->fresh()->status, 'An expired hold must return the copy to the shelf.');
        $this->assertSame(1, ReaderNotification::query()->where('event_type', 'reservation_expired')->count());
    }

    public function test_an_expired_hold_passes_the_copy_to_the_next_reader_in_the_queue(): void
    {
        $copy = BookCopy::factory()->create(['bibliographic_record_id' => $this->record->getKey(), 'status' => 'available']);
        $firstHold = $this->reservations->create($this->reader, $this->record);

        // A second reader queues behind the held copy.
        $waiting = $this->reader();
        $queued = $this->reservations->create($waiting, $this->record);
        $this->assertNull($queued->assigned_copy_id);

        $firstHold->fresh()->update(['expires_at' => now()->subDay()]);
        $this->reservations->sweepExpired();

        $queued->refresh();
        $this->assertSame('ready_for_pickup', $queued->status);
        $this->assertSame((int) $copy->getKey(), (int) $queued->assigned_copy_id);
        $this->assertSame('reserved', $copy->fresh()->status);
    }

    public function test_duplicate_reservation_on_the_same_edition_is_rejected(): void
    {
        BookCopy::factory()->create(['bibliographic_record_id' => $this->record->getKey(), 'status' => 'available']);
        $this->reservations->create($this->reader, $this->record);

        try {
            $this->reservations->create($this->reader, $this->record);
            $this->fail('A reader must not hold the same edition twice.');
        } catch (CirculationException $exception) {
            $this->assertSame('reservation_duplicate', $exception->reasonCode);
        }
    }

    public function test_reservation_limit_is_enforced(): void
    {
        $limit = (int) Setting::valueFor('max_active_reservations', 3);

        for ($i = 0; $i < $limit; $i++) {
            $record = BibliographicRecord::factory()->create();
            BookCopy::factory()->create(['bibliographic_record_id' => $record->getKey(), 'status' => 'available']);
            $this->reservations->create($this->reader, $record);
        }

        BookCopy::factory()->create(['bibliographic_record_id' => $this->record->getKey(), 'status' => 'available']);
        try {
            $this->reservations->create($this->reader, $this->record);
            $this->fail('The reservation limit must be enforced.');
        } catch (CirculationException $exception) {
            $this->assertSame('reservation_limit_reached', $exception->reasonCode);
        }
    }

    public function test_a_reader_with_overdue_items_cannot_reserve(): void
    {
        $overdueCopy = BookCopy::factory()->create(['status' => 'issued']);
        Loan::factory()->create([
            'user_id' => $this->reader->getKey(),
            'copy_id' => $overdueCopy->getKey(),
            'status' => 'overdue',
            'due_at' => now()->subDays(2),
        ]);
        BookCopy::factory()->create(['bibliographic_record_id' => $this->record->getKey(), 'status' => 'available']);

        try {
            $this->reservations->create($this->reader, $this->record);
            $this->fail('A reader with overdue items must not be able to reserve.');
        } catch (CirculationException $exception) {
            $this->assertSame('reader_has_overdue', $exception->reasonCode);
        }
    }

    public function test_a_blocked_reader_cannot_reserve(): void
    {
        ReaderProfile::forUser($this->reader)->update(['status' => 'blocked', 'block_reason' => 'Lost book unresolved']);
        BookCopy::factory()->create(['bibliographic_record_id' => $this->record->getKey(), 'status' => 'available']);

        try {
            $this->reservations->create($this->reader, $this->record);
            $this->fail('A blocked reader must not be able to reserve.');
        } catch (CirculationException $exception) {
            $this->assertSame('reader_blocked', $exception->reasonCode);
        }
    }

    public function test_a_reader_cannot_cancel_someone_elses_reservation(): void
    {
        BookCopy::factory()->create(['bibliographic_record_id' => $this->record->getKey(), 'status' => 'available']);
        $reservation = $this->reservations->create($this->reader, $this->record);

        try {
            $this->reservations->cancel($reservation, $this->reader(), 'Trying to cancel a stranger\'s hold');
            $this->fail('A reader must not cancel another reader\'s hold.');
        } catch (CirculationException $exception) {
            $this->assertSame('reservation_not_own', $exception->reasonCode);
        }
    }

    public function test_cancelling_a_pinned_hold_returns_the_copy_to_the_shelf(): void
    {
        $copy = BookCopy::factory()->create(['bibliographic_record_id' => $this->record->getKey(), 'status' => 'available']);
        $reservation = $this->reservations->create($this->reader, $this->record);
        $this->assertSame('reserved', $copy->fresh()->status);

        $this->reservations->cancel($reservation, $this->adminUser, 'Cancelled at the desk', byStaff: true);

        $this->assertSame('available', $copy->fresh()->status);
        $this->assertSame(1, ReaderNotification::query()->where('event_type', 'reservation_cancelled')->count());
    }

    public function test_reservation_status_labels_exist_in_every_locale(): void
    {
        foreach (['ru', 'kk', 'en'] as $locale) {
            app()->setLocale($locale);
            foreach (Reservation::STATUSES as $status) {
                $label = __('librarian.reservations.statuses.'.$status);
                $this->assertNotSame('librarian.reservations.statuses.'.$status, $label, "Missing {$locale} label for reservation status {$status}");
            }
        }
        app()->setLocale('ru');
    }
}
