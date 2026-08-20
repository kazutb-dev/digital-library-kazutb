<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * A partner named in the specification is a valid catalogue draft even
     * before the library has confirmed its destination URL.  Published
     * records still require a safe destination at the application boundary.
     */
    public function up(): void
    {
        Schema::table('external_resources', function (Blueprint $table): void {
            $table->string('url', 2048)->nullable()->change();
        });

        // Historic installations treated an enabled row as sufficient for
        // publication. A paid licence without a verified end date is not
        // publication-ready and must be completed by staff, not guessed.
        DB::table('external_resources')
            ->where('resource_type', 'licensed')
            ->whereNull('contract_ends_at')
            ->whereNull('license_expires_at')
            ->update([
                'is_active' => false,
                'publication_status' => 'draft',
                'published_at' => null,
            ]);
    }

    public function down(): void
    {
        DB::table('external_resources')->whereNull('url')->update(['url' => '']);

        Schema::table('external_resources', function (Blueprint $table): void {
            $table->string('url', 2048)->nullable(false)->change();
        });
    }
};
