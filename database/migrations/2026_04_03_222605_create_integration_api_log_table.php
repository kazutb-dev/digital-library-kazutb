<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
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
        DB::connection('pgsql')->statement('DROP TABLE IF EXISTS app.integration_api_log');
    }
};
