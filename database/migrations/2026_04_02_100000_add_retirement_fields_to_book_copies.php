<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        // app.book_copies belongs to the external legacy schema and was never
        // provisioned in every environment. An unguarded ALTER hard-fails the
        // whole migrate run (and the container entrypoint), so skip when the
        // table is absent.
        if (! $this->legacyTableExists()) {
            return;
        }

        DB::statement('
            ALTER TABLE app.book_copies
                ADD COLUMN IF NOT EXISTS retired_at             TIMESTAMPTZ NULL,
                ADD COLUMN IF NOT EXISTS retirement_reason_code TEXT        NULL,
                ADD COLUMN IF NOT EXISTS retirement_note        TEXT        NULL
        ');
    }

    public function down(): void
    {
        if (! $this->legacyTableExists()) {
            return;
        }

        DB::statement('
            ALTER TABLE app.book_copies
                DROP COLUMN IF EXISTS retired_at,
                DROP COLUMN IF EXISTS retirement_reason_code,
                DROP COLUMN IF EXISTS retirement_note
        ');
    }

    private function legacyTableExists(): bool
    {
        try {
            return DB::connection()->getDriverName() === 'pgsql'
                && DB::selectOne("SELECT to_regclass('app.book_copies') AS reg")?->reg !== null;
        } catch (Throwable) {
            return false;
        }
    }
};
