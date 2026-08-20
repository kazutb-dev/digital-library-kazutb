<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (! Schema::hasTable('book_copies')) {
            return;
        }

        Schema::table('book_copies', function (Blueprint $table): void {
            $table->index(
                ['bibliographic_record_id', 'access_restriction'],
                'book_copies_record_access_idx'
            );
            $table->index(
                ['bibliographic_record_id', 'registration_date'],
                'book_copies_record_registration_idx'
            );
        });

        Schema::table('bibliographic_records', function (Blueprint $table): void {
            $table->index(
                ['publication_year', 'title'],
                'bibliographic_records_year_title_idx'
            );
            $table->index(
                ['created_at', 'title'],
                'bibliographic_records_created_title_idx'
            );
        });
    }

    public function down(): void
    {
        if (Schema::hasTable('book_copies')) {
            Schema::table('book_copies', function (Blueprint $table): void {
                $table->dropIndex('book_copies_record_access_idx');
                $table->dropIndex('book_copies_record_registration_idx');
            });
        }

        if (Schema::hasTable('bibliographic_records')) {
            Schema::table('bibliographic_records', function (Blueprint $table): void {
                $table->dropIndex('bibliographic_records_year_title_idx');
                $table->dropIndex('bibliographic_records_created_title_idx');
            });
        }
    }
};
