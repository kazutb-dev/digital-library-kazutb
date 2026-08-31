<?php

namespace Tests\Feature;

use App\Exceptions\CirculationException;
use App\Models\Branch;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\CirculationIncidentCase;
use App\Models\Catalog\CopyHistory;
use App\Models\Catalog\CopyTransfer;
use App\Models\Catalog\Loan;
use App\Models\Catalog\ReaderProfile;
use App\Models\Catalog\Reservation;
use App\Models\Fund;
use App\Models\Ksu\KsuAuditEvent;
use App\Models\Ksu\KsuEntry;
use App\Models\Ksu\KsuEntryItem;
use App\Models\User;
use App\Services\Catalog\CirculationService;
use App\Services\Catalog\CopyWriteOffService;
use App\Services\Catalog\IncidentCaseService;
use App\Services\Catalog\LibraryNotificationService;
use App\Services\DataQuality\DataQualityScanner;
use App\Services\Reports\CollectionAccountingReportService;
use App\Services\Reports\ReportFilters;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\Concerns\BuildsCopyLifecycleOperations;
use Tests\TestCase;

class CopyWriteOffServiceTest extends TestCase
{
    use BuildsCopyLifecycleOperations;

    private User $actor;

    private User $reader;

    private Branch $sourceBranch;

    private Branch $destinationBranch;

    private Fund $fund;

    private BibliographicRecord $record;

    private MockInterface $scanner;

    private MockInterface $notifications;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpCopyLifecycleOperations();

        $this->scanner = Mockery::mock(DataQualityScanner::class);
        $this->scanner->shouldReceive('scanModel')->byDefault()->andReturn([
            'records_scanned' => 1,
            'issues_found' => 0,
            'issues_created' => 0,
            'issues_reopened' => 0,
            'issues_resolved_automatically' => 0,
        ]);
        $this->app->instance(DataQualityScanner::class, $this->scanner);
        $this->notifications = Mockery::mock(LibraryNotificationService::class);
        $this->notifications->shouldReceive('sendLocalized')->byDefault()->andReturnNull();
        $this->app->instance(LibraryNotificationService::class, $this->notifications);

