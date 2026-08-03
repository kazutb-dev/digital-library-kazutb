<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('category', 32);
            $table->string('subject');
            $table->text('body');
            $table->foreignId('sender_id')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('sender_email');
            $table->string('status', 32)->default('open');
            $table->foreignId('assigned_to')
                ->nullable()
                ->constrained('users')
                ->nullOnDelete();
            $table->string('priority', 16)->default('normal');
            $table->text('resolution_comment')->nullable();
            $table->json('attachments')->nullable();
            $table->timestampTz('resolved_at')->nullable();
            $table->timestampsTz();
            $table->softDeletesTz();

            $table->index(
                ['status', 'created_at'],
                'contact_messages_status_created_idx'
            );
            $table->index(
                ['category', 'created_at'],
                'contact_messages_category_created_idx'
            );
            $table->index(
                ['assigned_to', 'status'],
                'contact_messages_assignee_status_idx'
            );
            $table->index(
                ['priority', 'status'],
                'contact_messages_priority_status_idx'
            );
        });

        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            Schema::getConnection()->statement(
                "ALTER TABLE contact_messages ADD CONSTRAINT contact_messages_status_check CHECK (status IN ('open', 'in_review', 'resolved', 'archived'))"
            );
            Schema::getConnection()->statement(
                "ALTER TABLE contact_messages ADD CONSTRAINT contact_messages_priority_check CHECK (priority IN ('low', 'normal', 'high', 'urgent'))"
            );
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
