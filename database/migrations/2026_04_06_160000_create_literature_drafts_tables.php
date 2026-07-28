<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('literature_drafts', function (Blueprint $table) {
            $table->id();
            $table->string('user_id', 255)->index();
            $table->string('title', 500)->nullable();
            $table->text('notes')->nullable();
            $table->timestamps();

            $table->unique('user_id');
        });

        Schema::create('literature_draft_items', function (Blueprint $table) {
            $table->id();
            $table->foreignId('draft_id')->constrained('literature_drafts')->cascadeOnDelete();
            $table->string('identifier', 255);
            $table->string('title', 1000);
            $table->string('type', 50)->default('book');
            $table->string('author', 500)->nullable();
            $table->string('publisher', 500)->nullable();
            $table->string('year', 10)->nullable();
            $table->string('language', 50)->nullable();
            $table->string('isbn', 30)->nullable();
            $table->integer('available')->nullable();
            $table->integer('total')->nullable();
            $table->string('url', 2048)->nullable();
            $table->string('provider', 500)->nullable();
            $table->string('access_type', 50)->nullable();
            $table->timestamp('added_at')->nullable();
            $table->timestamps();

            $table->unique(['draft_id', 'identifier']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('literature_draft_items');
        Schema::dropIfExists('literature_drafts');
    }
};
