<?php

namespace Tests\Concerns;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/** Focused in-memory schema for physical movement and write-off workflows. */
trait BuildsCopyLifecycleOperations
{
    use BuildsAcquisitionOperations;

    protected function setUpCopyLifecycleOperations(): void
    {
        $this->setUpAcquisitionOperations();

        // BuildsAcquisitionOperations supplies a tiny notification placeholder
        // for view tests. The real reservation migration owns this table in
        // lifecycle tests and production.
        Schema::dropIfExists('reader_notifications');

        foreach ([
            'database/migrations/2026_07_29_122000_create_readers_circulation_tables.php',
            'database/migrations/2026_07_30_150000_add_barcode_to_reader_profiles.php',
            'database/migrations/2026_07_29_123000_create_reservations_notifications_tables.php',
            'database/migrations/2026_07_30_140000_add_pending_transfer_branch_to_reservations.php',
            'database/migrations/2026_07_30_170000_create_circulation_incident_workflow.php',
            'database/migrations/2026_08_03_000000_create_production_circulation_workflows.php',
        ] as $path) {
            (require base_path($path))->up();
        }

        // The shared acquisition concern already supplies legacy IDs. These
        // lifecycle tests only need the write-off subset of the much broader
        // MARC recovery extension, so keep the focused schema composable.
        if (! Schema::hasColumn('book_copies', 'writeoff_date')) {
            Schema::table('book_copies', function (Blueprint $table): void {
                $table->date('writeoff_date')->nullable();
                $table->string('writeoff_act', 128)->nullable();
                $table->string('writeoff_reason', 255)->nullable();
            });
        }
    }
}
