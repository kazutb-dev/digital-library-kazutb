<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Turn the original branch/pilot inventory screen into a complete collection
 * inventory workflow. Existing sessions remain branch-scoped; recovered
 * location evidence is never inferred or normalised by this migration.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_sessions', function (Blueprint $table): void {
            $table->string('scope_type', 32)->default('branch')->after('session_number');
            $table->string('storage_sigla', 64)->nullable()->after('fund_id');
            $table->string('service_point_code', 64)->nullable()->after('storage_sigla');
        });

        // A global or sigla-scoped session need not belong to one branch.
        Schema::table('inventory_sessions', function (Blueprint $table): void {
            $table->foreignId('branch_id')->nullable()->change();
            $table->index(
                ['scope_type', 'status', 'branch_id', 'fund_id'],
                'inventory_sessions_scope_status_idx',
            );
        });
    }

    public function down(): void
    {
        Schema::table('inventory_sessions', function (Blueprint $table): void {
            $table->dropIndex('inventory_sessions_scope_status_idx');
            $table->dropColumn(['scope_type', 'storage_sigla', 'service_point_code']);
        });

        // Restoring NOT NULL is safe only when no global sessions were made.
        if (! DB::table('inventory_sessions')->whereNull('branch_id')->exists()) {
            Schema::table('inventory_sessions', function (Blueprint $table): void {
                $table->foreignId('branch_id')->nullable(false)->change();
            });
        }
    }
};
