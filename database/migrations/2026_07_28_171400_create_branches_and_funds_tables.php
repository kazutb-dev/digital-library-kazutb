<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('branches', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('type', 32)->default('service_point');
            $table->text('description')->nullable();
            $table->string('address')->nullable();
            $table->string('phone', 64)->nullable();
            $table->string('email')->nullable();
            $table->json('opening_hours')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(
                ['is_active', 'sort_order'],
                'branches_active_sort_idx'
            );
        });

        Schema::create('funds', function (Blueprint $table) {
            $table->id();
            $table->foreignId('branch_id')
                ->nullable()
                ->constrained('branches')
                ->nullOnDelete();
            $table->string('code')->unique();
            $table->string('name');
            $table->string('fund_type', 32);
            $table->string('institutional_scope', 48)->default('general');
            $table->text('description')->nullable();
            $table->string('location')->nullable();
            $table->boolean('is_active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(
                ['fund_type', 'institutional_scope'],
                'funds_type_scope_idx'
            );
            $table->index(
                ['is_active', 'sort_order'],
                'funds_active_sort_idx'
            );
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('funds');
        Schema::dropIfExists('branches');
    }
};
