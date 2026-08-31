<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\CopyHistory;
use App\Models\Catalog\Loan;
use App\Models\Catalog\Reservation;
use App\Models\Fund;
use App\Models\User;
use App\Services\Catalog\FundMovementService;
use App\Services\DataQuality\DataQualityScanner;
use Illuminate\Validation\ValidationException;
use Mockery;
use Mockery\MockInterface;
use Tests\Concerns\BuildsCopyLifecycleOperations;
use Tests\TestCase;

class FundMovementServiceTest extends TestCase
{
    use BuildsCopyLifecycleOperations;

    private User $actor;

    private User $reader;

    private Branch $sourceBranch;

    private Branch $destinationBranch;

    private Fund $sourceFund;

    private Fund $destinationFund;

    private BibliographicRecord $record;

    private MockInterface $scanner;

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

        $this->actor = $this->user('movement-actor@example.test', 'Movement Operator');
        $this->reader = $this->user('movement-reader@example.test', 'Movement Reader');
        $this->sourceBranch = $this->branch('SRC');
        $this->destinationBranch = $this->branch('DST');
        $this->sourceFund = $this->fund($this->sourceBranch, 'SRC-MAIN');
        $this->destinationFund = $this->fund($this->destinationBranch, 'DST-MAIN');
        $this->record = BibliographicRecord::query()->create(['title' => 'Fund movement record']);
    }

    public function test_batch_movement_updates_placement_history_and_audit_with_one_batch_id(): void
    {
        $first = $this->copy(1);
        $second = $this->copy(2);

        $result = app(FundMovementService::class)->move(
            [$first->inventory_number, $second->barcode],
            [
                'branch_id' => $this->destinationBranch->getKey(),
                'fund_id' => $this->destinationFund->getKey(),
                'storage_sigla' => 'DST-SIGLA',
                'room' => '204',
                'section' => 'Research',
                'shelf_location' => 'R-04-02',
            ],
            'Approved relocation after the annual stock review.',
            $this->actor,
        );

        $this->assertNotSame('', $result['batch_id']);
        $this->assertCount(2, $result['copies']);
        foreach ([$first, $second] as $copy) {
            $copy->refresh();
            $this->assertSame($this->destinationBranch->getKey(), $copy->branch_id);
            $this->assertSame($this->destinationFund->getKey(), $copy->fund_id);
            $this->assertSame('DST-SIGLA', $copy->storage_sigla);
            $this->assertSame('204', $copy->room);
            $this->assertSame('Research', $copy->section);
            $this->assertSame('R-04-02', $copy->shelf_location);

            $history = CopyHistory::query()->where('copy_id', $copy->getKey())->where('event_type', 'fund_movement')->firstOrFail();
            $this->assertSame($result['batch_id'], data_get($history->details, 'movement_batch_id'));
            $this->assertSame($this->sourceBranch->getKey(), data_get($history->details, 'old.branch_id'));
            $this->assertSame($this->destinationBranch->getKey(), data_get($history->details, 'new.branch_id'));

            $audit = ActivityLog::query()->where('entity_type', 'book_copy')->where('entity_id', (string) $copy->getKey())->firstOrFail();
            $this->assertSame('copies.movement', $audit->action_type);
            $this->assertSame('operational', $audit->scope);
            $this->assertSame($result['batch_id'], data_get($audit->metadata, 'movement_batch_id'));
        }

        $this->assertDatabaseCount('copy_history', 2);
        $this->assertSame(2, ActivityLog::query()->where('action_type', 'copies.movement')->count());
        $this->scanner->shouldHaveReceived('scanModel')->twice();
    }

    public function test_active_loan_on_later_copy_rolls_back_the_entire_batch_and_audit(): void
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
            app(FundMovementService::class)->move(
                [$first->inventory_number, $blocked->inventory_number],
                ['branch_id' => $this->destinationBranch->getKey(), 'fund_id' => $this->destinationFund->getKey()],
                'This entire batch must roll back.',
                $this->actor,
            );
            $this->fail('Expected an active-loan validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('copy_codes', $exception->errors());
        }

        foreach ([$first, $blocked] as $copy) {
            $copy->refresh();
            $this->assertSame($this->sourceBranch->getKey(), $copy->branch_id);
            $this->assertSame($this->sourceFund->getKey(), $copy->fund_id);
        }
        $this->assertDatabaseCount('copy_history', 0);
        $this->assertDatabaseCount('activity_logs', 0);
        $this->scanner->shouldNotHaveReceived('scanModel');
    }

    public function test_active_copy_bound_reservation_blocks_movement_without_lifecycle_changes(): void
    {
        $copy = $this->copy(20, 'reserved', 'active', 'reserved');
        $reservation = Reservation::query()->create([
            'reservation_number' => 'RSV-MOVE-0001',
            'user_id' => $this->reader->getKey(),
            'bibliographic_record_id' => $this->record->getKey(),
            'assigned_copy_id' => $copy->getKey(),
            'status' => 'confirmed',
            'queue_sequence' => 1,
            'source' => 'web',
            'created_by' => $this->actor->getKey(),
        ]);

        try {
            app(FundMovementService::class)->move(
                [$copy->barcode],
                ['branch_id' => $this->destinationBranch->getKey(), 'fund_id' => $this->destinationFund->getKey()],
                'Reservation must keep physical placement stable.',
                $this->actor,
            );
            $this->fail('Expected an active-reservation validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('copy_codes', $exception->errors());
        }

        $copy->refresh();
        $this->assertSame($this->sourceBranch->getKey(), $copy->branch_id);
        $this->assertSame($this->sourceFund->getKey(), $copy->fund_id);
        $this->assertSame('reserved', $copy->status);
        $this->assertSame('confirmed', $reservation->refresh()->status);
        $this->assertSame($copy->getKey(), $reservation->assigned_copy_id);
        $this->assertDatabaseCount('copy_history', 0);
        $this->assertDatabaseCount('activity_logs', 0);
        $this->scanner->shouldNotHaveReceived('scanModel');
    }

    public function test_code_matching_one_inventory_number_and_another_barcode_is_rejected_as_ambiguous(): void
    {
        $inventoryMatch = $this->copy(30);
        $barcodeMatch = $this->copy(31);
        $inventoryMatch->update(['inventory_number' => 'MOVE-CROSS-FIELD-CODE']);
        $barcodeMatch->update(['barcode' => 'MOVE-CROSS-FIELD-CODE']);

        try {
            app(FundMovementService::class)->move(
                ['MOVE-CROSS-FIELD-CODE'],
                ['branch_id' => $this->destinationBranch->getKey(), 'fund_id' => $this->destinationFund->getKey()],
                'A cross-field collision must never pick an arbitrary copy.',
                $this->actor,
            );
            $this->fail('Expected an ambiguous-code validation failure.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('copy_codes', $exception->errors());
        }

        foreach ([$inventoryMatch, $barcodeMatch] as $copy) {
            $copy->refresh();
            $this->assertSame($this->sourceBranch->getKey(), $copy->branch_id);
            $this->assertSame($this->sourceFund->getKey(), $copy->fund_id);
        }
        $this->assertDatabaseCount('copy_history', 0);
        $this->assertDatabaseCount('activity_logs', 0);
        $this->scanner->shouldNotHaveReceived('scanModel');
    }

    public function test_branch_only_move_cannot_leave_the_copy_in_a_fund_from_another_branch(): void
    {
        $copy = $this->copy(40);

        try {
            app(FundMovementService::class)->move(
                [$copy->inventory_number],
                ['branch_id' => $this->destinationBranch->getKey()],
                'A branch-only update must preserve a valid branch/fund pair.',
                $this->actor,
            );
            $this->fail('Expected the incompatible retained fund to be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('fund_id', $exception->errors());
        }

        $copy->refresh();
        $this->assertSame($this->sourceBranch->getKey(), $copy->branch_id);
        $this->assertSame($this->sourceFund->getKey(), $copy->fund_id);
        $this->assertDatabaseCount('copy_history', 0);
        $this->assertDatabaseCount('activity_logs', 0);
        $this->scanner->shouldNotHaveReceived('scanModel');
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

    private function fund(Branch $branch, string $code): Fund
    {
        return Fund::query()->create([
            'branch_id' => $branch->getKey(),
            'code' => $code,
            'name' => $code.' fund',
            'fund_type' => 'main',
            'institutional_scope' => 'general',
            'is_active' => true,
        ]);
    }

    private function copy(
        int $sequence,
        string $status = 'available',
        string $inventoryStatus = 'active',
        string $circulationStatus = 'available',
    ): BookCopy {
        return BookCopy::query()->create([
            'bibliographic_record_id' => $this->record->getKey(),
            'inventory_number' => 'MOVE-INV-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'barcode' => 'MOVE-BC-'.str_pad((string) $sequence, 4, '0', STR_PAD_LEFT),
            'branch_id' => $this->sourceBranch->getKey(),
            'fund_id' => $this->sourceFund->getKey(),
            'storage_sigla' => 'SRC-SIGLA',
            'room' => '101',
            'section' => 'General',
            'shelf_location' => 'G-01-01',
            'condition' => 'good',
            'access_restriction' => 'free',
            'status' => $status,
            'inventory_status' => $inventoryStatus,
            'circulation_status' => $circulationStatus,
        ]);
    }
}
