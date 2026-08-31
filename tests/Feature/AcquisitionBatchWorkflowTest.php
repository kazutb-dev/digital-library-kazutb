<?php

namespace Tests\Feature;

use App\Http\Controllers\Librarian\AcquisitionBatchController;
use App\Http\Controllers\Librarian\KsuRegisterController;
use App\Models\Branch;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Fund;
use App\Models\Ksu\KsuBook;
use App\Models\Ksu\KsuConflict;
use App\Models\Ksu\KsuEntry;
use App\Models\Setting;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Operations\AcquisitionService;
use App\Services\Operations\InventoryNumberAllocator;
use App\Services\Operations\KsuNumberAllocator;
use App\Services\Operations\KsuOperationsService;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Spatie\Permission\Models\Role;
use Tests\Concerns\BuildsAcquisitionOperations;
use Tests\TestCase;

class AcquisitionBatchWorkflowTest extends TestCase
{
    use BuildsAcquisitionOperations;

    private User $actor;

    private BibliographicRecord $record;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAcquisitionOperations();
        Gate::before(static fn (): bool => true);
        $this->registerOperationRoutes();

        $this->actor = User::query()->create([
            'name' => 'Acquisitions Operator',
            'email' => 'acquisitions@example.test',
            'password' => 'test-password',
            'locale' => 'ru',
            'role' => 'librarian',
            'is_active' => true,
        ]);
        Role::findOrCreate('librarian');
        $this->actor->assignRole('librarian');
        $this->withSession([
            'library.user' => [
                'id' => $this->actor->getKey(),
                'role' => 'librarian',
                'name' => $this->actor->name,
            ],
        ]);
        $this->record = BibliographicRecord::query()->create([
            'title' => 'Atomic Library Acquisition',
            'primary_author' => 'Test Author',
        ]);
        KsuBook::query()->create([
            'code' => 'KSU-1',
            'name' => 'KSU Part 1',
            'auto_numbering_enabled' => true,
            'requires_manual_decision' => false,
            'is_active' => true,
        ]);
    }

    public function test_confirm_creates_ksu_entry_copies_items_and_audit_atomically(): void
    {
        KsuEntry::query()->create([
            'ksu_book_id' => KsuBook::query()->where('code', 'KSU-1')->value('id'),
            'entry_number' => '9/2026',
            'number' => 9,
            'year' => 2026,
            'status' => 'legacy',
        ]);
        $batch = app(AcquisitionService::class)->createDraft($this->actor, $this->draftData(2));

        $confirmed = app(AcquisitionService::class)->confirm($this->actor, $batch);

        $this->assertSame('confirmed', $confirmed->status);
        $this->assertSame('10/2026', $confirmed->ksuEntry->entry_number);
        $this->assertDatabaseCount('book_copies', 2);
        $this->assertDatabaseCount('ksu_entry_items', 2);
        $this->assertDatabaseHas('book_copies', [
            'acquisition_batch_id' => $batch->getKey(),
            'inventory_status' => 'active',
            'circulation_status' => 'available',
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action_type' => 'acquisition_batch.confirmed',
            'entity_id' => (string) $batch->getKey(),
        ]);
        $this->actingAs($this->actor)
            ->get(route('librarian.acquisitions.show', $confirmed))
            ->assertOk()
            ->assertSee('Созданные экземпляры', false);
        $this->actingAs($this->actor)
            ->get(route('librarian.ksu.show', $confirmed->ksuEntry))
            ->assertOk()
            ->assertSee('10/2026', false);
    }

    public function test_failure_during_copy_creation_rolls_back_entry_copies_and_sequence(): void
    {
        $batch = app(AcquisitionService::class)->createDraft($this->actor, $this->draftData(2));
        $failingInventory = new class extends InventoryNumberAllocator
        {
            private int $calls = 0;

            public function allocate(?int $branchId, int $year, string $inventoryPrefix = 'INV', string $barcodePrefix = 'KAZUTB'): array
            {
                if (++$this->calls === 2) {
                    throw new RuntimeException('Injected allocation failure');
                }

                return parent::allocate($branchId, $year, $inventoryPrefix, $barcodePrefix);
            }
        };
        $service = new AcquisitionService(
            app(KsuNumberAllocator::class),
            $failingInventory,
            app(AuditLogger::class),
        );

        try {
            $service->confirm($this->actor, $batch);
            $this->fail('Expected the injected failure.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected allocation failure', $exception->getMessage());
        }

        $this->assertSame('draft', $batch->refresh()->status);
        $this->assertDatabaseCount('book_copies', 0);
        $this->assertDatabaseCount('ksu_entries', 0);
        $this->assertDatabaseCount('ksu_entry_items', 0);
        $this->assertDatabaseCount('inventory_sequences', 0);
    }

    public function test_librarian_pages_render_and_conflict_resolution_is_audited(): void
    {
        $batch = app(AcquisitionService::class)->createDraft($this->actor, $this->draftData(1));
        $this->actingAs($this->actor)
            ->get(route('librarian.acquisitions.index'))
            ->assertOk()
            ->assertSee('Новые поступления', false)
            ->assertSee($batch->batch_number, false);
        $this->actingAs($this->actor)
            ->get(route('librarian.acquisitions.show', $batch))
            ->assertOk()
            ->assertSee('Подтвердить поступление', false);
        $this->actingAs($this->actor)
            ->get(route('librarian.ksu.index'))
            ->assertOk()
            ->assertSee('Регистр КСУ', false);

        $conflict = KsuConflict::query()->create([
            'ksu_book_id' => KsuBook::query()->where('code', 'KSU-1')->value('id'),
            'kind' => 'unresolved_link',
            'ksu_number_raw' => '10/2026',
            'reason' => 'No exact copy link',
            'status' => 'open',
        ]);
        $this->actingAs($this->actor)
            ->get(route('librarian.ksu.conflicts'))
            ->assertOk()
            ->assertSee('No exact copy link', false);
        $this->actingAs($this->actor)
            ->post(route('librarian.ksu.conflicts.resolve', $conflict), [
                'status' => 'ignored',
                'resolution_note' => 'Source row reviewed; no canonical copy exists.',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('ksu_conflicts', ['id' => $conflict->getKey(), 'status' => 'ignored']);
        $this->assertDatabaseHas('ksu_audit_events', ['event_type' => 'conflict.ignored']);
    }

    public function test_withdrawal_api_creates_ksu_part_two_without_changing_copy_state(): void
    {
        $batch = app(AcquisitionService::class)->createDraft($this->actor, $this->draftData(1));
        $copy = app(AcquisitionService::class)->confirm($this->actor, $batch)
            ->ksuEntry
            ->items
            ->firstOrFail()
            ->copy;

        $entry = app(KsuOperationsService::class)->recordWithdrawal(
            [$copy],
            '2026-08-29',
            'ACT-2026-001',
            'Damaged beyond repair',
            $this->actor,
        );

        $this->assertSame('KSU-2', $entry->book->code);
        $this->assertSame('withdrawal', $entry->operation_type);
        $this->assertSame('1/2026', $entry->entry_number);
        $this->assertSame('available', $copy->refresh()->status);
        $this->assertDatabaseHas('ksu_entry_items', [
            'ksu_entry_id' => $entry->getKey(),
            'book_copy_id' => $copy->getKey(),
            'link_method' => 'writeoff_act',
        ]);
    }

    public function test_manual_inventory_and_barcode_lists_create_exact_values_without_sequences(): void
    {
        $data = $this->draftData(2);
        $data['items'][0] = [
            ...$data['items'][0],
            'inventory_number_mode' => 'manual_list',
            'manual_inventory_numbers' => "MAN-0001\nMAN-0002",
            'barcode_mode' => 'manual-list',
            'manual_barcodes' => ['BC-MAN-001', 'BC-MAN-002'],
            'service_point_code' => 'SP-READING',
            'shelf_index' => 'UDC-001',
        ];

        $batch = app(AcquisitionService::class)->createDraft($this->actor, $data);
        $item = $batch->items->firstOrFail();
        $this->assertSame(['MAN-0001', 'MAN-0002'], $item->manual_inventory_numbers);
        $this->assertSame(['BC-MAN-001', 'BC-MAN-002'], $item->manual_barcodes);

        app(AcquisitionService::class)->confirm($this->actor, $batch);

        $copies = BookCopy::query()->orderBy('id')->get();
        $this->assertSame(['MAN-0001', 'MAN-0002'], $copies->pluck('inventory_number')->all());
        $this->assertSame(['BC-MAN-001', 'BC-MAN-002'], $copies->pluck('barcode')->all());
        $this->assertSame(['SP-READING'], $copies->pluck('service_point_code')->unique()->values()->all());
        $this->assertSame(['UDC-001'], $copies->pluck('shelf_index')->unique()->values()->all());
        $this->assertDatabaseCount('inventory_sequences', 0);
    }

    public function test_inventory_range_and_no_barcode_expand_to_the_exact_quantity(): void
    {
        $data = $this->draftData(3);
        $data['items'][0] = [
            ...$data['items'][0],
            'inventory_number_mode' => 'range',
            'inventory_range_start' => 'R-0098',
            'inventory_range_end' => 'R-0100',
            'barcode_mode' => 'none',
        ];

        $batch = app(AcquisitionService::class)->createDraft($this->actor, $data);
        app(AcquisitionService::class)->confirm($this->actor, $batch);

        $copies = BookCopy::query()->orderBy('id')->get();
        $this->assertSame(['R-0098', 'R-0099', 'R-0100'], $copies->pluck('inventory_number')->all());
        $this->assertSame([null, null, null], $copies->pluck('barcode')->all());
        $this->assertDatabaseCount('inventory_sequences', 0);
    }

    public function test_confirm_revalidates_exact_quantity_and_rolls_back_all_posting_rows(): void
    {
        $data = $this->draftData(2);
        $data['items'][0] = [
            ...$data['items'][0],
            'inventory_number_mode' => 'manual_list',
            'manual_inventory_numbers' => ['Q-001', 'Q-002'],
            'barcode_mode' => 'none',
        ];
        $batch = app(AcquisitionService::class)->createDraft($this->actor, $data);
        $batch->items->firstOrFail()->update(['quantity' => 3]);

        try {
            app(AcquisitionService::class)->confirm($this->actor, $batch);
            $this->fail('Expected exact-quantity validation to fail.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items.0.manual_inventory_numbers', $exception->errors());
        }

        $this->assertSame('draft', $batch->refresh()->status);
        $this->assertDatabaseCount('book_copies', 0);
        $this->assertDatabaseCount('ksu_entries', 0);
        $this->assertDatabaseCount('ksu_entry_items', 0);
        $this->assertDatabaseCount('inventory_sequences', 0);
    }

    public function test_existing_manual_inventory_and_barcode_conflicts_keep_posting_atomic(): void
    {
        BookCopy::query()->forceCreate([
            'bibliographic_record_id' => $this->record->getKey(),
            'inventory_number' => 'TAKEN-001',
            'barcode' => 'TAKEN-BC-001',
        ]);
        $data = $this->draftData(1);
        $data['items'][0] = [
            ...$data['items'][0],
            'inventory_number_mode' => 'manual_list',
            'manual_inventory_numbers' => ['TAKEN-001'],
            'barcode_mode' => 'manual_list',
            'manual_barcodes' => ['NEW-BC-001'],
        ];
        $batch = app(AcquisitionService::class)->createDraft($this->actor, $data);

        try {
            app(AcquisitionService::class)->confirm($this->actor, $batch);
            $this->fail('Expected the inventory-number conflict.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }

        $this->assertSame('draft', $batch->refresh()->status);
        $this->assertDatabaseCount('book_copies', 1);
        $this->assertDatabaseCount('ksu_entries', 0);
        $this->assertDatabaseCount('ksu_entry_items', 0);
        $this->assertDatabaseCount('inventory_sequences', 0);

        $barcodeData = $this->draftData(1);
        $barcodeData['items'][0] = [
            ...$barcodeData['items'][0],
            'inventory_number_mode' => 'manual_list',
            'manual_inventory_numbers' => ['NEW-INV-001'],
            'barcode_mode' => 'manual_list',
            'manual_barcodes' => ['TAKEN-BC-001'],
        ];
        $barcodeBatch = app(AcquisitionService::class)->createDraft($this->actor, $barcodeData);

        try {
            app(AcquisitionService::class)->confirm($this->actor, $barcodeBatch);
            $this->fail('Expected the barcode conflict.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items', $exception->errors());
        }

        $this->assertSame('draft', $barcodeBatch->refresh()->status);
        $this->assertDatabaseCount('book_copies', 1);
        $this->assertDatabaseCount('ksu_entries', 0);
        $this->assertDatabaseCount('ksu_entry_items', 0);
        $this->assertDatabaseCount('inventory_sequences', 0);
    }

    public function test_settings_select_safe_defaults_and_gate_only_automatic_modes(): void
    {
        $this->setting('inventory_numbering_enabled', false);
        $this->setting('barcode_generation_enabled', false);

        $manualData = $this->draftData(1);
        $manualData['items'][0]['manual_inventory_numbers'] = ['SET-MAN-001'];
        unset($manualData['items'][0]['inventory_number_mode'], $manualData['items'][0]['barcode_mode']);
        $manualBatch = app(AcquisitionService::class)->createDraft($this->actor, $manualData);
        $this->assertSame('manual_list', $manualBatch->items->firstOrFail()->inventory_number_mode);
        $this->assertSame('none', $manualBatch->items->firstOrFail()->barcode_mode);
        app(AcquisitionService::class)->confirm($this->actor, $manualBatch);
        $this->assertDatabaseHas('book_copies', [
            'inventory_number' => 'SET-MAN-001',
            'barcode' => null,
        ]);

        $inventoryAuto = $this->draftData(1);
        $inventoryAuto['items'][0]['inventory_number_mode'] = 'auto';
        $inventoryAuto['items'][0]['barcode_mode'] = 'none';
        try {
            app(AcquisitionService::class)->createDraft($this->actor, $inventoryAuto);
            $this->fail('Expected the inventory auto-mode gate.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items.0.inventory_number_mode', $exception->errors());
        }

        $barcodeAuto = $this->draftData(1);
        $barcodeAuto['items'][0]['inventory_number_mode'] = 'manual_list';
        $barcodeAuto['items'][0]['manual_inventory_numbers'] = ['SET-MAN-002'];
        $barcodeAuto['items'][0]['barcode_mode'] = 'auto';
        try {
            app(AcquisitionService::class)->createDraft($this->actor, $barcodeAuto);
            $this->fail('Expected the barcode auto-mode gate.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('items.0.barcode_mode', $exception->errors());
        }

        $this->assertDatabaseCount('inventory_sequences', 0);
    }

    public function test_batch_rejects_a_fund_from_another_branch_before_creating_a_draft(): void
    {
        $first = Branch::query()->create(['code' => 'ACQ-A', 'name' => 'Acquisition branch A']);
        $second = Branch::query()->create(['code' => 'ACQ-B', 'name' => 'Acquisition branch B']);
        $fund = Fund::query()->create([
            'branch_id' => $second->getKey(),
            'code' => 'ACQ-B-FUND',
            'name' => 'Branch B collection',
            'fund_type' => 'main',
        ]);
        $data = $this->draftData(1) + [
            'branch_id' => $first->getKey(),
            'fund_id' => $fund->getKey(),
        ];

        try {
            app(AcquisitionService::class)->createDraft($this->actor, $data);
            $this->fail('A fund/branch mismatch must be rejected.');
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey('fund_id', $exception->errors());
        }

        $this->assertDatabaseCount('acquisition_batches', 0);
        $this->assertDatabaseCount('acquisition_batch_items', 0);
    }

    /** @return array<string,mixed> */
    private function draftData(int $quantity): array
    {
        return [
            'batch_number' => 'ARR-TEST-'.str()->upper(str()->random(8)),
            'received_at' => '2026-08-29',
            'acquisition_source' => 'purchase',
            'supplier_name' => 'Test Supplier',
            'currency' => 'KZT',
            'items' => [[
                'bibliographic_record_id' => $this->record->getKey(),
                'quantity' => $quantity,
                'unit_price' => '1250.50',
                'accounting_type' => 'inventory',
                'condition' => 'new',
                'access_restriction' => 'free',
                'inventory_prefix' => 'INV',
                'barcode_prefix' => 'KAZUTB',
            ]],
        ];
    }

    private function registerOperationRoutes(): void
    {
        Route::middleware('web')->prefix('__test/operations')->group(function (): void {
            Route::get('/acquisitions', [AcquisitionBatchController::class, 'index'])->name('librarian.acquisitions.index');
            Route::post('/acquisitions', [AcquisitionBatchController::class, 'store'])->name('librarian.acquisitions.store');
            Route::get('/acquisitions/{batch}', [AcquisitionBatchController::class, 'show'])->name('librarian.acquisitions.show');
            Route::put('/acquisitions/{batch}', [AcquisitionBatchController::class, 'update'])->name('librarian.acquisitions.update');
            Route::post('/acquisitions/{batch}/confirm', [AcquisitionBatchController::class, 'confirm'])->name('librarian.acquisitions.confirm');
            Route::post('/acquisitions/{batch}/cancel', [AcquisitionBatchController::class, 'cancel'])->name('librarian.acquisitions.cancel');
            Route::get('/ksu', [KsuRegisterController::class, 'index'])->name('librarian.ksu.index');
            Route::get('/ksu/conflicts', [KsuRegisterController::class, 'conflicts'])->name('librarian.ksu.conflicts');
            Route::post('/ksu/conflicts/{conflict}', [KsuRegisterController::class, 'resolve'])->name('librarian.ksu.conflicts.resolve');
            Route::get('/ksu/{entry}', [KsuRegisterController::class, 'show'])->name('librarian.ksu.show');
        });
        Route::getRoutes()->refreshNameLookups();
    }

    private function setting(string $key, bool $value): void
    {
        Setting::query()->create([
            'key' => $key,
            'value' => $value,
            'type' => 'boolean',
            'group' => 'library_operations',
        ]);
    }
}
