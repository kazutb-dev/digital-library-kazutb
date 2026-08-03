<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('funds', function (Blueprint $table): void {
            $table->string('academic_direction', 160)->nullable()->after('institutional_scope')->index();
        });

        DB::table('funds')->where('code', 'UNIVERSITY-TECHNOLOGY')->update([
            'academic_direction' => 'Инженерия',
        ]);
        DB::table('funds')->where('code', 'UNIVERSITY-ECONOMIC')->update([
            'academic_direction' => 'Экономика',
        ]);
    }

    public function down(): void
    {
        Schema::table('funds', function (Blueprint $table): void {
            $table->dropColumn('academic_direction');
        });
    }
};
