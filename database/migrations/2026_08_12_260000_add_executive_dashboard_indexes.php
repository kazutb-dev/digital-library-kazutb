<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /** @var array<string, array{table: string, columns: list<string>}> */
    private const INDEXES = [
        'dashboard_loans_issue_reader_idx' => ['table' => 'loans', 'columns' => ['issued_at', 'user_id']],
        'dashboard_loans_return_status_idx' => ['table' => 'loans', 'columns' => ['returned_at', 'status']],
        'dashboard_visits_date_reader_idx' => ['table' => 'library_visits', 'columns' => ['scanned_at', 'user_id']],
        'dashboard_copies_status_condition_idx' => ['table' => 'book_copies', 'columns' => ['status', 'condition']],
        'dashboard_copies_record_usage_idx' => ['table' => 'book_copies', 'columns' => ['bibliographic_record_id', 'issue_count']],
        'dashboard_fines_status_idx' => ['table' => 'fines', 'columns' => ['status']],
        'dashboard_messages_status_due_idx' => ['table' => 'contact_messages', 'columns' => ['status', 'due_at']],
        'dashboard_records_resource_type_idx' => ['table' => 'bibliographic_records', 'columns' => ['resource_type']],
        'dashboard_records_udc_idx' => ['table' => 'bibliographic_records', 'columns' => ['udc_code']],
        'dashboard_external_publication_active_idx' => ['table' => 'external_resources', 'columns' => ['publication_status', 'is_active']],
        'dashboard_external_contract_expiry_idx' => ['table' => 'external_resources', 'columns' => ['resource_type', 'contract_ends_at']],
        'dashboard_news_event_start_idx' => ['table' => 'news', 'columns' => ['status', 'type', 'starts_at']],
    ];

    public function up(): void
    {
        foreach (self::INDEXES as $name => $definition) {
            if (! $this->canIndex($definition['table'], $definition['columns']) || Schema::hasIndex($definition['table'], $name)) {
                continue;
            }

            Schema::table($definition['table'], static function (Blueprint $table) use ($definition, $name): void {
                $table->index($definition['columns'], $name);
            });
        }
    }

    public function down(): void
    {
        foreach (array_reverse(self::INDEXES, true) as $name => $definition) {
            if (! Schema::hasTable($definition['table']) || ! Schema::hasIndex($definition['table'], $name)) {
                continue;
            }

            Schema::table($definition['table'], static function (Blueprint $table) use ($name): void {
                $table->dropIndex($name);
            });
        }
    }

    /** @param list<string> $columns */
    private function canIndex(string $table, array $columns): bool
    {
        if (! Schema::hasTable($table)) {
            return false;
        }

        foreach ($columns as $column) {
            if (! Schema::hasColumn($table, $column)) {
                return false;
            }
        }

        return true;
    }
};
