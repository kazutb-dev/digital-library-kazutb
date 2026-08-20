<?php

use Carbon\CarbonImmutable;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    private const ANALYTICS_RETENTION_DAYS = 395;

    /** @var list<string> */
    private const RETAINED_EVENT_TYPES = [
        'card_view',
        'outbound_click',
        'access_denied',
        'expired_click',
        'unsafe_destination',
        'health_check',
    ];

    public function up(): void
    {
        Schema::table('external_resources', function (Blueprint $table): void {
            $table->string('health_check_url', 2048)->nullable()->after('url');
            $table->uuid('health_incident_id')->nullable()->after('health_status');
            $table->timestampTz('health_incident_started_at')->nullable()->after('health_incident_id');
        });

        Schema::table('external_resource_events', function (Blueprint $table): void {
            $table->string('dedupe_key', 64)->nullable()->after('event_type');
            $table->timestampTz('retention_until')->nullable()->after('metadata');
        });

        $this->backfillEventGovernance();

        Schema::table('external_resource_events', function (Blueprint $table): void {
            $table->unique('dedupe_key', 'external_resource_events_dedupe_unique');
            $table->index('retention_until', 'external_resource_events_retention_idx');
        });

        Schema::create('external_resource_notification_outboxes', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('external_resource_id')->constrained()->cascadeOnDelete();
            $table->string('dedupe_key', 64)->unique();
            $table->string('notification_type', 64);
            $table->json('payload');
            $table->string('status', 24)->default('pending');
            $table->unsignedSmallInteger('attempts')->default(0);
            $table->timestampTz('available_at')->useCurrent();
            $table->timestampTz('locked_at')->nullable();
            $table->timestampTz('processed_at')->nullable();
            $table->string('last_error', 255)->nullable();
            $table->timestampsTz();

            $table->index(
                ['status', 'available_at'],
                'external_resource_notification_outbox_ready_idx',
            );
        });

        $this->backfillDeliveredReminderOutbox();
    }

    public function down(): void
    {
        Schema::dropIfExists('external_resource_notification_outboxes');

        Schema::table('external_resource_events', function (Blueprint $table): void {
            $table->dropUnique('external_resource_events_dedupe_unique');
            $table->dropIndex('external_resource_events_retention_idx');
            $table->dropColumn(['dedupe_key', 'retention_until']);
        });

        Schema::table('external_resources', function (Blueprint $table): void {
            $table->dropColumn([
                'health_check_url',
                'health_incident_id',
                'health_incident_started_at',
            ]);
        });
    }

    private function backfillEventGovernance(): void
    {
        $seenReminderKeys = [];

        DB::table('external_resource_events')
            ->select(['id', 'external_resource_id', 'event_type', 'metadata', 'created_at'])
            ->orderBy('id')
            ->chunkById(500, function ($events) use (&$seenReminderKeys): void {
                foreach ($events as $event) {
                    $updates = [];
                    $eventType = (string) $event->event_type;

                    if (in_array($eventType, self::RETAINED_EVENT_TYPES, true)) {
                        $updates['retention_until'] = CarbonImmutable::parse((string) $event->created_at, 'UTC')
                            ->addDays(self::ANALYTICS_RETENTION_DAYS);
                    }

                    if (str_starts_with($eventType, 'licence_notice_')) {
                        $metadata = is_array($event->metadata)
                            ? $event->metadata
                            : json_decode((string) $event->metadata, true);
                        $expiryDate = is_array($metadata) ? ($metadata['expiry_date'] ?? null) : null;
                        if (is_string($expiryDate) && $expiryDate !== '') {
                            $key = hash('sha256', implode('|', [
                                'licence_notice',
                                (string) $event->external_resource_id,
                                $eventType,
                                $expiryDate,
                            ]));
                            if (! isset($seenReminderKeys[$key])) {
                                $updates['dedupe_key'] = $key;
                                $seenReminderKeys[$key] = true;
                            }
                        }
                    }

                    if ($updates !== []) {
                        DB::table('external_resource_events')->where('id', $event->id)->update($updates);
                    }
                }
            });
    }

    private function backfillDeliveredReminderOutbox(): void
    {
        DB::table('external_resource_events')
            ->whereNotNull('dedupe_key')
            ->where('event_type', 'like', 'licence_notice_%')
            ->select(['id', 'external_resource_id', 'dedupe_key', 'metadata', 'created_at'])
            ->orderBy('id')
            ->chunkById(500, function ($events): void {
                foreach ($events as $event) {
                    $metadata = is_array($event->metadata)
                        ? $event->metadata
                        : json_decode((string) $event->metadata, true);
                    DB::table('external_resource_notification_outboxes')->insertOrIgnore([
                        'external_resource_id' => $event->external_resource_id,
                        'dedupe_key' => $event->dedupe_key,
                        'notification_type' => 'licence_expiry',
                        'payload' => json_encode(is_array($metadata) ? $metadata : []),
                        'status' => 'delivered',
                        'attempts' => 1,
                        'available_at' => $event->created_at,
                        'processed_at' => $event->created_at,
                        'created_at' => $event->created_at,
                        'updated_at' => $event->created_at,
                    ]);
                }
            }, 'id', 'id');
    }
};
