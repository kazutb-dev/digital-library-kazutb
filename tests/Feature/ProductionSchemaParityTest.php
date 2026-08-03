<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Schema;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class ProductionSchemaParityTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();

        // The PostgreSQL quality gate migrates its isolated database before
        // PHPUnit starts. Ordinary fast tests build the equivalent schema in
        // SQLite so the same expectations remain executable everywhere.
        if (Schema::getConnection()->getDriverName() !== 'pgsql') {
            $this->setUpAdminControlPlane();
        }
    }

    public function test_reader_cabinet_tables_and_columns_match_application_expectations(): void
    {
        foreach (['reader_profiles', 'literature_drafts', 'literature_draft_items', 'literature_collection_follows'] as $table) {
            $this->assertTrue(Schema::hasTable($table), 'Missing table '.$table);
        }

        $this->assertColumns('reader_profiles', [
            'user_id', 'ticket_number', 'barcode', 'category', 'status', 'phone', 'additional_email',
            'faculty', 'department', 'study_group', 'preferred_branch_id', 'valid_until',
            'notification_preferences', 'accessibility_preferences',
        ]);
        $this->assertColumns('literature_drafts', [
            'user_id', 'slug', 'title', 'description', 'cover_path', 'collection_type', 'visibility',
            'status', 'owner_type', 'target_audience', 'language', 'subject', 'udc', 'created_by', 'published_at',
        ]);
        $this->assertColumns('literature_draft_items', [
            'draft_id', 'bibliographic_record_id', 'identifier', 'sort_order', 'inclusion_reason',
        ]);
        $this->assertColumns('literature_collection_follows', ['collection_id', 'user_id']);
    }

    public function test_circulation_upgrade_columns_and_indexes_exist(): void
    {
        foreach (['reservation_history', 'copy_transfers', 'inventory_sessions', 'inventory_session_items', 'inventory_scans'] as $table) {
            $this->assertTrue(Schema::hasTable($table), 'Missing table '.$table);
        }
        $this->assertColumns('reservations', [
            'reservation_number', 'pickup_branch_id', 'current_branch_id', 'queue_sequence',
            'queued_at', 'confirmed_at', 'ready_at', 'fulfilled_at', 'cancelled_at', 'expired_at',
            'extension_count', 'source', 'priority', 'requires_resolution',
        ]);
        $this->assertColumns('reader_notifications', [
            'channel', 'delivery_status', 'idempotency_key', 'attempts', 'last_error', 'sent_at',
        ]);

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            $reservationIndexes = collect(Schema::getIndexes('reservations'))->pluck('name');
            $this->assertContains('ux_reservations_active_reader_record', $reservationIndexes);
            $this->assertContains('ux_reservations_active_copy', $reservationIndexes);
            $fineIndexes = collect(Schema::getIndexes('fines'))->pluck('name');
            $this->assertContains('ux_fines_loan_reason', $fineIndexes);
        }
    }

    /** @param list<string> $columns */
    private function assertColumns(string $table, array $columns): void
    {
        foreach ($columns as $column) {
            $this->assertTrue(Schema::hasColumn($table, $column), "Missing {$table}.{$column}");
        }
    }
}
