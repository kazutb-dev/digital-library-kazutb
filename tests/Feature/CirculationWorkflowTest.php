<?php

namespace Tests\Feature;

use App\Exceptions\CirculationException;
use App\Models\ActivityLog;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\Fine;
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
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

/**
 * Circulation rules from Master.md §14 and the reference scenario §31.2.
 */
class CirculationWorkflowTest extends TestCase
{
    use BuildsAdminControlPlane;

    private CirculationService $circulation;

    private User $reader;

    private BookCopy $copy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();

        $this->circulation = app(CirculationService::class);
        $this->reader = $this->makeControlPlaneUser('member');
        ReaderProfile::forUser($this->reader);
        $this->copy = BookCopy::factory()->create(['status' => 'available']);
    }

    public function test_issue_creates_loan_marks_copy_and_audits(): void
    {
        $loan = $this->circulation->issue($this->reader, $this->copy, $this->adminUser);

        $this->assertSame('active', $loan->status);
        $this->assertSame((int) $this->reader->getKey(), (int) $loan->user_id);
        $this->assertSame('issued', $this->copy->fresh()->status);
        $this->assertSame(1, (int) $this->copy->fresh()->issue_count);

        // §9.3 — the due date honours the copy-count scale, not a flat period.
        // A factory copy is the only one of its edition, so it is the scarce tier.
        $expected = now()->addDays(app(LoanPeriodPolicy::class)->daysForCopy($this->copy))->endOfDay();
        $this->assertSame($expected->toDateString(), $loan->due_at->toDateString());

        ActivityLog::query()
            ->where('action_type', 'circulation.issue')
            ->where('entity_id', (string) $loan->getKey())
            ->firstOrFail();

        $this->assertDatabaseHas('copy_history', [
            'copy_id' => $this->copy->getKey(),
            'event_type' => 'issued',
        ]);
    }

    public function test_reading_room_copy_cannot_be_issued_for_home_use(): void
    {
        $copy = BookCopy::factory()->create(['status' => 'available', 'access_restriction' => 'reading_room']);

        $this->expectException(CirculationException::class);
        $this->circulation->issue($this->reader, $copy, $this->adminUser);
    }

    public function test_issue_is_blocked_beyond_the_loan_limit(): void
    {
        $limit = (int) Setting::valueFor('max_active_loans', 5);
        for ($i = 0; $i < $limit; $i++) {
            $this->circulation->issue($this->reader, BookCopy::factory()->create(['status' => 'available']), $this->adminUser);
        }

        $this->expectException(CirculationException::class);
        $this->circulation->issue($this->reader, $this->copy, $this->adminUser);
    }

    public function test_issue_is_blocked_for_a_reader_with_overdue_items(): void
    {
        $overdueCopy = BookCopy::factory()->create(['status' => 'issued']);
        Loan::factory()->create([
            'user_id' => $this->reader->getKey(),
            'copy_id' => $overdueCopy->getKey(),
            'status' => 'overdue',
            'due_at' => now()->subDays(3),
        ]);

        $this->expectException(CirculationException::class);
        $this->circulation->issue($this->reader, $this->copy, $this->adminUser);
    }

    public function test_override_bypasses_limits_only_with_permission_and_is_audited_separately(): void
    {
        $overdueCopy = BookCopy::factory()->create(['status' => 'issued']);
        Loan::factory()->create([
            'user_id' => $this->reader->getKey(),
            'copy_id' => $overdueCopy->getKey(),
            'status' => 'overdue',
            'due_at' => now()->subDays(3),
        ]);

        // A librarian lacks circulation.override_limits (admin-only).
        $librarian = $this->makeControlPlaneUser('librarian');
        try {
            $this->circulation->issue($this->reader, $this->copy, $librarian, override: true);
            $this->fail('A librarian must not be able to override circulation limits.');
        } catch (CirculationException $exception) {
            $this->assertSame('override_not_permitted', $exception->reasonCode);
        }

        // The admin may, and the override is recorded as its own audit event.
        $loan = $this->circulation->issue($this->reader, $this->copy, $this->adminUser, override: true, overrideReason: 'Coursework deadline, head of department approved');
        $this->assertSame('active', $loan->status);

        $override = ActivityLog::query()
            ->where('action_type', 'circulation.override_limits')
            ->firstOrFail();
        $this->assertStringContainsString('head of department', (string) $override->reason);
    }

    public function test_a_copy_held_for_another_reader_cannot_be_issued(): void
    {
        $otherReader = $this->makeControlPlaneUser('member');
        Reservation::factory()->create([
            'user_id' => $otherReader->getKey(),
            'bibliographic_record_id' => $this->copy->bibliographic_record_id,
            'assigned_copy_id' => $this->copy->getKey(),
            'status' => 'ready_for_pickup',
        ]);
        $this->copy->update(['status' => 'reserved']);

        try {
            $this->circulation->issue($this->reader, $this->copy, $this->adminUser);
            $this->fail('A copy reserved for another reader must not be issued.');
        } catch (CirculationException $exception) {
            $this->assertSame('copy_reserved_for_other', $exception->reasonCode);
        }
    }

    public function test_return_closes_the_loan_and_frees_the_copy(): void
    {
        $loan = $this->circulation->issue($this->reader, $this->copy, $this->adminUser);

        $returned = $this->circulation->returnCopy($this->copy, $this->adminUser, 'good');

        $this->assertSame('returned', $returned->status);
        $this->assertNotNull($returned->returned_at);
        $this->assertSame('available', $this->copy->fresh()->status);
        $this->assertSame(0, Fine::query()->count(), 'An on-time return must not charge a fine.');

        ActivityLog::query()
            ->where('action_type', 'circulation.return')
            ->where('entity_id', (string) $loan->getKey())
            ->firstOrFail();
    }

    public function test_overdue_return_charges_the_configured_fine_per_day(): void
    {
        $loan = $this->circulation->issue($this->reader, $this->copy, $this->adminUser);
        $loan->update(['due_at' => now()->subDays(4)]);

        $this->circulation->returnCopy($this->copy, $this->adminUser, 'good');

        $rate = (int) Setting::valueFor('fine_per_overdue_day', 100);
        $fine = Fine::query()->where('reason', 'overdue')->firstOrFail();
        $this->assertSame((float) (4 * $rate), (float) $fine->amount);
        $this->assertSame('pending', $fine->status);
        $this->assertSame((int) $this->reader->getKey(), (int) $fine->user_id);
    }

    public function test_lost_and_damaged_returns_take_the_copy_out_of_circulation(): void
    {
        $this->circulation->issue($this->reader, $this->copy, $this->adminUser);
        $this->circulation->returnCopy($this->copy, $this->adminUser, 'damaged', 'lost', 15000.0, 'Reader reported the book lost');

        $this->assertSame('lost', $this->copy->fresh()->status);
        $lostFine = Fine::query()->where('reason', 'lost')->firstOrFail();
        $this->assertSame(15000.0, (float) $lostFine->amount);

        $damagedCopy = BookCopy::factory()->create(['status' => 'available']);
        // An unresolved loss now correctly blocks further issues, so the
        // independent damage scenario uses another reader.
        $damagedReader = $this->makeControlPlaneUser('member');
        ReaderProfile::forUser($damagedReader);
        $this->circulation->issue($damagedReader, $damagedCopy, $this->adminUser);
        $this->circulation->returnCopy($damagedCopy, $this->adminUser, 'damaged', 'damaged', 2000.0, 'Water damage on pages 10-15');

        $this->assertSame('under_repair', $damagedCopy->fresh()->status);
        $this->assertSame('damaged', $damagedCopy->fresh()->condition);
    }

    public function test_renewal_is_allowed_once_and_blocked_when_overdue_or_reserved(): void
    {
        $loan = $this->circulation->issue($this->reader, $this->copy, $this->adminUser);
        $originalDue = $loan->due_at->copy();

        $renewed = $this->circulation->renew($loan, $this->reader);
        $this->assertSame(1, $renewed->renewal_count);
        $this->assertTrue($renewed->due_at->greaterThan($originalDue));

        // Second renewal exceeds the limit.
        try {
            $this->circulation->renew($renewed, $this->reader);
            $this->fail('A loan must not be renewable twice.');
        } catch (CirculationException $exception) {
            $this->assertSame('renewal_limit_reached', $exception->reasonCode);
        }

        // Overdue blocks renewal.
        $secondCopy = BookCopy::factory()->create(['status' => 'available']);
        $otherReader = $this->makeControlPlaneUser('member');
        $secondLoan = $this->circulation->issue($otherReader, $secondCopy, $this->adminUser);
        $secondLoan->update(['due_at' => now()->subDay()]);
        try {
            $this->circulation->renew($secondLoan->fresh(), $otherReader);
            $this->fail('An overdue loan must not be renewable.');
        } catch (CirculationException $exception) {
            $this->assertSame('renewal_overdue', $exception->reasonCode);
        }
    }

    public function test_renewal_is_blocked_when_someone_is_waiting_for_the_edition(): void
    {
        $loan = $this->circulation->issue($this->reader, $this->copy, $this->adminUser);

        $waiting = $this->makeControlPlaneUser('member');
        Reservation::factory()->create([
            'user_id' => $waiting->getKey(),
            'bibliographic_record_id' => $this->copy->bibliographic_record_id,
            'status' => 'pending',
            'queue_position' => 1,
        ]);

        try {
            $this->circulation->renew($loan, $this->reader);
            $this->fail('A loan must not be renewable while a reader waits for the edition.');
        } catch (CirculationException $exception) {
            $this->assertSame('renewal_reserved', $exception->reasonCode);
        }
    }

    public function test_a_reader_cannot_renew_another_readers_loan(): void
    {
        $loan = $this->circulation->issue($this->reader, $this->copy, $this->adminUser);
        $stranger = $this->makeControlPlaneUser('member');

        try {
            $this->circulation->renew($loan, $stranger);
            $this->fail('A reader must not renew a loan that is not theirs.');
        } catch (CirculationException $exception) {
            $this->assertSame('renewal_not_own', $exception->reasonCode);
        }
    }

    public function test_blocked_reader_cannot_borrow(): void
    {
        ReaderProfile::forUser($this->reader)->update(['status' => 'blocked', 'block_reason' => 'Unresolved debt']);

        try {
            $this->circulation->issue($this->reader, $this->copy, $this->adminUser);
            $this->fail('A blocked reader must not be able to borrow.');
        } catch (CirculationException $exception) {
            $this->assertSame('reader_blocked', $exception->reasonCode);
        }
    }

    public function test_sweep_marks_overdue_loans_and_notifies_once(): void
    {
        $loan = $this->circulation->issue($this->reader, $this->copy, $this->adminUser);
        $loan->update(['due_at' => now()->subDays(2)]);

        $stats = $this->circulation->sweepOverdue();

        $this->assertSame(1, $stats['overdue']);
        $this->assertSame('overdue', $loan->fresh()->status);
        $this->assertSame('overdue', $this->copy->fresh()->status);
        $this->assertSame(1, ReaderNotification::query()->where('event_type', 'loan_overdue')->count());

        // A second sweep must not re-flag the same loan.
        $this->assertSame(0, $this->circulation->sweepOverdue()['overdue']);
    }

    public function test_due_soon_warning_is_sent_once_per_loan(): void
    {
        $loan = $this->circulation->issue($this->reader, $this->copy, $this->adminUser);
        $loan->update(['due_at' => now()->addDay()]);

        $this->assertSame(1, $this->circulation->sweepOverdue()['due_soon']);
        $this->assertSame(0, $this->circulation->sweepOverdue()['due_soon'], 'A reader must not be warned twice about the same loan.');
    }

    public function test_reader_summary_reports_limits_debts_and_blocks(): void
    {
        $this->circulation->issue($this->reader, $this->copy, $this->adminUser);
        Fine::factory()->create(['user_id' => $this->reader->getKey(), 'amount' => 750, 'status' => 'pending']);

        $summary = $this->circulation->readerSummary($this->reader);

        $this->assertSame(1, $summary['open_loans']->count());
        $this->assertSame(750.0, $summary['pending_fines_total']);
        $this->assertFalse($summary['blocked']);
        $this->assertSame((int) Setting::valueFor('max_active_loans', 5) - 1, $summary['loans_remaining']);
    }

    public function test_returning_a_copy_hands_it_to_the_next_reader_in_the_queue(): void
    {
        // The only copy of this edition is on loan.
        $this->circulation->issue($this->reader, $this->copy, $this->adminUser);

        $waiting = $this->makeControlPlaneUser('member');
        ReaderProfile::forUser($waiting);
        $record = BibliographicRecord::query()->findOrFail($this->copy->bibliographic_record_id);
        $reservation = app(ReservationQueueService::class)->create($waiting, $record);

        // No copy was free, so the reader joined the queue (§13.2 scenario 2).
        $this->assertNull($reservation->assigned_copy_id);
        $this->assertSame('queued', $reservation->status);
        $this->assertSame(1, app(ReservationInsightService::class)->queuePosition($reservation));

        $this->circulation->returnCopy($this->copy, $this->adminUser, 'good');

        $reservation->refresh();
        $this->assertSame('ready_for_pickup', $reservation->status);
        $this->assertSame((int) $this->copy->getKey(), (int) $reservation->assigned_copy_id);
        $this->assertSame('reserved', $this->copy->fresh()->status, 'A queued copy must not fall back to the open shelf.');
        $this->assertSame(1, ReaderNotification::query()->where('event_type', 'reservation_ready')->count());
    }
}
