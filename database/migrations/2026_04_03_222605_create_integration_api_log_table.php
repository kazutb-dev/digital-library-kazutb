<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            $attached = collect(DB::select('PRAGMA database_list'))->contains(
                fn (object $database): bool => $database->name === 'app'
            );
            if (! $attached) {
                DB::statement("ATTACH DATABASE ':memory:' AS app");
            }

            Schema::create('app.integration_api_log', function (Blueprint $table): void {
                $table->uuid('id')->primary();
                $table->string('client_ref', 128)->index();
                $table->string('source_system', 32);
                $table->string('method', 10);
                $table->string('path', 512)->index();
                $table->unsignedSmallInteger('status_code');
                $table->double('duration_ms');
                $table->string('request_id', 128)->nullable();
                $table->string('correlation_id', 128)->nullable();
                $table->string('ip_address', 45)->nullable();
                $table->timestampTz('logged_at')->useCurrent()->index();
            });

            return;
        }

        DB::connection('pgsql')->statement("
            CREATE TABLE IF NOT EXISTS app.integration_api_log (
                id UUID PRIMARY KEY,
                client_ref VARCHAR(128) NOT NULL,
                source_system VARCHAR(32) NOT NULL,
                method VARCHAR(10) NOT NULL,
                path VARCHAR(512) NOT NULL,
                status_code SMALLINT NOT NULL,
                duration_ms DOUBLE PRECISION NOT NULL,
                request_id VARCHAR(128),
                correlation_id VARCHAR(128),
                ip_address VARCHAR(45),
                logged_at TIMESTAMPTZ NOT NULL DEFAULT NOW()
            )
        ");

        DB::connection('pgsql')->statement("
            CREATE INDEX IF NOT EXISTS idx_api_log_client_ref ON app.integration_api_log(client_ref)
        ");
        DB::connection('pgsql')->statement("
            CREATE INDEX IF NOT EXISTS idx_api_log_logged_at ON app.integration_api_log(logged_at)
        ");
        DB::connection('pgsql')->statement("
            CREATE INDEX IF NOT EXISTS idx_api_log_path ON app.integration_api_log(path)
        ");
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'sqlite') {
            Schema::dropIfExists('app.integration_api_log');

            return;
        }

        DB::connection('pgsql')->statement('DROP TABLE IF EXISTS app.integration_api_log');
    }
};
