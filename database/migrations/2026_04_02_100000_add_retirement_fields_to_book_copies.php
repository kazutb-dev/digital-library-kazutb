<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::statement('
            ALTER TABLE app.book_copies
                ADD COLUMN IF NOT EXISTS retired_at             TIMESTAMPTZ NULL,
                ADD COLUMN IF NOT EXISTS retirement_reason_code TEXT        NULL,
                ADD COLUMN IF NOT EXISTS retirement_note        TEXT        NULL
        ');
    }

    public function down(): void
    {
        DB::statement('
            ALTER TABLE app.book_copies
                DROP COLUMN IF EXISTS retired_at,
                DROP COLUMN IF EXISTS retirement_reason_code,
                DROP COLUMN IF EXISTS retirement_note
        ');
    }
};
