<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        DB::statement('CREATE SCHEMA IF NOT EXISTS app');

        Schema::create('app.circulation_loans', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('reader_id')->index();
            $table->uuid('copy_id')->index();
            $table->string('status', 32)->default('active')->index();
            $table->timestampTz('issued_at');
            $table->timestampTz('due_at');
            $table->timestampTz('returned_at')->nullable();
            $table->unsignedInteger('renew_count')->default(0);
            $table->timestampTz('created_at')->nullable();
            $table->timestampTz('updated_at')->nullable();

            $table->index(['reader_id', 'status', 'due_at'], 'idx_circ_loans_reader_status_due');
            $table->index(['copy_id', 'status'], 'idx_circ_loans_copy_status');
        });

        DB::statement(
            <<<'SQL'
            ALTER TABLE app.circulation_loans
            ADD CONSTRAINT ck_circ_loans_due_after_issue
            CHECK (due_at >= issued_at)
            SQL
        );

        DB::statement(
            <<<'SQL'
            ALTER TABLE app.circulation_loans
            ADD CONSTRAINT ck_circ_loans_return_after_issue
            CHECK (returned_at IS NULL OR returned_at >= issued_at)
            SQL
        );

        DB::statement(
            <<<'SQL'
            ALTER TABLE app.circulation_loans
            ADD CONSTRAINT ck_circ_loans_renew_non_negative
            CHECK (renew_count >= 0)
            SQL
        );

        DB::statement(
            <<<'SQL'
            ALTER TABLE app.circulation_loans
            ADD CONSTRAINT ck_circ_loans_status
            CHECK (status IN ('active', 'returned'))
            SQL
        );

        DB::statement(
            <<<'SQL'
            CREATE UNIQUE INDEX ux_circ_loans_active_copy
            ON app.circulation_loans (copy_id)
            WHERE status = 'active' AND returned_at IS NULL
            SQL
        );
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('app.circulation_loans');
    }
};
