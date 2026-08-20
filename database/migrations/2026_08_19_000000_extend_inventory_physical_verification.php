<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('book_copies', function (Blueprint $table): void {
            $table->string('room')->nullable()->after('fund_id');
            $table->string('section')->nullable()->after('room');
        });

        Schema::table('inventory_sessions', function (Blueprint $table): void {
            $table->string('section')->nullable()->after('room');
            $table->unsignedSmallInteger('pilot_limit')->nullable()->after('shelf_range');
        });

        Schema::table('inventory_session_items', function (Blueprint $table): void {
            $table->string('inventory_condition', 24)->default('unverified')->after('result');
            $table->string('observed_inventory_number', 128)->nullable()->after('inventory_condition');
            $table->foreignId('verified_by')->nullable()->after('observed_inventory_number')->constrained('users')->nullOnDelete();
            $table->timestampTz('verified_at')->nullable()->after('verified_by');
            $table->timestampTz('location_confirmed_at')->nullable()->after('verified_at');
            $table->timestampTz('location_corrected_at')->nullable()->after('location_confirmed_at');
            $table->unsignedInteger('handling_seconds')->nullable()->after('location_corrected_at');
        });
    }

    public function down(): void
    {
        Schema::table('inventory_session_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('verified_by');
            $table->dropColumn([
                'inventory_condition', 'observed_inventory_number', 'verified_at',
                'location_confirmed_at', 'location_corrected_at', 'handling_seconds',
            ]);
        });
        Schema::table('inventory_sessions', fn (Blueprint $table) => $table->dropColumn(['section', 'pilot_limit']));
        Schema::table('book_copies', fn (Blueprint $table) => $table->dropColumn(['room', 'section']));
    }
};
