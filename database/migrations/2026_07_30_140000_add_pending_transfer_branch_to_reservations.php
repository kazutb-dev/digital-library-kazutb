<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * §8.2 logistics note. Inter-branch transfer is not automated anywhere in the
 * system: this is a manual informational marker only — "the copy is on its way
 * from branch X" — so librarians at different desks can coordinate. It moves
 * no stock and triggers no workflow.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->foreignId('pending_transfer_branch_id')->nullable()->after('assigned_copy_id')
                ->constrained('branches')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('reservations', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('pending_transfer_branch_id');
        });
    }
};
