<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('message_categories', function (Blueprint $table): void {
            $table->id();
            $table->string('slug', 96)->unique();
            $table->string('message_type', 32);
            $table->string('name_kk');
            $table->string('name_ru');
            $table->string('name_en');
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->string('default_priority', 16)->default('medium');
            $table->string('default_assignee_role', 64)->nullable();
            $table->boolean('requires_director_review')->default(false);
            $table->unsignedInteger('sla_hours')->default(72);
            $table->json('allowed_attachment_types')->nullable();
            $table->timestampsTz();
            $table->index(['message_type', 'active', 'sort_order']);
        });

        Schema::create('message_routing_rules', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('message_type', 32)->nullable();
            $table->foreignId('category_id')->nullable()->constrained('message_categories')->nullOnDelete();
            $table->foreignId('branch_id')->nullable()->constrained('branches')->nullOnDelete();
            $table->string('priority', 16)->nullable();
            $table->string('target_role', 64);
            $table->boolean('director_visibility')->default(false);
            $table->boolean('active')->default(true);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
            $table->index(['active', 'message_type', 'category_id', 'sort_order'], 'message_routing_match_idx');
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE contact_messages DROP CONSTRAINT IF EXISTS contact_messages_status_check');
            DB::statement('ALTER TABLE contact_messages DROP CONSTRAINT IF EXISTS contact_messages_priority_check');
        }

        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable()->unique()->after('id');
            $table->string('ticket_number', 32)->nullable()->unique()->after('public_id');
            $table->foreignId('user_id')->nullable()->after('ticket_number')->constrained('users')->nullOnDelete();
            $table->foreignId('reader_profile_id')->nullable()->after('user_id')->constrained('reader_profiles')->nullOnDelete();
            $table->string('type', 32)->default('request')->after('reader_profile_id');
            $table->foreignId('category_id')->nullable()->after('category')->constrained('message_categories')->nullOnDelete();
            $table->string('source', 32)->default('cabinet')->after('body');
            $table->string('preferred_locale', 8)->default('kk')->after('source');
            $table->string('preferred_contact_channel', 16)->default('in_app')->after('preferred_locale');
            $table->string('sender_name_snapshot')->nullable()->after('sender_email');
            $table->string('sender_email_snapshot')->nullable()->after('sender_name_snapshot');
            $table->string('sender_phone_snapshot', 64)->nullable()->after('sender_email_snapshot');
            $table->string('reader_ticket_snapshot', 64)->nullable()->after('sender_phone_snapshot');
            $table->foreignId('branch_id')->nullable()->after('reader_ticket_snapshot')->constrained('branches')->nullOnDelete();
            $table->string('related_entity_type', 64)->nullable()->after('branch_id');
            $table->string('related_entity_id', 191)->nullable()->after('related_entity_type');
            $table->foreignId('complaint_against_user_id')->nullable()->after('related_entity_id')->constrained('users')->nullOnDelete();
            $table->foreignId('assigned_by')->nullable()->after('assigned_to')->constrained('users')->nullOnDelete();
            $table->timestampTz('assigned_at')->nullable()->after('assigned_by');
            $table->foreignId('reviewed_by')->nullable()->after('assigned_at')->constrained('users')->nullOnDelete();
            $table->timestampTz('reviewed_at')->nullable()->after('reviewed_by');
            $table->foreignId('resolved_by')->nullable()->after('resolved_at')->constrained('users')->nullOnDelete();
            $table->foreignId('rejected_by')->nullable()->after('resolved_by')->constrained('users')->nullOnDelete();
            $table->timestampTz('rejected_at')->nullable()->after('rejected_by');
            $table->text('rejection_reason')->nullable()->after('rejected_at');
            $table->timestampTz('closed_at')->nullable()->after('rejection_reason');
            $table->timestampTz('due_at')->nullable()->after('closed_at');
            $table->timestampTz('first_response_due_at')->nullable()->after('due_at');
            $table->timestampTz('first_response_at')->nullable()->after('first_response_due_at');
            $table->timestampTz('last_response_at')->nullable()->after('first_response_at');
            $table->timestampTz('last_user_message_at')->nullable()->after('last_response_at');
            $table->timestampTz('last_staff_message_at')->nullable()->after('last_user_message_at');
            $table->timestampTz('sla_paused_at')->nullable()->after('last_staff_message_at');
            $table->unsignedInteger('sla_paused_minutes')->default(0)->after('sla_paused_at');
            $table->boolean('requires_director_review')->default(false)->after('sla_paused_minutes');
            $table->boolean('sensitive')->default(false)->after('requires_director_review');
            $table->unsignedTinyInteger('satisfaction_score')->nullable()->after('sensitive');
            $table->text('satisfaction_comment')->nullable()->after('satisfaction_score');
            $table->string('idempotency_key', 96)->nullable()->unique()->after('satisfaction_comment');
            $table->unsignedInteger('lock_version')->default(0)->after('idempotency_key');

            $table->index(['type', 'status', 'priority'], 'contact_messages_type_status_priority_idx');
            $table->index(['due_at', 'status'], 'contact_messages_due_status_idx');
            $table->index(['user_id', 'created_at'], 'contact_messages_user_created_idx');
            $table->index(['requires_director_review', 'status'], 'contact_messages_director_status_idx');
            $table->index(['related_entity_type', 'related_entity_id'], 'contact_messages_related_idx');
        });

        Schema::create('message_thread_entries', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_message_id')->constrained('contact_messages')->cascadeOnDelete();
            $table->foreignId('author_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('author_type', 32);
            $table->string('entry_type', 48);
            $table->text('body')->nullable();
            $table->string('visibility', 24)->default('public');
            $table->boolean('is_official_response')->default(false);
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('supersedes_id')->nullable()->constrained('message_thread_entries')->nullOnDelete();
            $table->timestampTz('edited_at')->nullable();
            $table->text('edit_reason')->nullable();
            $table->json('metadata')->nullable();
            $table->timestampsTz();
            $table->index(['contact_message_id', 'visibility', 'created_at'], 'message_thread_visibility_idx');
            $table->index(['contact_message_id', 'entry_type', 'created_at'], 'message_thread_type_idx');
        });

        Schema::create('message_attachments', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_message_id')->constrained('contact_messages')->cascadeOnDelete();
            $table->foreignId('thread_entry_id')->nullable()->constrained('message_thread_entries')->cascadeOnDelete();
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->uuid('public_id')->unique();
            $table->string('disk', 32)->default('local');
            $table->string('path', 500);
            $table->string('original_name', 255);
            $table->string('extension', 16);
            $table->string('mime', 128);
            $table->unsignedBigInteger('size');
            $table->string('sha256', 64);
            $table->string('visibility', 24)->default('public');
            $table->string('scan_status', 24)->default('pending_review');
            $table->timestampTz('reviewed_at')->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['contact_message_id', 'visibility']);
            $table->unique(['contact_message_id', 'sha256'], 'message_attachment_dedup_idx');
        });

        Schema::create('message_watchers', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_message_id')->constrained('contact_messages')->cascadeOnDelete();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->foreignId('added_by')->nullable()->constrained('users')->nullOnDelete();
            $table->string('reason', 255)->nullable();
            $table->timestampsTz();
            $table->unique(['contact_message_id', 'user_id']);
        });

        Schema::create('message_sla_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('contact_message_id')->constrained('contact_messages')->cascadeOnDelete();
            $table->string('event_type', 48);
            $table->string('threshold_key', 64);
            $table->timestampTz('triggered_at');
            $table->json('metadata')->nullable();
            $table->timestampsTz();
            $table->unique(['contact_message_id', 'event_type', 'threshold_key'], 'message_sla_event_once_idx');
        });

        $now = now('UTC');
        $fallbacks = [
            ['slug' => 'request-other', 'message_type' => 'request', 'name_kk' => 'Басқа сұрау', 'name_ru' => 'Другой запрос', 'name_en' => 'Other request', 'default_priority' => 'medium', 'default_assignee_role' => 'librarian', 'requires_director_review' => false, 'sla_hours' => 72],
            ['slug' => 'complaint-other', 'message_type' => 'complaint', 'name_kk' => 'Басқа шағым', 'name_ru' => 'Другая жалоба', 'name_en' => 'Other complaint', 'default_priority' => 'high', 'default_assignee_role' => 'senior_librarian', 'requires_director_review' => true, 'sla_hours' => 48],
            ['slug' => 'suggestion-other', 'message_type' => 'suggestion', 'name_kk' => 'Басқа ұсыныс', 'name_ru' => 'Другое предложение', 'name_en' => 'Other suggestion', 'default_priority' => 'low', 'default_assignee_role' => 'director', 'requires_director_review' => true, 'sla_hours' => 120],
            ['slug' => 'question-other', 'message_type' => 'question', 'name_kk' => 'Басқа сұрақ', 'name_ru' => 'Другой вопрос', 'name_en' => 'Other question', 'default_priority' => 'medium', 'default_assignee_role' => 'director', 'requires_director_review' => true, 'sla_hours' => 72],
        ];
        foreach ($fallbacks as $category) {
            DB::table('message_categories')->insert(array_merge($category, [
                'active' => true, 'sort_order' => 999, 'allowed_attachment_types' => json_encode(['jpg', 'jpeg', 'png', 'webp', 'pdf', 'docx']),
                'created_at' => $now, 'updated_at' => $now,
            ]));
        }

        DB::table('contact_messages')->orderBy('id')->get()->each(function (object $legacy) use ($now): void {
            $type = in_array($legacy->category, ['request', 'complaint', 'suggestion', 'question'], true) ? $legacy->category : 'request';
            $priority = match ($legacy->priority) {
                'normal' => 'medium', 'urgent' => 'critical', default => $legacy->priority
            };
            $status = $legacy->status === 'archived' ? 'closed' : $legacy->status;
            $categoryId = DB::table('message_categories')->where('slug', $type.'-other')->value('id');
            $user = $legacy->sender_id ? DB::table('users')->where('id', $legacy->sender_id)->first() : null;
            $profile = $legacy->sender_id ? DB::table('reader_profiles')->where('user_id', $legacy->sender_id)->first() : null;
            $created = $legacy->created_at ? Carbon::parse($legacy->created_at) : $now;
            $publicId = (string) Str::uuid();
            $ticket = sprintf('LIB-%s-%06d', $created->format('Y'), $legacy->id);

            DB::table('contact_messages')->where('id', $legacy->id)->update([
                'public_id' => $publicId, 'ticket_number' => $ticket, 'user_id' => $legacy->sender_id,
                'reader_profile_id' => $profile?->id, 'type' => $type, 'category_id' => $categoryId,
                'priority' => $priority, 'status' => $status, 'preferred_locale' => $user?->locale ?: 'kk',
                'sender_name_snapshot' => $user?->name, 'sender_email_snapshot' => $legacy->sender_email,
                'sender_phone_snapshot' => $profile?->phone, 'reader_ticket_snapshot' => $profile?->ticket_number,
                'last_user_message_at' => $legacy->created_at, 'last_staff_message_at' => $legacy->resolved_at,
                'last_response_at' => $legacy->resolved_at, 'resolved_by' => $legacy->assigned_to,
                'closed_at' => $status === 'closed' ? $legacy->updated_at : null,
            ]);

            $entryId = DB::table('message_thread_entries')->insertGetId([
                'contact_message_id' => $legacy->id, 'author_id' => $legacy->sender_id, 'author_type' => 'user',
                'entry_type' => 'user_message', 'body' => $legacy->body, 'visibility' => 'public',
                'is_official_response' => false, 'version' => 1, 'created_at' => $legacy->created_at ?: $now,
                'updated_at' => $legacy->created_at ?: $now,
            ]);

            foreach ((array) json_decode((string) ($legacy->attachments ?? '[]'), true) as $attachment) {
                $path = is_array($attachment) ? (string) ($attachment['path'] ?? '') : (string) $attachment;
                if ($path === '') {
                    continue;
                }
                DB::table('message_attachments')->insert([
                    'contact_message_id' => $legacy->id, 'thread_entry_id' => $entryId, 'uploaded_by' => $legacy->sender_id,
                    'public_id' => (string) Str::uuid(), 'disk' => 'local', 'path' => $path,
                    'original_name' => is_array($attachment) ? (string) ($attachment['name'] ?? basename($path)) : basename($path),
                    'extension' => mb_strtolower(pathinfo($path, PATHINFO_EXTENSION)),
                    'mime' => is_array($attachment) ? (string) ($attachment['mime'] ?? 'application/octet-stream') : 'application/octet-stream',
                    'size' => is_array($attachment) ? (int) ($attachment['size'] ?? 0) : 0,
                    'sha256' => hash('sha256', $path), 'visibility' => 'public', 'scan_status' => 'legacy_unverified',
                    'created_at' => $legacy->created_at ?: $now, 'updated_at' => $legacy->created_at ?: $now,
                ]);
            }

            if (filled($legacy->resolution_comment)) {
                DB::table('message_thread_entries')->insert([
                    'contact_message_id' => $legacy->id, 'author_id' => $legacy->assigned_to, 'author_type' => 'staff',
                    'entry_type' => 'official_resolution', 'body' => $legacy->resolution_comment, 'visibility' => 'public',
                    'is_official_response' => true, 'version' => 1, 'created_at' => $legacy->resolved_at ?: $legacy->updated_at ?: $now,
                    'updated_at' => $legacy->resolved_at ?: $legacy->updated_at ?: $now,
                ]);
            }
        });

        if (DB::getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE contact_messages ADD CONSTRAINT contact_messages_status_check CHECK (status IN ('open','in_review','waiting_for_user','response_prepared','resolved','rejected','closed','reopened'))");
            DB::statement("ALTER TABLE contact_messages ADD CONSTRAINT contact_messages_priority_check CHECK (priority IN ('low','medium','high','critical'))");
        }
    }

    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE contact_messages DROP CONSTRAINT IF EXISTS contact_messages_status_check');
            DB::statement('ALTER TABLE contact_messages DROP CONSTRAINT IF EXISTS contact_messages_priority_check');
        }

        Schema::dropIfExists('message_sla_events');
        Schema::dropIfExists('message_watchers');
        Schema::dropIfExists('message_attachments');
        Schema::dropIfExists('message_thread_entries');
        Schema::dropIfExists('message_routing_rules');

        Schema::table('contact_messages', function (Blueprint $table): void {
            $table->dropForeign(['category_id']);
            $table->dropForeign(['user_id']);
            $table->dropForeign(['reader_profile_id']);
            $table->dropForeign(['branch_id']);
            $table->dropForeign(['complaint_against_user_id']);
            $table->dropForeign(['assigned_by']);
            $table->dropForeign(['reviewed_by']);
            $table->dropForeign(['resolved_by']);
            $table->dropForeign(['rejected_by']);
            $table->dropColumn([
                'public_id', 'ticket_number', 'user_id', 'reader_profile_id', 'type', 'category_id', 'source',
                'preferred_locale', 'preferred_contact_channel', 'sender_name_snapshot', 'sender_email_snapshot',
                'sender_phone_snapshot', 'reader_ticket_snapshot', 'branch_id', 'related_entity_type', 'related_entity_id',
                'complaint_against_user_id', 'assigned_by', 'assigned_at', 'reviewed_by', 'reviewed_at', 'resolved_by',
                'rejected_by', 'rejected_at', 'rejection_reason', 'closed_at', 'due_at', 'first_response_due_at',
                'first_response_at', 'last_response_at', 'last_user_message_at', 'last_staff_message_at', 'sla_paused_at',
                'sla_paused_minutes', 'requires_director_review', 'sensitive', 'satisfaction_score', 'satisfaction_comment',
                'idempotency_key', 'lock_version',
            ]);
        });

        Schema::dropIfExists('message_categories');
    }
};
