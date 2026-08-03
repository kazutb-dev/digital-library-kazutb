<?php

use App\Models\NotificationSetting;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('notification_settings', function (Blueprint $table): void {
            $table->id();
            $table->string('event_type', 64)->unique();
            $table->boolean('in_app_enabled')->default(true);
            $table->boolean('email_enabled')->default(true);
            $table->timestampsTz();
        });

        // Canonical event dictionary. Seeded here (not in a seeder) so every
        // environment gets the full matrix without re-running seeders on a
        // live database. Config toggles only — delivery wiring arrives with
        // the circulation/reservation modules.
        $now = now('UTC');
        DB::table('notification_settings')->insert(array_map(
            static fn (string $eventType): array => [
                'event_type' => $eventType,
                'in_app_enabled' => true,
                'email_enabled' => true,
                'created_at' => $now,
                'updated_at' => $now,
            ],
            NotificationSetting::EVENT_TYPES,
        ));
    }

    public function down(): void
    {
        Schema::dropIfExists('notification_settings');
    }
};
