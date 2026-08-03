<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('book_copies', function (Blueprint $table): void {
            $table->string('supplier_name')->nullable()->after('acquisition_source');
        });
    }

    public function down(): void
    {
        Schema::table('book_copies', function (Blueprint $table): void {
            $table->dropColumn('supplier_name');
        });
    }
};
