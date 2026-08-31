<?php

namespace Tests\Feature;

use App\Exceptions\CirculationException;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\CirculationIncidentCase;
use App\Models\Catalog\Fine;
use App\Models\Catalog\Loan;
use App\Models\Catalog\ReaderProfile;
use App\Models\Catalog\ReplacementCandidate;
use App\Models\Fund;
use App\Models\User;
use App\Services\Catalog\CirculationService;
use App\Services\Catalog\IncidentCaseService;
use App\Services\Catalog\MachineCodeService;
use App\Services\Catalog\ReservationInsightService;
use App\Services\Catalog\ReservationQueueService;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

/**
 * Librarian-facing circulation acceptance chains.
 *
 * This suite intentionally uses the explicit SQLite :memory: schema builder
 * from BuildsAdminControlPlane. It never uses RefreshDatabase or a database
 * reset command and therefore cannot write to the runtime PostgreSQL database.
 */
class CirculationOperationsChainTest extends TestCase
{
    use BuildsAdminControlPlane;

    private CirculationService $circulation;

    private ReservationQueueService $reservations;

    private IncidentCaseService $incidents;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();

        foreach ([
            'database/migrations/2026_08_28_100000_create_marc_recovery_model.php',
            'database/migrations/2026_08_28_100100_extend_catalogue_for_marc_recovery.php',
            'database/migrations/2026_08_29_120000_create_acquisition_batches_and_safe_number_sequences.php',
        ] as $path) {
            (require base_path($path))->up();
        }

