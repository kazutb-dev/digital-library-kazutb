<?php

namespace Tests\Feature;

use App\Http\Controllers\Admin\LibraryDataRecoveryController;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\LegacyMarcField;
use App\Models\Catalog\LegacyMarcRecord;
use App\Models\Ksu\KsuBook;
use App\Models\Ksu\KsuConflict;
use App\Models\Ksu\KsuSequence;
use App\Models\Recovery\LegacyImportBatch;
use App\Models\Recovery\LegacyMarcCopy;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Spatie\Permission\Models\Permission;
use Spatie\Permission\PermissionRegistrar;
use Tests\Concerns\BuildsAcquisitionOperations;
use Tests\TestCase;

class AdminLibraryDataRecoveryControlPlaneTest extends TestCase
{
    use BuildsAcquisitionOperations;

    private User $viewer;

    private User $manager;

    private User $outsider;

    private LegacyImportBatch $batch;

    private LegacyMarcRecord $record;

    private int $quarantineId;

    private int $conflictId;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAcquisitionOperations();
        $this->registerRoutes();
        $this->setUpPermissions();
        $this->seedRecoveryEvidence();
    }

    public function test_every_read_endpoint_requires_legacy_recovery_view(): void
    {
        foreach ($this->readUrls() as $url) {
            $this->actingAs($this->outsider)->get($url)->assertForbidden();
        }

        $this->actingAs($this->viewer)->get('/__tests/admin/library-recovery')
            ->assertOk()
            ->assertSee('Восстановленные библиотечные данные', false)
            ->assertSee($this->batch->package_name, false)
            ->assertSee($this->batch->package_sha256, false)
            ->assertSee('INV.T990t', false)
            ->assertSee('КСУ', false);
    }

    public function test_dashboard_and_evidence_lookups_render_recovery_facts(): void
    {
        $this->actingAs($this->viewer)
            ->get('/__tests/admin/library-recovery/batches/'.$this->batch->id)
            ->assertOk()
            ->assertSeeInOrder(['Документы', 'Ожидалось', '10', 'Загружено', '10'])
            ->assertSeeInOrder(['Поля', 'Ожидалось', '25', 'Загружено', '25'])
            ->assertSee('validated_sha256', false);

        $this->actingAs($this->viewer)
            ->get('/__tests/admin/library-recovery/raw?q=CN-900&tag=245')
            ->assertOk()
            ->assertSee('CN-900', false)
            ->assertSee($this->record->source_hash, false);

        $this->actingAs($this->viewer)
            ->get('/__tests/admin/library-recovery/raw/'.$this->record->id)
            ->assertOk()
            ->assertSee('245', false)
            ->assertSee('Recovered title', false)
            ->assertSee('raw-title', false);

        $this->actingAs($this->viewer)
            ->get('/__tests/admin/library-recovery/quarantine?kind=orphan_copy&status=open')
            ->assertOk()
            ->assertSee('orphan_copy', false)
            ->assertSee('No matching document', false);

        $this->actingAs($this->viewer)
            ->get('/__tests/admin/library-recovery/quarantine/'.$this->quarantineId)
            ->assertOk()
            ->assertSee('INV-ORPHAN-77', false);

        $this->actingAs($this->viewer)
            ->get('/__tests/admin/library-recovery/conflicts?field_name=title&status=open')
            ->assertOk()
            ->assertSee('Current title', false)
            ->assertSee('Legacy title', false);

        $this->actingAs($this->viewer)
            ->get('/__tests/admin/library-recovery/conflicts/'.$this->conflictId)
            ->assertOk()
            ->assertSee('manual_edit_after_import', false);
    }

    public function test_manage_permission_only_hands_off_to_existing_librarian_review(): void
    {
        $this->actingAs($this->viewer)
            ->get('/__tests/admin/library-recovery/review?queue=conflicts')
            ->assertForbidden();

        $this->actingAs($this->manager)
            ->get('/__tests/admin/library-recovery/review?queue=conflicts')
            ->assertRedirect(url('/librarian/recovery?queue=conflicts'));
    }

    public function test_all_control_plane_gets_leave_recovery_evidence_unchanged(): void
    {
        $before = $this->recoverySnapshot();

        foreach ($this->readUrls() as $url) {
            $this->actingAs($this->viewer)->get($url)->assertOk();
        }

        $this->assertSame($before, $this->recoverySnapshot());
    }

    private function registerRoutes(): void
    {
        Route::middleware('web')->prefix('/__tests/admin/library-recovery')->group(function (): void {
            Route::get('/', [LibraryDataRecoveryController::class, 'index']);
            Route::get('/batches/{batchId}', [LibraryDataRecoveryController::class, 'batch']);
            Route::get('/raw', [LibraryDataRecoveryController::class, 'rawRecords']);
            Route::get('/raw/{recordId}', [LibraryDataRecoveryController::class, 'rawRecord']);
            Route::get('/quarantine', [LibraryDataRecoveryController::class, 'quarantine']);
            Route::get('/quarantine/{quarantineId}', [LibraryDataRecoveryController::class, 'quarantineItem']);
            Route::get('/conflicts', [LibraryDataRecoveryController::class, 'conflicts']);
            Route::get('/conflicts/{conflictId}', [LibraryDataRecoveryController::class, 'conflict']);
            Route::get('/review', [LibraryDataRecoveryController::class, 'librarianReview']);
        });
    }

    private function setUpPermissions(): void
    {
        app(PermissionRegistrar::class)->forgetCachedPermissions();
        foreach (['legacy_recovery.view', 'legacy_recovery.manage'] as $permission) {
            Permission::findOrCreate($permission, 'web');
        }

        $this->viewer = $this->user('Recovery Viewer');
        $this->viewer->givePermissionTo('legacy_recovery.view');
        $this->manager = $this->user('Recovery Manager');
        $this->manager->givePermissionTo(['legacy_recovery.view', 'legacy_recovery.manage']);
        $this->outsider = $this->user('No Recovery Access');
    }

    private function user(string $name): User
    {
        return User::query()->create([
            'name' => $name,
            'email' => str()->slug($name).'-'.str()->random(6).'@example.test',
            'password' => 'SafeSQLiteTest2026!',
            'locale' => 'ru',
            'is_active' => true,
        ]);
    }

    private function seedRecoveryEvidence(): void
    {
        $record = BibliographicRecord::query()->create([
            'title' => 'Current title',
            'primary_author' => 'Recovery Author',
        ]);
        $this->batch = LegacyImportBatch::query()->create([
            'package_name' => 'marc-recovery-evidence.zip',
            'package_sha256' => str_repeat('a', 64),
            'package_bytes' => 4096,
            'source_system' => 'MARC-SQL',
            'source_database' => 'MARC',
            'status' => 'applied',
            'documents_expected' => 10,
            'documents_loaded' => 10,
            'copies_expected' => 2,
            'copies_loaded' => 2,
            'fields_expected' => 25,
            'fields_loaded' => 25,
            'validation' => ['validated_sha256' => str_repeat('b', 64), 'valid' => true],
            'reconciliation' => ['documents' => 10, 'balanced' => true],
            'apply_stats' => ['inserted' => 1, 'updated' => 9],
            'started_at' => '2026-08-28 08:00:00',
            'loaded_at' => '2026-08-28 08:05:00',
            'applied_at' => '2026-08-28 08:10:00',
        ]);
        $this->record = LegacyMarcRecord::query()->create([
            'legacy_import_batch_id' => $this->batch->id,
            'source_doc_id' => 900,
            'source_hash' => str_repeat('c', 64),
            'leader' => '00000nam a2200000 i 4500',
            'record_type' => 'a',
            'bibliographic_level' => 'm',
            'control_number' => 'CN-900',
            'fixed_008_raw' => '260101s2026####xx###########000#0#rus#d',
            'canonical' => ['title' => 'Recovered title'],
            'raw' => ['T200a' => 'raw-title'],
            'mapping_status' => 'matched_control_number',
            'bibliographic_record_id' => $record->id,
            'apply_status' => 'updated',
        ]);
        LegacyMarcField::query()->create([
            'legacy_import_batch_id' => $this->batch->id,
            'source_doc_id' => 900,
            'tag' => '245',
            'indicator1' => '1',
            'indicator2' => '0',
            'subfield_code' => 'a',
            'value' => 'Recovered title',
            'occurrence' => 0,
            'is_known_tag' => true,
            'raw' => ['value' => 'Recovered title'],
        ]);
        LegacyMarcCopy::query()->create([
            'legacy_import_batch_id' => $this->batch->id,
            'source_inv_id' => 700,
            'source_doc_id' => 900,
            'source_hash' => str_repeat('d', 64),
            'relation_status' => 'linked',
            'canonical' => ['inventory_number' => 'INV-700'],
            'raw' => ['T090e' => 'INV-700'],
            'apply_status' => 'inserted',
        ]);

        $this->quarantineId = (int) DB::table('legacy_import_quarantine')->insertGetId([
            'legacy_import_batch_id' => $this->batch->id,
            'kind' => 'orphan_copy',
            'source_doc_id' => 999,
            'source_inv_id' => 77,
            'reason' => 'No matching document',
            'payload' => json_encode(['inventory_number' => 'INV-ORPHAN-77']),
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        $this->conflictId = (int) DB::table('legacy_import_conflicts')->insertGetId([
            'legacy_import_batch_id' => $this->batch->id,
            'entity_type' => 'bibliographic_record',
            'entity_id' => $record->id,
            'source_id' => 900,
            'field_name' => 'title',
            'current_value' => 'Current title',
            'incoming_value' => 'Legacy title',
            'reason' => 'manual_edit_after_import',
            'status' => 'open',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        DB::table('contributors')->insert([
            'name' => 'Recovery Author',
            'normalized_name' => 'recovery author',
            'kind' => 'person',
            'created_at' => now(),
            'updated_at' => now(),
        ]);
        DB::table('subjects')->insert([
            'term' => 'Recovery',
            'normalized_term' => 'recovery',
            'scheme' => 'topical',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $book = KsuBook::query()->create([
            'code' => 'KSU-1',
            'name' => 'Part 1 — receipt',
            'legacy_source_table' => 'ksu1',
            'numbering_format' => 'number/year',
            'auto_numbering_enabled' => false,
            'numbering_rule_evidence' => 'INV.T990t exact historic links only',
            'requires_manual_decision' => true,
            'is_active' => true,
        ]);
        KsuSequence::query()->create([
            'ksu_book_id' => $book->id,
            'year' => 2026,
            'last_number' => 36,
            'min_observed' => 1,
            'max_observed' => 36,
            'missing_numbers' => [7],
            'duplicate_numbers' => [12],
            'allocation_enabled' => false,
        ]);
        KsuConflict::query()->create([
            'ksu_book_id' => $book->id,
            'kind' => 'unresolved_link',
            'ksu_number_raw' => '36/2026',
            'source_inv_id' => 77,
            'reason' => 'No exact historic materialized entry',
            'payload' => ['T990t' => '36/2026'],
            'status' => 'open',
        ]);
    }

    /** @return list<string> */
    private function readUrls(): array
    {
        return [
            '/__tests/admin/library-recovery',
            '/__tests/admin/library-recovery/batches/'.$this->batch->id,
            '/__tests/admin/library-recovery/raw',
            '/__tests/admin/library-recovery/raw/'.$this->record->id,
            '/__tests/admin/library-recovery/quarantine',
            '/__tests/admin/library-recovery/quarantine/'.$this->quarantineId,
            '/__tests/admin/library-recovery/conflicts',
            '/__tests/admin/library-recovery/conflicts/'.$this->conflictId,
        ];
    }

    /** @return array<string,list<array<string,mixed>>> */
    private function recoverySnapshot(): array
    {
        $tables = [
            'legacy_import_batches', 'legacy_marc_records', 'legacy_marc_fields',
            'legacy_marc_copies', 'legacy_import_quarantine', 'legacy_import_conflicts',
            'ksu_books', 'ksu_sequences', 'ksu_conflicts',
        ];

        return collect($tables)->mapWithKeys(fn (string $table): array => [
            $table => DB::table($table)->orderBy('id')->get()->map(
                static fn (object $row): array => (array) $row,
            )->all(),
        ])->all();
    }
}
