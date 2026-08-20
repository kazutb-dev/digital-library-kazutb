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
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE external_resources DROP CONSTRAINT IF EXISTS external_resources_type_check');
        }
        DB::table('external_resources')->where('resource_type', 'open')->update(['resource_type' => 'open_access']);

        Schema::table('electronic_materials', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable()->unique();
            $table->foreignId('repository_item_id')->nullable()->constrained('repository_items')->nullOnDelete();
            $table->string('material_type', 48)->default('book_pdf');
            $table->text('description')->nullable();
            $table->string('original_filename')->nullable();
            $table->string('safe_filename')->nullable();
            $table->string('storage_disk', 32)->default('local');
            $table->string('checksum_sha256', 64)->nullable()->index();
            $table->string('mime_type', 160)->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->string('language', 8)->default('kk');
            $table->text('source')->nullable();
            $table->string('rights_holder')->nullable();
            $table->string('copyright_status', 48)->default('unknown');
            $table->string('licence_type', 64)->nullable();
            $table->text('licence_text')->nullable();
            $table->string('permission_document_path')->nullable();
            $table->date('permission_date')->nullable();
            $table->string('preview_policy', 32)->default('none');
            $table->string('download_policy', 32)->default('disabled');
            $table->string('print_policy', 32)->default('disabled');
            $table->string('copy_policy', 32)->default('disabled');
            $table->json('restricted_roles')->nullable();
            $table->json('restricted_branches')->nullable();
            $table->boolean('campus_only')->default(false);
            $table->timestampTz('embargo_until')->nullable();
            $table->string('workflow_status', 48)->default('uploaded');
            $table->string('processing_status', 32)->default('pending');
            $table->string('ocr_status', 32)->default('not_requested');
            $table->string('text_extraction_status', 32)->default('pending');
            $table->longText('extracted_text')->nullable();
            $table->decimal('ocr_confidence', 5, 2)->nullable();
            $table->string('ocr_language', 16)->nullable();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampTz('published_at')->nullable();
            $table->timestampTz('archived_at')->nullable();
            $table->timestampTz('withdrawn_at')->nullable();
            $table->text('withdrawal_reason')->nullable();
            $table->unsignedInteger('version_number')->default(1);

            $table->index(['workflow_status', 'material_type'], 'electronic_materials_workflow_type_idx');
            $table->index(['access_level', 'published_at'], 'electronic_materials_access_published_idx');
            $table->index('embargo_until');
        });

        Schema::create('electronic_material_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('electronic_material_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('storage_disk', 32)->default('local');
            $table->string('storage_path');
            $table->string('original_filename');
            $table->string('safe_filename');
            $table->string('mime_type', 160);
            $table->unsignedBigInteger('file_size');
            $table->string('checksum_sha256', 64);
            $table->text('change_reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->unique(['electronic_material_id', 'version_number'], 'electronic_material_versions_unique');
        });
        DB::table('electronic_materials')->orderBy('id')->each(function (object $row): void {
            DB::table('electronic_materials')->where('id', $row->id)->update([
                'public_id' => (string) Str::uuid(),
                'workflow_status' => $row->is_active ? 'restricted' : 'archived',
                'download_policy' => $row->allow_download ? 'allowed' : 'disabled',
            ]);
        });

        Schema::create('digital_material_access_logs', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('electronic_material_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('action', 32);
            $table->boolean('allowed');
            $table->string('denial_reason')->nullable();
            $table->string('ip_hash', 64)->nullable();
            $table->string('user_agent_hash', 64)->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['electronic_material_id', 'created_at'], 'digital_access_material_date_idx');
        });

        Schema::table('external_resources', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable()->unique();
            $table->json('name_translations')->nullable();
            $table->json('short_description_translations')->nullable();
            $table->json('description_translations')->nullable();
            $table->json('instructions_translations')->nullable();
            $table->json('content_types')->nullable();
            $table->string('access_method', 48)->default('public_url');
            $table->boolean('guest_access')->default(false);
            $table->boolean('campus_only')->default(false);
            $table->boolean('login_required')->default(false);
            $table->string('contract_number')->nullable();
            $table->date('contract_starts_at')->nullable();
            $table->date('contract_ends_at')->nullable();
            $table->date('renewal_at')->nullable();
            $table->foreignId('responsible_user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('vendor_contact')->nullable();
            $table->text('internal_notes')->nullable();
            $table->string('licence_file_path')->nullable();
            $table->string('statistics_url', 2048)->nullable();
            $table->string('health_status', 32)->default('unchecked');
            $table->timestampTz('last_checked_at')->nullable();
            $table->string('publication_status', 32)->default('draft');
            $table->string('renewal_status', 32)->default('not_required');
            $table->timestampTz('published_at')->nullable();
        });
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE external_resources ADD CONSTRAINT external_resources_type_check CHECK (resource_type IN ('licensed', 'open_access', 'partner', 'internal'))");
        }
        DB::table('external_resources')->orderBy('id')->each(function (object $row): void {
            DB::table('external_resources')->where('id', $row->id)->update([
                'public_id' => (string) Str::uuid(),
                'publication_status' => $row->is_active ? 'published' : 'draft',
                'guest_access' => in_array('guest', json_decode((string) $row->available_roles, true) ?: [], true),
            ]);
        });

        Schema::create('external_resource_events', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('external_resource_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('event_type', 48);
            $table->string('role_name', 96)->nullable();
            $table->json('metadata')->nullable();
            $table->timestampTz('created_at')->useCurrent();
            $table->index(['external_resource_id', 'event_type', 'created_at'], 'external_resource_events_lookup_idx');
        });

        Schema::create('external_resource_contract_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('external_resource_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('contract_number')->nullable();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->date('renewal_at')->nullable();
            $table->string('licence_file_path')->nullable();
            $table->text('change_reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->unique(['external_resource_id', 'version_number'], 'external_resource_contract_versions_unique');
        });

        Schema::table('repository_items', function (Blueprint $table): void {
            $table->uuid('public_id')->nullable()->unique();
            $table->json('title_translations')->nullable();
            $table->string('original_title', 1000)->nullable();
            $table->string('supervisor')->nullable();
            $table->string('reviewer')->nullable();
            $table->string('university')->nullable();
            $table->string('faculty')->nullable();
            $table->string('educational_programme')->nullable();
            $table->string('degree_level', 64)->nullable();
            $table->date('defence_date')->nullable();
            $table->date('publication_date')->nullable();
            $table->json('abstract_translations')->nullable();
            $table->json('keyword_translations')->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->text('bibliography')->nullable();
            $table->string('doi')->nullable()->index();
            $table->string('isbn_issn', 64)->nullable();
            $table->text('source')->nullable();
            $table->string('rights_holder')->nullable();
            $table->string('copyright_status', 48)->default('unknown');
            $table->string('licence_type', 64)->nullable();
            $table->text('licence_text')->nullable();
            $table->string('permission_document_path')->nullable();
            $table->date('permission_date')->nullable();
            $table->string('access_policy', 64)->default('metadata_only');
            $table->timestampTz('embargo_until')->nullable();
            $table->string('checksum_sha256', 64)->nullable();
            $table->unsignedInteger('version_number')->default(1);
            $table->timestampTz('scheduled_for')->nullable();
            $table->timestampTz('withdrawn_at')->nullable();
            $table->text('withdrawal_reason')->nullable();
            $table->foreignId('withdrawn_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('internal_review_notes')->nullable();

            $table->index(['status', 'access_policy'], 'repository_status_access_idx');
            $table->index(['faculty', 'year'], 'repository_faculty_year_idx');
        });
        foreach ([
            'thesis' => 'bachelor_thesis', 'article' => 'scientific_article',
            'report' => 'research_report', 'publication' => 'university_publication',
            'abstract' => 'abstract_of_thesis',
        ] as $oldType => $canonicalType) {
            DB::table('repository_items')->where('work_type', $oldType)->update(['work_type' => $canonicalType]);
        }
        DB::table('repository_items')->where('status', 'under_review')->update(['status' => 'metadata_review']);
        DB::table('repository_items')->orderBy('id')->each(function (object $row): void {
            DB::table('repository_items')->where('id', $row->id)->update(['public_id' => (string) Str::uuid()]);
        });

        Schema::create('repository_authors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repository_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('display_name');
            $table->string('normalised_name')->index();
            $table->string('orcid', 19)->nullable()->index();
            $table->string('affiliation')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
        });

        Schema::create('repository_item_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repository_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('storage_disk', 32)->default('local');
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type', 160);
            $table->string('checksum_sha256', 64);
            $table->text('change_reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->unique(['repository_item_id', 'version_number'], 'repository_item_versions_unique');
        });

        Schema::create('repository_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repository_item_id')->constrained()->cascadeOnDelete();
            $table->string('review_type', 32);
            $table->string('decision', 32);
            $table->text('comment')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
            $table->index(['repository_item_id', 'review_type']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('repository_reviews');
        Schema::dropIfExists('repository_item_versions');
        Schema::dropIfExists('repository_authors');
        Schema::dropIfExists('external_resource_contract_versions');
        Schema::dropIfExists('external_resource_events');
        Schema::dropIfExists('digital_material_access_logs');
        Schema::dropIfExists('electronic_material_versions');

        Schema::table('repository_items', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('withdrawn_by');
            $table->dropColumn(['public_id', 'title_translations', 'original_title', 'supervisor', 'reviewer', 'university', 'faculty', 'educational_programme', 'degree_level', 'defence_date', 'publication_date', 'abstract_translations', 'keyword_translations', 'page_count', 'bibliography', 'doi', 'isbn_issn', 'source', 'rights_holder', 'copyright_status', 'licence_type', 'licence_text', 'permission_document_path', 'permission_date', 'access_policy', 'embargo_until', 'checksum_sha256', 'version_number', 'scheduled_for', 'withdrawn_at', 'withdrawal_reason', 'internal_review_notes']);
        });
        Schema::table('external_resources', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('responsible_user_id');
            $table->dropColumn(['public_id', 'name_translations', 'short_description_translations', 'description_translations', 'instructions_translations', 'content_types', 'access_method', 'guest_access', 'campus_only', 'login_required', 'contract_number', 'contract_starts_at', 'contract_ends_at', 'renewal_at', 'vendor_contact', 'internal_notes', 'licence_file_path', 'statistics_url', 'health_status', 'last_checked_at', 'publication_status', 'renewal_status', 'published_at']);
        });
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE external_resources DROP CONSTRAINT IF EXISTS external_resources_type_check');
        }
        DB::table('external_resources')->where('resource_type', 'open_access')->update(['resource_type' => 'open']);
        if (Schema::getConnection()->getDriverName() === 'pgsql') {
            DB::statement("ALTER TABLE external_resources ADD CONSTRAINT external_resources_type_check CHECK (resource_type IN ('licensed', 'open', 'partner', 'internal'))");
        }
        Schema::table('electronic_materials', function (Blueprint $table): void {
            $table->dropConstrainedForeignId('repository_item_id');
            $table->dropConstrainedForeignId('reviewed_by');
            $table->dropConstrainedForeignId('approved_by');
            $table->dropColumn(['public_id', 'material_type', 'description', 'original_filename', 'safe_filename', 'storage_disk', 'checksum_sha256', 'mime_type', 'page_count', 'language', 'source', 'rights_holder', 'copyright_status', 'licence_type', 'licence_text', 'permission_document_path', 'permission_date', 'preview_policy', 'download_policy', 'print_policy', 'copy_policy', 'restricted_roles', 'restricted_branches', 'campus_only', 'embargo_until', 'workflow_status', 'processing_status', 'ocr_status', 'text_extraction_status', 'extracted_text', 'ocr_confidence', 'ocr_language', 'published_at', 'archived_at', 'withdrawn_at', 'withdrawal_reason', 'version_number']);
        });
    }
};
