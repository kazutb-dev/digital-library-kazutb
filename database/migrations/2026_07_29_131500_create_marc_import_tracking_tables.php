<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('marc_import_records', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('source_doc_id')->unique();
            $table->foreignId('bibliographic_record_id')
                ->unique()
                ->constrained('bibliographic_records')
                ->cascadeOnDelete();
            $table->string('source_hash', 64);
            $table->timestampsTz();
        });

        Schema::create('marc_import_copies', function (Blueprint $table): void {
            $table->id();
            $table->unsignedBigInteger('source_inv_id')->unique();
            $table->foreignId('book_copy_id')
                ->unique()
                ->constrained('book_copies')
                ->cascadeOnDelete();
            $table->string('source_hash', 64);
            $table->timestampsTz();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('marc_import_copies');
        Schema::dropIfExists('marc_import_records');
    }
};
