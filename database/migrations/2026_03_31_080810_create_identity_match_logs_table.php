<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('identity_match_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('session_user_id')->nullable()->index();
            $table->string('session_email')->nullable()->index();
            $table->string('session_ad_login')->nullable();
            $table->uuid('matched_reader_id')->nullable()->index();
            $table->string('matched_by')->comment('email|ad_login|no_match|unknown');
            $table->integer('candidate_count')->default(0);
            $table->boolean('has_ambiguity')->default(false);
            $table->text('ambiguity_details')->nullable();
            $table->boolean('is_stale')->default(false);
            $table->string('stale_reason')->nullable();
            $table->text('context_notes')->nullable();
            $table->timestamps();
            $table->index(['matched_by', 'created_at']);
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('identity_match_logs');
    }
};