        $this->actor = $this->user('writeoff-actor@example.test', 'Write-off Operator');
        $this->reader = $this->user('writeoff-reader@example.test', 'Write-off Reader');
        $this->sourceBranch = $this->branch('WO-SRC');
        $this->destinationBranch = $this->branch('WO-DST');
        $this->fund = Fund::query()->create([
            'branch_id' => $this->sourceBranch->getKey(),
            'code' => 'WO-MAIN',
            'name' => 'Write-off main fund',
            'fund_type' => 'main',
            'institutional_scope' => 'general',
            'is_active' => true,
        ]);
        $this->record = BibliographicRecord::query()->create(['title' => 'Write-off lifecycle record']);
    }

    public function test_write_off_cancels_active_reservation_creates_ksu_two_and_audits_full_lifecycle(): void
    {
        $reserved = $this->copy(1, 'reserved', 'active', 'reserved', '125.50');
        $available = $this->copy(2, 'available', 'active', 'available', '74.50');
        $reservation = $this->reservation($reserved, 'ready_for_pickup', 'RSV-WO-0001');

        $result = app(CopyWriteOffService::class)->writeOffByCodes(
            [$reserved->inventory_number, $available->barcode],
            '2026-08-29',
            'ACT-WO-2026-001',
            'Irreparable damage confirmed by the write-off commission.',
            $this->actor,
        );

        $this->assertCount(2, $result['copies']);
        foreach ([$reserved, $available] as $copy) {
            $copy->refresh();
            $this->assertSame('written_off', $copy->status);
            $this->assertSame('written_off', $copy->inventory_status);
            $this->assertSame('unavailable', $copy->circulation_status);
            $this->assertSame('2026-08-29', $copy->writeoff_date?->toDateString());
            $this->assertSame('ACT-WO-2026-001', $copy->writeoff_act);
            $this->assertSame('Irreparable damage confirmed by the write-off commission.', $copy->writeoff_reason);
            $this->assertFalse($copy->isCirculatable());

            $history = CopyHistory::query()->where('copy_id', $copy->getKey())->where('event_type', 'write_off')->firstOrFail();
            $this->assertSame($result['ksu_entry_id'], data_get($history->details, 'ksu_withdrawal_entry_id'));
            $this->assertSame('written_off', data_get($history->details, 'new.status'));

            $this->assertDatabaseHas('activity_logs', [
                'action_type' => 'copies.status_change',
                'entity_type' => 'book_copy',
                'entity_id' => (string) $copy->getKey(),
            ]);
        }

        $reservation->refresh();
        $this->assertSame('cancelled', $reservation->status);
        $this->assertNull($reservation->assigned_copy_id);
        $this->assertSame($this->actor->getKey(), $reservation->cancelled_by);
        $this->assertNotNull($reservation->cancelled_at);
        $this->assertDatabaseHas('reservation_history', [
            'reservation_id' => $reservation->getKey(),
            'event_type' => 'reservation.cancelled',
            'from_status' => 'ready_for_pickup',
            'to_status' => 'cancelled',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action_type' => 'reservation.cancel',
            'entity_type' => 'reservation',
            'entity_id' => (string) $reservation->getKey(),
        ]);

        $entry = KsuEntry::query()->findOrFail($result['ksu_entry_id'])->load('book');
        $this->assertSame('KSU-2', $entry->book?->code);
        $this->assertSame('withdrawal', $entry->operation_type);
        $this->assertSame('ACT-WO-2026-001', $entry->act_number);
        $this->assertSame(2, $entry->copy_count);
        $this->assertSame('200.00', $entry->total_cost);
        $this->assertSame(2, KsuEntryItem::query()->where('ksu_entry_id', $entry->getKey())->where('link_method', 'writeoff_act')->count());
        $this->assertSame(2, KsuAuditEvent::query()->where('ksu_entry_id', $entry->getKey())->where('event_type', 'withdrawal.item_linked')->count());
        $this->assertDatabaseHas('ksu_audit_events', [
            'event_type' => 'withdrawal.created',
            'ksu_entry_id' => $entry->getKey(),
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action_type' => 'ksu.withdrawal.created',
            'entity_type' => 'ksu_entry',
            'entity_id' => (string) $entry->getKey(),
        ]);

        $this->scanner->shouldHaveReceived('scanModel')->twice();
        $this->notifications->shouldHaveReceived('sendLocalized')->once();
    }

    public function test_incident_resolution_uses_the_same_ksu_two_writeoff_and_report_chain(): void
    {
        ReaderProfile::forUser($this->reader);
        $copy = $this->copy(3, 'available', 'active', 'available', '180.00');
        $circulation = app(CirculationService::class);
        $loan = $circulation->issue($this->reader, $copy, $this->actor);
        $circulation->returnCopy(
            $copy,
            $this->actor,
            'severe_damage',
            'damaged',
            null,
            'Pages and binding are beyond repair.',
            [
                'damage_severity' => 'irreparable',
                'damage_description' => 'Pages and binding are beyond repair.',
                'preliminary_action' => 'write_off',
                'open_case' => true,
            ],
        );

        $case = CirculationIncidentCase::query()->where('loan_id', $loan->getKey())->firstOrFail();
        $this->assertSame('under_repair', $copy->fresh()->status);

        app(IncidentCaseService::class)->resolveWithoutReplacement(
            $case,
            $this->actor,
            'write_off',
            'Commission confirmed irreparable damage.',
            false,
            '2026-08-29',
            'ACT-INC-2026-003',
        );

        $copy->refresh();
        $this->assertSame('written_off', $copy->status);
        $this->assertSame('2026-08-29', $copy->writeoff_date?->toDateString());
        $entry = KsuEntry::query()->where('act_number', 'ACT-INC-2026-003')->firstOrFail();
        $this->assertSame('KSU-2', $entry->book?->code);
        $this->assertSame(1, $entry->copy_count);
        $this->assertDatabaseHas('copy_history', [
            'copy_id' => $copy->getKey(),
            'event_type' => 'write_off',
        ]);

        $filters = new ReportFilters(
            preset: 'custom',
            from: Carbon::parse('2026-01-01', 'Asia/Almaty')->startOfDay()->utc(),
            to: Carbon::parse('2026-12-31', 'Asia/Almaty')->endOfDay()->utc(),
        );
        $reports = app(CollectionAccountingReportService::class);
        $ksu = $reports->build('ksu-part-2', $filters);
        $writeoffs = $reports->build('writeoffs', $filters);
        $this->assertCount(1, $ksu['rows'], json_encode($ksu, JSON_UNESCAPED_UNICODE));
        $this->assertSame(1, collect($ksu['metrics'])->firstWhere('key', 'copies')['value']);
        $this->assertSame(1, collect($writeoffs['metrics'])->firstWhere('key', 'copies')['value']);
        $this->assertSame('ACT-INC-2026-003', $writeoffs['rows'][0]['act_number']);
    }

    public function test_active_loan_on_later_copy_rolls_back_statuses_ksu_history_and_audit(): void
    {
        $first = $this->copy(10);
        $blocked = $this->copy(11);
        Loan::query()->create([
            'user_id' => $this->reader->getKey(),
            'copy_id' => $blocked->getKey(),
            'status' => 'active',
            'issued_at' => now()->subDay(),
            'due_at' => now()->addWeek(),
        ]);

        try {
            app(CopyWriteOffService::class)->writeOffByCodes(
                [$first->inventory_number, $blocked->inventory_number],
                '2026-08-29',
                'ACT-WO-2026-002',
                'This batch must be entirely atomic.',
                $this->actor,
            );
            $this->fail('Expected an active-loan validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('copy_codes', $exception->errors());
        }

        foreach ([$first, $blocked] as $copy) {
            $copy->refresh();
            $this->assertSame('available', $copy->status);
            $this->assertSame('active', $copy->inventory_status);
            $this->assertSame('available', $copy->circulation_status);
            $this->assertNull($copy->writeoff_date);
        }
        $this->assertDatabaseCount('ksu_books', 0);
        $this->assertDatabaseCount('ksu_entries', 0);
        $this->assertDatabaseCount('ksu_entry_items', 0);
        $this->assertDatabaseCount('ksu_audit_events', 0);
        $this->assertDatabaseCount('copy_history', 0);
        $this->assertDatabaseCount('activity_logs', 0);
        $this->scanner->shouldNotHaveReceived('scanModel');
    }

    public function test_in_transit_reservation_failure_rolls_back_copy_and_reservation_lifecycle(): void
    {
        $copy = $this->copy(20, 'reserved', 'active', 'reserved');
        $reservation = $this->reservation($copy, 'in_transit', 'RSV-WO-0002');
        $transfer = CopyTransfer::query()->create([
            'transfer_number' => 'TRF-WO-0001',
            'copy_id' => $copy->getKey(),
            'reservation_id' => $reservation->getKey(),
            'source_branch_id' => $this->sourceBranch->getKey(),
            'destination_branch_id' => $this->destinationBranch->getKey(),
            'status' => 'in_transit',
            'requested_by' => $this->actor->getKey(),
            'requested_at' => now()->subDay(),
            'sent_at' => now()->subHour(),
        ]);

        try {
            app(CopyWriteOffService::class)->writeOffByCodes(
                [$copy->barcode],
                '2026-08-29',
                'ACT-WO-2026-003',
                'An in-transit reservation must prevent write-off.',
                $this->actor,
            );
            $this->fail('Expected the in-transit reservation to abort the write-off.');
        } catch (CirculationException $exception) {
            $this->assertSame('transfer_already_sent', $exception->reasonCode);
        }

        $copy->refresh();
        $this->assertSame('reserved', $copy->status);
        $this->assertSame('active', $copy->inventory_status);
        $this->assertSame('reserved', $copy->circulation_status);
        $this->assertNull($copy->writeoff_date);
        $this->assertSame('in_transit', $reservation->refresh()->status);
        $this->assertSame($copy->getKey(), $reservation->assigned_copy_id);
        $this->assertSame('in_transit', $transfer->refresh()->status);
        $this->assertDatabaseCount('reservation_history', 0);
        $this->assertDatabaseCount('ksu_entries', 0);
        $this->assertDatabaseCount('copy_history', 0);
        $this->assertDatabaseCount('activity_logs', 0);
        $this->scanner->shouldNotHaveReceived('scanModel');
        $this->notifications->shouldNotHaveReceived('sendLocalized');
    }

    public function test_code_matching_one_inventory_number_and_another_barcode_is_rejected_as_ambiguous(): void
    {
        $inventoryMatch = $this->copy(30);
        $barcodeMatch = $this->copy(31);
        $inventoryMatch->update(['inventory_number' => 'WO-CROSS-FIELD-CODE']);
        $barcodeMatch->update(['barcode' => 'WO-CROSS-FIELD-CODE']);

        try {
            app(CopyWriteOffService::class)->writeOffByCodes(
                ['WO-CROSS-FIELD-CODE'],
                '2026-08-29',
                'ACT-WO-2026-004',
                'A cross-field collision must never pick an arbitrary copy.',
                $this->actor,
            );
            $this->fail('Expected an ambiguous-code validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('copy_codes', $exception->errors());
        }

        foreach ([$inventoryMatch, $barcodeMatch] as $copy) {
            $copy->refresh();
            $this->assertSame('available', $copy->status);
            $this->assertSame('active', $copy->inventory_status);
            $this->assertSame('available', $copy->circulation_status);
            $this->assertNull($copy->writeoff_date);
        }
        $this->assertDatabaseCount('ksu_entries', 0);
        $this->assertDatabaseCount('copy_history', 0);
        $this->assertDatabaseCount('activity_logs', 0);
        $this->scanner->shouldNotHaveReceived('scanModel');
        $this->notifications->shouldNotHaveReceived('sendLocalized');
    }

    private function user(string $email, string $name): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => $email,
            'password' => 'test-password',
            'locale' => 'ru',
        ]);
    }

    private function branch(string $code): Branch
    {
        return Branch::query()->create([
            'code' => $code,
            'name' => $code.' branch',
            'type' => 'library',
            'is_active' => true,
        ]);
    }

    private function copy(
        int $sequence,
        string $status = 'available',
        string $inventoryStatus = 'active',
        string $circulationStatus = 'available',
        ?string $price = null,
    ): BookCopy {
        return BookCopy::query()->create([
            'bibliographic_record_id' => $this->record->getKey(),
            'inventory_number' => 'WO-INV-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'barcode' => 'WO-BC-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'branch_id' => $this->sourceBranch->getKey(),
            'fund_id' => $this->fund->getKey(),
            'price' => $price,
            'registration_date' => '2020-01-01',
            'condition' => 'damaged',
            'access_restriction' => 'free',
            'status' => $status,
            'inventory_status' => $inventoryStatus,
            'circulation_status' => $circulationStatus,
        ]);
    }

    private function reservation(BookCopy $copy, string $status, string $number): Reservation
    {
        return Reservation::query()->create([
            'reservation_number' => $number,
            'user_id' => $this->reader->getKey(),
            'bibliographic_record_id' => $this->record->getKey(),
            'assigned_copy_id' => $copy->getKey(),
            'pickup_branch_id' => $this->destinationBranch->getKey(),
            'current_branch_id' => $this->sourceBranch->getKey(),
            'status' => $status,
            'queue_sequence' => 1,
            'confirmed_at' => now()->subDays(2),
            'copy_assigned_at' => now()->subDays(2),
            'ready_at' => $status === 'ready_for_pickup' ? now()->subDay() : null,
            'source' => 'web',
            'created_by' => $this->actor->getKey(),
        ]);
    }
}
