<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE users ALTER COLUMN locale DROP NOT NULL');
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::table('users')->whereNull('locale')->update(['locale' => 'kk']);
            DB::statement('ALTER TABLE users ALTER COLUMN locale SET NOT NULL');
        }
    }
};
