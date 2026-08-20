<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $this->addIndex('book_copies', ['registration_date'], 'report_copies_registration_idx');
        $this->addIndex('book_copies', ['acquisition_date'], 'report_copies_acquisition_idx');
        $this->addIndex('loans', ['issued_at'], 'report_loans_issued_idx');
        $this->addIndex('loans', ['returned_at'], 'report_loans_returned_idx');
        $this->addIndex('reservations', ['created_at'], 'report_reservations_created_idx');
        $this->addIndex('digital_material_access_logs', ['created_at', 'action'], 'report_digital_access_date_action_idx');
        $this->addIndex('external_resource_events', ['created_at', 'event_type'], 'report_external_events_date_type_idx');
        $this->addIndex('users', ['created_at'], 'report_users_created_idx');
        $this->addIndex('repository_items', ['published_at'], 'report_repository_published_idx');
    }

    public function down(): void
    {
        $this->dropIndex('repository_items', 'report_repository_published_idx');
        $this->dropIndex('users', 'report_users_created_idx');
        $this->dropIndex('external_resource_events', 'report_external_events_date_type_idx');
        $this->dropIndex('digital_material_access_logs', 'report_digital_access_date_action_idx');
        $this->dropIndex('reservations', 'report_reservations_created_idx');
        $this->dropIndex('loans', 'report_loans_returned_idx');
        $this->dropIndex('loans', 'report_loans_issued_idx');
        $this->dropIndex('book_copies', 'report_copies_acquisition_idx');
        $this->dropIndex('book_copies', 'report_copies_registration_idx');
    }

    /** @param list<string> $columns */
    private function addIndex(string $table, array $columns, string $name): void
    {
        if (! Schema::hasTable($table)) {
            return;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return;
            }
        }

        Schema::table($table, static function (Blueprint $blueprint) use ($columns, $name): void {
            $blueprint->index($columns, $name);
        });
    }

    private function dropIndex(string $table, string $name): void
    {
        if (! Schema::hasTable($table) || ! Schema::hasIndex($table, $name)) {
            return;
        }

        Schema::table($table, static function (Blueprint $blueprint) use ($name): void {
            $blueprint->dropIndex($name);
        });
    }
};
