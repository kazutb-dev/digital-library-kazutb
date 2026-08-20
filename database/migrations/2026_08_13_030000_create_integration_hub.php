<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('integrations', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->string('code', 80)->unique();
            $table->string('name');
            $table->string('type', 40)->index();
            $table->string('provider')->nullable();
            $table->string('status', 40)->default('disabled')->index();
            $table->string('direction', 24);
            $table->string('transport', 32);
            $table->string('authentication_type', 40)->default('none');
            $table->string('base_url_safe', 512)->nullable();
            $table->boolean('enabled')->default(false)->index();
            $table->string('environment', 32)->default('development');
            $table->string('sync_mode', 32)->default('manual');
            $table->string('health_status', 32)->default('unchecked')->index();
            $table->timestampTz('last_health_check_at')->nullable();
            $table->timestampTz('last_success_at')->nullable();
            $table->timestampTz('last_failure_at')->nullable();
            $table->unsignedInteger('consecutive_failures')->default(0);
            $table->unsignedInteger('last_latency_ms')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('config_version')->default(1);
            $table->json('capabilities')->nullable();
            $table->json('data_policy')->nullable();
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('updated_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('integration_inbox_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->string('external_message_id', 190);
            $table->string('event_type', 100);
            $table->string('payload_hash', 64);
            $table->longText('payload_safe')->nullable();
            $table->unsignedInteger('schema_version')->default(1);
            $table->timestampTz('received_at');
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestampTz('processed_at')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->uuid('correlation_id');
            $table->timestampsTz();
            $table->unique(['integration_id', 'external_message_id'], 'integration_inbox_external_unique');
            $table->index(['integration_id', 'status', 'received_at'], 'integration_inbox_queue_idx');
        });

        Schema::create('integration_outbox_messages', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->string('aggregate_type', 100);
            $table->string('aggregate_id', 190);
            $table->string('event_type', 100);
            $table->longText('payload_safe');
            $table->string('idempotency_key', 190);
            $table->string('destination', 100);
            $table->string('status', 32)->default('pending')->index();
            $table->unsignedInteger('attempts')->default(0);
            $table->timestampTz('next_attempt_at')->nullable()->index();
            $table->timestampTz('locked_at')->nullable();
            $table->timestampTz('sent_at')->nullable();
            $table->timestampTz('acknowledged_at')->nullable();
            $table->string('error_code', 80)->nullable();
            $table->uuid('correlation_id');
            $table->timestampsTz();
            $table->unique(['integration_id', 'idempotency_key'], 'integration_outbox_idempotency_unique');
            $table->index(['integration_id', 'status', 'next_attempt_at'], 'integration_outbox_queue_idx');
        });

        Schema::create('integration_sync_runs', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->string('type', 32);
            $table->timestampTz('started_at');
            $table->timestampTz('completed_at')->nullable();
            $table->string('status', 32)->index();
            $table->unsignedInteger('received')->default(0);
            $table->unsignedInteger('created')->default(0);
            $table->unsignedInteger('updated')->default(0);
            $table->unsignedInteger('unchanged')->default(0);
            $table->unsignedInteger('conflicts')->default(0);
            $table->unsignedInteger('rejected')->default(0);
            $table->unsignedInteger('errors')->default(0);
            $table->string('cursor', 512)->nullable();
            $table->string('error_code', 80)->nullable();
            $table->foreignId('started_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        Schema::create('integration_mappings', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version');
            $table->string('external_field', 128);
            $table->string('local_field', 128);
            $table->string('transform', 80)->default('identity');
            $table->boolean('required')->default(false);
            $table->boolean('active')->default(true);
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['integration_id', 'version', 'external_field'], 'integration_mapping_version_unique');
        });

        Schema::create('integration_conflicts', function (Blueprint $table): void {
            $table->id();
            $table->uuid('uuid')->unique();
            $table->foreignId('integration_id')->constrained()->cascadeOnDelete();
            $table->foreignId('sync_run_id')->nullable()->constrained('integration_sync_runs')->nullOnDelete();
            $table->string('entity_type', 100);
            $table->string('external_id', 190)->nullable();
            $table->string('local_id', 190)->nullable();
            $table->string('field', 128);
            $table->longText('local_value_safe')->nullable();
            $table->longText('external_value_safe')->nullable();
            $table->string('source_of_truth', 32);
            $table->string('status', 32)->default('open')->index();
            $table->string('resolution', 32)->nullable();
            $table->text('resolution_reason')->nullable();
            $table->foreignId('resolved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE integrations ADD CONSTRAINT integrations_status_check CHECK (status IN ('disabled','configured','healthy','degraded','unavailable','credentials_required','configuration_error','contract_expired','maintenance'))");
            DB::statement("ALTER TABLE integrations ADD CONSTRAINT integrations_direction_check CHECK (direction IN ('inbound','outbound','bidirectional'))");
            DB::statement("ALTER TABLE integrations ADD CONSTRAINT integrations_health_check CHECK (health_status IN ('unchecked','healthy','degraded','unavailable'))");
            DB::statement("ALTER TABLE integration_inbox_messages ADD CONSTRAINT integration_inbox_status_check CHECK (status IN ('pending','processing','processed','failed','dead_letter'))");
            DB::statement("ALTER TABLE integration_outbox_messages ADD CONSTRAINT integration_outbox_status_check CHECK (status IN ('pending','processing','sent','acknowledged','failed','dead_letter'))");
            DB::statement("ALTER TABLE integration_sync_runs ADD CONSTRAINT integration_sync_status_check CHECK (status IN ('running','completed','failed','configuration_required','cancelled'))");
            DB::statement("ALTER TABLE integration_conflicts ADD CONSTRAINT integration_conflicts_status_check CHECK (status IN ('open','resolved','ignored'))");
            DB::statement("ALTER TABLE integration_conflicts ADD CONSTRAINT integration_conflicts_source_check CHECK (source_of_truth IN ('local','external','manual','undetermined'))");
            DB::statement("ALTER TABLE integration_conflicts ADD CONSTRAINT integration_conflicts_resolution_check CHECK (resolution IS NULL OR resolution IN ('accept_local','accept_external','manual_fix','ignore'))");
        }

        $now = now('UTC');
        $definitions = [
            ['active_directory', 'Active Directory', 'identity', 'KazUTB', 'bidirectional', 'LDAP', 'service_bind', true, 'configured', ['authentication', 'identity', 'health']],
            ['education_system', 'Educational system', 'education', null, 'inbound', 'REST', 'oauth2', false, 'credentials_required', ['user_sync', 'dry_run', 'reconcile']],
            ['crm', 'CRM', 'crm', null, 'bidirectional', 'REST', 'bearer', false, 'credentials_required', ['user_sync', 'activity_aggregate', 'dry_run', 'reconcile']],
            ['finance', 'Finance', 'finance', null, 'bidirectional', 'REST', 'signed', false, 'credentials_required', ['fine_created', 'payment_confirmation', 'reconcile']],
            ['inventory_accounting', 'Inventory / Accounting', 'inventory', null, 'bidirectional', 'CSV', 'none', false, 'configuration_error', ['acquisitions', 'write_off', 'stock_reconcile']],
            ['email', 'Email', 'notification', 'Laravel Mail', 'outbound', 'message-queue', 'configured_secret', true, 'configured', ['delivery', 'health', 'retry']],
            ['sms', 'SMS', 'notification', null, 'outbound', 'REST', 'api_key', false, 'credentials_required', ['delivery']],
            ['push', 'Push', 'notification', null, 'outbound', 'REST', 'oauth2', false, 'credentials_required', ['delivery']],
            ['external_libraries', 'External Libraries', 'digital_library', null, 'bidirectional', 'manual-import', 'contract', false, 'configured', ['external_link', 'metadata', 'usage', 'licence']],
            ['publishers', 'Publisher Platforms', 'publisher', null, 'inbound', 'REST', 'contract', false, 'credentials_required', ['metadata', 'titles', 'identifiers']],
            ['search_catalogs', 'Search / Interlibrary Catalogs', 'search', null, 'inbound', 'manual-import', 'none', false, 'configured', ['search', 'lookup', 'review_import']],
            ['analytics', 'External Analytics', 'analytics', null, 'outbound', 'REST', 'api_key', false, 'credentials_required', ['aggregate_export']],
        ];
        foreach ($definitions as [$code, $name, $type, $provider, $direction, $transport, $auth, $enabled, $status, $capabilities]) {
            DB::table('integrations')->insert([
                'uuid' => (string) Str::uuid(), 'code' => $code, 'name' => $name, 'type' => $type,
                'provider' => $provider, 'status' => $status, 'direction' => $direction, 'transport' => $transport,
                'authentication_type' => $auth, 'enabled' => $enabled, 'environment' => 'development',
                'sync_mode' => 'manual', 'health_status' => 'unchecked',
                'capabilities' => json_encode($capabilities),
                'data_policy' => json_encode(['pii' => $type === 'identity' || in_array($type, ['education', 'crm'], true), 'detail_level' => $type === 'crm' ? 'aggregate' : 'minimum']),
                'created_at' => $now, 'updated_at' => $now,
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('integration_conflicts');
        Schema::dropIfExists('integration_mappings');
        Schema::dropIfExists('integration_sync_runs');
        Schema::dropIfExists('integration_outbox_messages');
        Schema::dropIfExists('integration_inbox_messages');
        Schema::dropIfExists('integrations');
    }
};