        $this->circulation = app(CirculationService::class);
        $this->reservations = app(ReservationQueueService::class);
        $this->incidents = app(IncidentCaseService::class);
    }

    public function test_reader_scan_reservation_issue_and_return_are_one_copy_accountable_chain(): void
    {
        $this->assertSame('testing', app()->environment());
        $this->assertSame('sqlite', DB::connection()->getDriverName());
        $this->assertSame(':memory:', DB::connection()->getDatabaseName());

        $staff = $this->makeControlPlaneUser('librarian');
        $staffProfile = ReaderProfile::forUser($staff);
        $this->assertSame('staff', $staffProfile->category);
        $this->assertSame('librarian', $staff->fresh()->effectiveRole());
        $this->assertSame('staff', ReaderProfile::forUser($staff->fresh())->category);

        [$reader, $profile] = $this->reader('Айдана Қасымова');
        $profile->update(['limits_override' => ['max_active_loans' => 2]]);
        $record = BibliographicRecord::factory()->create([
            'title' => 'Қазақ кітапханасының тәжірибесі',
            'primary_author' => 'Әбілова А.',
            'isbn' => '978-601-1234-56-7',
            'language' => 'kaz',
        ]);
        $copy = BookCopy::factory()->create([
            'bibliographic_record_id' => $record->getKey(),
            'inventory_number' => 'CHAIN-INV-001',
            'barcode' => 'CHAINBC001',
            'status' => 'available',
            'condition' => 'good',
        ]);

        $reservation = $this->reservations->create($reader, $record, $reader);
        $this->assertSame('ready_for_pickup', $reservation->status);
        $this->assertSame($copy->getKey(), $reservation->assigned_copy_id);
        $this->assertSame('reserved', $copy->fresh()->status);
        $this->assertDatabaseHas('reader_notifications', [
            'user_id' => $reader->getKey(),
            'event_type' => 'reservation_ready',
        ]);

        foreach ([$profile->ticket_number, $profile->barcode] as $readerCode) {
            $this->signInToLibraryAs($this->adminUser)
                ->getJson(route('librarian.circulation.reader-lookup', ['q' => $readerCode]))
                ->assertOk()
                ->assertJsonPath('data.0.id', $reader->getKey());
        }

        $qr = app(MachineCodeService::class)->qr((string) $profile->barcode);
        $this->assertStringContainsString('<svg', $qr);
        $this->assertStringNotContainsString($reader->name, $qr);
        $this->assertStringNotContainsString($reader->email, $qr);

        $this->signInToLibraryAs($this->adminUser)
            ->getJson(route('librarian.circulation.copy-lookup', ['q' => $record->isbn]))
            ->assertOk()
            ->assertJsonPath('data', null)
            ->assertJsonPath('match_type', 'isbn')
            ->assertJsonPath('editions.0.id', $record->getKey());

        foreach ([$copy->inventory_number, $copy->barcode] as $copyCode) {
            $this->signInToLibraryAs($this->adminUser)
                ->getJson(route('librarian.circulation.copy-lookup', ['q' => $copyCode]))
                ->assertOk()
                ->assertJsonPath('data.id', $copy->getKey());
        }

        // ISBN identifies the edition and must never silently select a copy.
        $this->signInToLibraryAs($this->adminUser)
            ->from(route('librarian.circulation.issue', ['reader' => $reader->getKey()]))
            ->post(route('librarian.circulation.issue.store'), [
                'reader_id' => $reader->getKey(),
                'copy_code' => $record->isbn,
            ])
            ->assertSessionHasErrors('copy_code');
        $this->assertSame(0, Loan::query()->count());
        $this->assertSame('ready_for_pickup', $reservation->fresh()->status);

        // Inventory number resolves the physical copy and fulfils its hold.
        $this->signInToLibraryAs($this->adminUser)
            ->post(route('librarian.circulation.issue.store'), [
                'reader_id' => $reader->getKey(),
                'copy_code' => $copy->inventory_number,
            ])
            ->assertRedirect();

        $loan = Loan::query()->where('user_id', $reader->getKey())->firstOrFail();
        $this->assertSame('active', $loan->status);
        $this->assertSame('issued', $copy->fresh()->status);
        $this->assertSame('fulfilled', $reservation->fresh()->status);
        $this->assertSame(1, $this->circulation->readerSummary($reader)['open_loans']->count());
        $this->assertSame(1, $this->circulation->readerSummary($reader)['loans_remaining']);

        // Barcode identifies that same physical copy at the return desk.
        $this->signInToLibraryAs($this->adminUser)
            ->post(route('librarian.circulation.return.store'), [
                'copy_code' => $copy->barcode,
                'condition_on_return' => 'unchanged',
                'incident' => 'none',
            ])
            ->assertRedirect();
        $this->assertSame('returned', $loan->fresh()->status);
        $this->assertNotNull($loan->fresh()->returned_at);
        $this->assertSame('available', $copy->fresh()->status);

        // Barcode also works for a subsequent issue; no edition-level shortcut
        // is involved in either physical operation.
        [$secondReader] = $this->reader('Марат Омаров');
        $this->signInToLibraryAs($this->adminUser)
            ->post(route('librarian.circulation.issue.store'), [
                'reader_id' => $secondReader->getKey(),
                'copy_code' => $copy->barcode,
            ])
            ->assertRedirect();
        $this->assertSame('issued', $copy->fresh()->status);
        $this->assertDatabaseHas('loans', [
            'copy_id' => $copy->getKey(),
            'user_id' => $secondReader->getKey(),
            'status' => 'active',
        ]);

        $this->assertAuditActions([
            'reservation.create',
            'reservation.ready',
            'reservation.fulfill',
            'circulation.issue',
            'circulation.return',
        ]);
        foreach (['reservation.ready', 'reservation.fulfill', 'circulation.issue', 'circulation.return'] as $action) {
            $this->assertTransitionAudit($action);
        }
        $this->assertDatabaseHas('copy_history', ['copy_id' => $copy->getKey(), 'event_type' => 'issued']);
        $this->assertDatabaseHas('copy_history', ['copy_id' => $copy->getKey(), 'event_type' => 'returned']);
    }

    public function test_return_fifo_expiry_next_reader_ready_and_issue_stay_in_one_chain(): void
    {
        [$borrower] = $this->reader('Initial Borrower');
        [$firstReader] = $this->reader('First Queue Reader');
        [$secondReader] = $this->reader('Second Queue Reader');
        $record = BibliographicRecord::factory()->create(['title' => 'FIFO Chain Book']);
        $copy = BookCopy::factory()->create([
            'bibliographic_record_id' => $record->getKey(),
            'inventory_number' => 'CHAIN-FIFO-001',
            'barcode' => 'CHAINFIFO001',
            'status' => 'available',
        ]);

        $loan = $this->circulation->issue($borrower, $copy, $this->adminUser);
        $first = $this->reservations->create($firstReader, $record);
        $second = $this->reservations->create($secondReader, $record);
        $this->assertSame(['queued', 'queued'], [$first->status, $second->status]);
        $this->assertSame(1, app(ReservationInsightService::class)->queuePosition($first));
        $this->assertSame(2, app(ReservationInsightService::class)->queuePosition($second));

        $returned = $this->circulation->returnCopy($copy, $this->adminUser, 'unchanged');
        $this->assertSame('returned', $returned->status);
        $this->assertSame('ready_for_pickup', $first->fresh()->status);
        $this->assertSame('queued', $second->fresh()->status);
        $this->assertSame($copy->getKey(), $first->fresh()->assigned_copy_id);
        $this->assertSame('reserved', $copy->fresh()->status);

        $first->fresh()->update(['expires_at' => now()->subMinute()]);
        $this->assertSame(['expired' => 1], $this->reservations->sweepExpired());
        $this->assertSame('expired', $first->fresh()->status);
        $this->assertSame('ready_for_pickup', $second->fresh()->status);
        $this->assertSame($copy->getKey(), $second->fresh()->assigned_copy_id);
        $this->assertSame('reserved', $copy->fresh()->status);

        $collected = $this->circulation->issue($secondReader, $copy, $this->adminUser);
        $this->assertSame('active', $collected->status);
        $this->assertSame('fulfilled', $second->fresh()->status);
        $this->assertSame('issued', $copy->fresh()->status);
        $this->assertDatabaseHas('reader_notifications', [
            'user_id' => $firstReader->getKey(),
            'event_type' => 'reservation_expired',
        ]);
        $this->assertDatabaseHas('reader_notifications', [
            'user_id' => $secondReader->getKey(),
            'event_type' => 'reservation_ready',
        ]);
        $this->assertDatabaseHas('reservation_history', [
            'reservation_id' => $second->getKey(),
            'event_type' => 'reservation.fulfilled',
            'to_status' => 'fulfilled',
        ]);
        $this->assertTransitionAudit('reservation.expire');
        $this->assertSame($loan->getKey(), $returned->getKey());
    }

    public function test_overdue_is_visible_to_reader_and_librarian_and_blocks_issue_and_reservation(): void
    {
        [$reader] = $this->reader('Overdue Reader');
        $record = BibliographicRecord::factory()->create(['title' => 'Overdue Chain Book']);
        $copy = BookCopy::factory()->create([
            'bibliographic_record_id' => $record->getKey(),
            'inventory_number' => 'CHAIN-OVERDUE-001',
            'barcode' => 'CHAINOVERDUE001',
            'status' => 'available',
        ]);
        $loan = $this->circulation->issue($reader, $copy, $this->adminUser);
        $loan->update(['due_at' => now()->subDays(3)]);

        $this->assertSame(['overdue' => 1, 'due_soon' => 0], $this->circulation->sweepOverdue());
        $this->assertSame('overdue', $loan->fresh()->status);
        $this->assertSame('overdue', $copy->fresh()->status);
        $this->assertTrue($this->circulation->readerSummary($reader)['overdue_blocked']);
        $this->assertDatabaseHas('reader_notifications', [
            'user_id' => $reader->getKey(),
            'event_type' => 'loan_overdue',
        ]);

        $this->signInToLibraryAs($this->adminUser)
            ->get(route('librarian.circulation'))
            ->assertOk()
            ->assertSee($reader->name)
            ->assertSee($copy->inventory_number);
        $this->signInToLibraryAs($this->adminUser)
            ->get(route('librarian.circulation.issue', ['reader' => $reader->getKey()]))
            ->assertOk()
            ->assertSee(__('librarian.circulation.blocked_overdue'));
        $this->signInToLibraryAs($reader)
            ->get(route('member.dashboard'))
            ->assertOk()
            ->assertSee(__('librarian.member.dashboard.overdue_notice'));

        $otherRecord = BibliographicRecord::factory()->create(['title' => 'Blocked Reservation Book']);
        $otherCopy = BookCopy::factory()->create([
            'bibliographic_record_id' => $otherRecord->getKey(),
            'status' => 'available',
        ]);
        $this->assertCirculationReason(
            fn () => $this->reservations->create($reader, $otherRecord),
            'reader_has_overdue',
        );
        $this->assertCirculationReason(
            fn () => $this->circulation->issue($reader, $otherCopy, $this->adminUser),
            'reader_has_overdue',
        );
        $this->assertAuditActions(['circulation.issue', 'circulation.overdue_marked', 'circulation.overdue_sweep']);
        $this->assertTransitionAudit('circulation.overdue_marked');
    }

    public function test_damage_assessment_decision_copy_history_and_audit_form_one_case_chain(): void
    {
        [$reader] = $this->reader('Damage Reader');
        $copy = BookCopy::factory()->create([
            'inventory_number' => 'CHAIN-DAMAGE-001',
            'barcode' => 'CHAINDAMAGE001',
            'status' => 'available',
            'condition' => 'good',
        ]);
        $loan = $this->circulation->issue($reader, $copy, $this->adminUser);
        $returned = $this->circulation->returnCopy(
            $copy,
            $this->adminUser,
            'severe_damage',
            'damaged',
            null,
            'Water damage on pages 10–25',
            [
                'damage_severity' => 'severe',
                'damage_description' => 'Water damage on pages 10–25',
                'preliminary_action' => 'repair',
                'open_case' => true,
                'open_replacement_case' => false,
                'condition_after' => 'severe_damage',
            ],
        );

        $case = CirculationIncidentCase::query()->where('loan_id', $loan->getKey())->firstOrFail();
        $this->assertSame('returned', $returned->status);
        $this->assertSame('damaged', $copy->fresh()->condition);
        $this->assertSame('under_repair', $copy->fresh()->status);
        $this->assertSame('severe', $case->damage_severity);
        $this->assertSame('Water damage on pages 10–25', $case->damage_description);
        $this->assertSame('good', $case->condition_before);
        $this->assertSame('severe_damage', $case->condition_after);

        $resolved = $this->incidents->resolveWithoutReplacement(
            $case,
            $this->adminUser,
            'write_off',
            'Conservation review found the copy irreparable',
            false,
            '2026-08-29',
            'ACT-INC-2026-001',
        );
        $this->assertSame('resolved', $resolved->status);
        $this->assertSame('written_off', $copy->fresh()->status);
        $history = $copy->history()->where('event_type', 'write_off')->firstOrFail();
        $this->assertSame($case->getKey(), $history->details['incident_case_id']);
        $this->assertSame('under_repair', $history->details['old']['status']);
        $this->assertSame('written_off', $history->details['new']['status']);
        $this->assertNotNull($history->details['ksu_withdrawal_entry_id']);
        $this->assertSame($this->adminUser->getKey(), $history->actor_id);
        $this->assertAuditActions([
            'circulation.return',
            'incident.opened',
            'incident.damage_assessed',
            'ksu.withdrawal.created',
            'incident.resolved',
        ]);
        $this->assertTransitionAudit('incident.resolved');
    }

    public function test_damage_case_repair_decision_records_the_physical_copy_transition(): void
    {
        [$reader] = $this->reader('Repair Reader');
        $copy = BookCopy::factory()->create([
            'inventory_number' => 'CHAIN-REPAIR-001',
            'barcode' => 'CHAINREPAIR001',
            'status' => 'available',
            'condition' => 'good',
        ]);
        $loan = $this->circulation->issue($reader, $copy, $this->adminUser);
        $this->circulation->returnCopy(
            $copy,
            $this->adminUser,
            'moderate_damage',
            'damaged',
            null,
            'Binding detached during normal inspection',
            [
                'damage_severity' => 'moderate',
                'damage_description' => 'Binding detached during normal inspection',
                // Initial desk assessment keeps it visible until the incident
                // decision explicitly sends the physical copy to repair.
                'preliminary_action' => 'return_to_fund',
                'open_case' => true,
                'condition_after' => 'moderate_damage',
            ],
        );
        $case = CirculationIncidentCase::query()->where('loan_id', $loan->getKey())->firstOrFail();
        $this->assertSame('available', $copy->fresh()->status);

        $this->incidents->resolveWithoutReplacement(
            $case,
            $this->adminUser,
            'repair',
            'Bindery accepted the copy for repair',
        );

        $this->assertSame('under_repair', $copy->fresh()->status);
        $history = $copy->history()->where('event_type', 'sent_to_repair')->firstOrFail();
        $this->assertSame('available', $history->details['old_status']);
        $this->assertSame('under_repair', $history->details['new_status']);
        $this->assertSame('repair', $history->details['resolution_type']);
        $this->assertSame($case->getKey(), $history->details['incident_case_id']);
        $this->assertTransitionAudit('incident.resolved');
    }

    public function test_loss_replacement_preserves_original_resolves_restriction_and_is_fully_audited(): void
    {
        [$reader] = $this->reader('Loss Reader');
        $record = BibliographicRecord::factory()->create([
            'title' => 'Replacement Chain Book',
            'primary_author' => 'Author A.',
            'isbn' => '978-601-1234-56-7',
            'language' => 'kaz',
        ]);
        $lostCopy = BookCopy::factory()->create([
            'bibliographic_record_id' => $record->getKey(),
            'inventory_number' => 'CHAIN-LOST-001',
            'barcode' => 'CHAINLOST001',
            'status' => 'available',
            'condition' => 'good',
        ]);
        $this->circulation->issue($reader, $lostCopy, $this->adminUser);
        $this->circulation->returnCopy(
            $lostCopy,
            $this->adminUser,
            'lost',
            'lost',
            3000,
            'Reader reported the physical copy lost',
        );

        $case = CirculationIncidentCase::query()->where('original_copy_id', $lostCopy->getKey())->firstOrFail();
        $fine = Fine::query()->whereKey($case->fine_id)->firstOrFail();
        $this->assertSame('lost', $lostCopy->fresh()->status);
        $this->assertSame('awaiting_reader', $case->status);
        $this->assertSame('pending', $fine->status);

        $spare = BookCopy::factory()->create(['status' => 'available']);
        $this->assertCirculationReason(
            fn () => $this->circulation->issue($reader, $spare, $this->adminUser),
            'reader_has_open_incident',
        );

        $candidate = $this->incidents->propose($case, $reader, [
            'bibliographic_record_id' => $record->getKey(),
            'isbn' => $record->isbn,
            'author' => $record->primary_author,
            'title' => $record->title,
            'publisher' => $record->publisher,
            'publication_year' => $record->publication_year,
            'language' => $record->language,
            'resource_type' => $record->resource_type,
            'udc_code' => $record->udc_code,
            'content_description' => $record->annotation,
            'copy_condition' => 'new',
        ]);
        $criteria = collect([...ReplacementCandidate::REQUIRED_CRITERIA, 'value_comparable', 'complete_set'])
            ->mapWithKeys(fn (string $criterion): array => [$criterion => true])
            ->all();
        $candidate = $this->incidents->review($candidate, $this->adminUser, $criteria, 'Exact usable replacement');
        $case = $this->incidents->decide(
            $candidate,
            $this->adminUser,
            'approve',
            'Exact edition accepted after physical review',
            resolutionType: 'replacement',
            fineRemains: false,
        );
        $branch = Branch::query()->where('is_active', true)->firstOrFail();
        $approvedFund = Fund::query()->where('branch_id', $branch->getKey())->firstOrFail();
        $foreignFund = Fund::query()
            ->whereNotNull('branch_id')
            ->where('branch_id', '!=', $branch->getKey())
            ->firstOrFail();
        $unrelatedRecord = BibliographicRecord::factory()->create([
            'title' => 'Unrelated Record Must Never Close Replacement Case',
        ]);
        $registration = [
            'branch_id' => $branch->getKey(),
            'fund_id' => $approvedFund->getKey(),
            'storage_sigla' => $branch->code,
            'shelf_location' => 'REPL-01',
            'condition' => 'new',
            'registration_date' => today(),
        ];

        try {
            $this->incidents->registerReplacement($case, $this->adminUser, $registration + [
                'inventory_number' => 'CHAIN-WRONG-RECORD-001',
                'barcode' => 'CHAINWRONGRECORD001',
                'bibliographic_record_id' => $unrelatedRecord->getKey(),
            ]);
            $this->fail('Registration must not override the approved replacement record.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('bibliographic_record_id', $exception->errors());
        }
        $this->assertSame('awaiting_registration', $case->fresh()->status);
        $this->assertNull($case->fresh()->replacement_copy_id);
        $this->assertFalse(BookCopy::query()->where('inventory_number', 'CHAIN-WRONG-RECORD-001')->exists());

        try {
            $this->incidents->registerReplacement($case, $this->adminUser, [
                ...$registration,
                'inventory_number' => 'CHAIN-WRONG-FUND-001',
                'barcode' => 'CHAINWRONGFUND001',
                'bibliographic_record_id' => $record->getKey(),
                'fund_id' => $foreignFund->getKey(),
            ]);
            $this->fail('Registration must not link a replacement to a fund from another branch.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('fund_id', $exception->errors());
        }
        $this->assertSame('awaiting_registration', $case->fresh()->status);
        $this->assertNull($case->fresh()->replacement_copy_id);
        $this->assertFalse(BookCopy::query()->where('inventory_number', 'CHAIN-WRONG-FUND-001')->exists());

        $replacement = $this->incidents->registerReplacement($case, $this->adminUser, [
            ...$registration,
            'inventory_number' => 'CHAIN-REPLACEMENT-001',
            'barcode' => 'CHAINREPLACEMENT001',
            'bibliographic_record_id' => $record->getKey(),
            'notes' => 'Accepted at the circulation desk',
        ]);

        $this->assertNotSame($lostCopy->getKey(), $replacement->getKey());
        $this->assertSame(2, BookCopy::query()->where('bibliographic_record_id', $record->getKey())->count());
        $this->assertTrue(BookCopy::query()->whereKey($lostCopy->getKey())->exists());
        $this->assertSame('lost', $lostCopy->fresh()->status);
        $this->assertSame('available', $replacement->status);
        $this->assertSame('reader_replacement', $replacement->acquisition_source);
        $this->assertSame($approvedFund->getKey(), $replacement->fund_id);
        $this->assertSame('resolved', $case->fresh()->status);
        $this->assertSame('waived', $fine->fresh()->status);
        $this->assertDatabaseHas('copy_history', [
            'copy_id' => $replacement->getKey(),
            'event_type' => 'replacement_registered',
        ]);

        // Closing the case and its linked debt removes the restriction; the
        // old lost copy remains immutable evidence rather than being deleted.
        $newLoan = $this->circulation->issue($reader, $replacement, $this->adminUser);
        $this->assertSame('active', $newLoan->status);
        $this->assertSame('issued', $replacement->fresh()->status);
        $this->assertAuditActions([
            'incident.opened',
            'incident.replacement_proposed',
            'incident.replacement_reviewed',
            'incident.replacement_approved',
            'incident.replacement_copy_registered',
            'incident.fine_waived',
            'incident.resolved',
        ]);
        $this->assertTransitionAudit('incident.replacement_copy_registered');
        $this->assertTransitionAudit('incident.resolved');

        $auditPayload = ActivityLog::query()
            ->where('entity_type', 'circulation_incident_case')
            ->get(['old_values', 'new_values', 'metadata'])
            ->toJson();
        foreach (['password', 'authorization', 'api_key', 'secret'] as $sensitive) {
            $this->assertStringNotContainsString($sensitive, mb_strtolower($auditPayload));
        }
    }

    /** @return array{0: User, 1: ReaderProfile} */
    private function reader(string $name): array
    {
        $reader = $this->makeControlPlaneUser('member', ['name' => $name]);

        return [$reader, ReaderProfile::forUser($reader)];
    }

    /** @param list<string> $actions */
    private function assertAuditActions(array $actions): void
    {
        foreach ($actions as $action) {
            $log = ActivityLog::query()->where('action_type', $action)->firstOrFail();
            $this->assertNotNull($log->occurred_at, "Audit timestamp is missing for {$action}");
            $this->assertNotSame('', (string) $log->actor_name, "Audit actor is missing for {$action}");
            $this->assertNotSame('', (string) $log->actor_role, "Audit actor role is missing for {$action}");
            $this->assertNotSame('', (string) $log->entity_type, "Audit entity is missing for {$action}");
            $this->assertNotSame('', (string) $log->entity_id, "Audit entity id is missing for {$action}");
        }
    }

    private function assertTransitionAudit(string $action): void
    {
        $log = ActivityLog::query()->where('action_type', $action)->latest('id')->firstOrFail();
        $this->assertNotNull($log->old_values, "Audit before snapshot is missing for {$action}");
        $this->assertNotNull($log->new_values, "Audit after snapshot is missing for {$action}");
        $this->assertNotSame($log->old_values, $log->new_values, "Audit snapshots are identical for {$action}");
    }

    private function assertCirculationReason(callable $operation, string $reason): void
    {
        try {
            $operation();
            $this->fail("Expected circulation rejection: {$reason}");
        } catch (CirculationException $exception) {
            $this->assertSame($reason, $exception->reasonCode);
        }
    }
}
