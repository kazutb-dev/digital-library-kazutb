<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement(<<<'SQL'
            CREATE INDEX IF NOT EXISTS book_copies_branch_record_active_idx
            ON book_copies (branch_id, bibliographic_record_id)
            WHERE status <> 'written_off'
        SQL);
        DB::statement(<<<'SQL'
            CREATE INDEX IF NOT EXISTS book_copies_fund_record_active_idx
            ON book_copies (fund_id, bibliographic_record_id)
            WHERE status <> 'written_off'
        SQL);
    }

    public function down(): void
    {
        if (DB::getDriverName() !== 'pgsql') {
            return;
        }

        DB::statement('DROP INDEX IF EXISTS book_copies_branch_record_active_idx');
        DB::statement('DROP INDEX IF EXISTS book_copies_fund_record_active_idx');
    }
};
