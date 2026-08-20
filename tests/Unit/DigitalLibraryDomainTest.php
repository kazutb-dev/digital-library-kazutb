<?php

namespace Tests\Unit;

use App\Models\Catalog\ElectronicMaterial;
use App\Models\Catalog\RepositoryItem;
use App\Models\ExternalResource;
use App\Services\Digital\DigitalMaterialWorkflow;
use App\Services\Repository\RepositoryWorkflow;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

class DigitalLibraryDomainTest extends TestCase
{
    public function test_digital_material_vocabulary_and_workflow_are_canonical(): void
    {
        $this->assertSame([
            'book_pdf', 'image_collection', 'presentation', 'scientific_work',
            'methodological_material', 'supplementary_file',
        ], ElectronicMaterial::MATERIAL_TYPES);
        $this->assertContains('rights_review', ElectronicMaterial::WORKFLOW_STATUSES);
        $this->assertNotContains('published', DigitalMaterialWorkflow::TRANSITIONS['uploaded']);
    }

    public function test_unknown_rights_block_publication(): void
    {
        $material = new ElectronicMaterial([
            'copyright_status' => 'unknown', 'rights_holder' => 'University',
            'source' => 'Archive', 'licence_type' => 'permission',
        ]);

        $this->assertFalse($material->rightsPermitPublication());
        $material->copyright_status = 'university_owned';
        $this->assertTrue($material->rightsPermitPublication());
    }

    public function test_published_scope_falls_back_to_active_for_the_legacy_schema(): void
    {
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
        ]);
        DB::purge('sqlite');
        DB::setDefaultConnection('sqlite');
        DB::reconnect('sqlite');

        Schema::create('electronic_materials', function (Blueprint $table): void {
            $table->id();
            $table->boolean('is_active')->default(true);
        });
        DB::table('electronic_materials')->insert([
            ['is_active' => true],
            ['is_active' => false],
        ]);

        $this->assertSame(1, ElectronicMaterial::query()->published()->count());
    }

    public function test_repository_supports_all_seven_work_types_and_review_chain(): void
    {
        $this->assertSame([
            'bachelor_thesis', 'master_thesis', 'phd_dissertation', 'scientific_article',
            'research_report', 'university_publication', 'thesis_abstract',
        ], RepositoryItem::WORK_TYPES);
        $this->assertSame('thesis_abstract', RepositoryItem::normaliseWorkType('abstract_of_thesis'));
        $this->assertContains('abstract_of_thesis', RepositoryItem::acceptedWorkTypes());
        $this->assertSame('metadata_only', RepositoryItem::normaliseAccessPolicy('metadata_public'));
        $this->assertSame('metadata_public_file_authenticated', RepositoryItem::normaliseAccessPolicy('authenticated'));
        $this->assertSame(['metadata_review'], RepositoryWorkflow::TRANSITIONS['draft']);
        $this->assertContains('author_verification', RepositoryWorkflow::TRANSITIONS['metadata_review']);
        $this->assertContains('rights_review', RepositoryWorkflow::TRANSITIONS['author_verification']);
        $this->assertContains('pending_approval', RepositoryWorkflow::TRANSITIONS['quality_review']);
    }

    public function test_repository_public_file_policy_is_explicit(): void
    {
        $item = new RepositoryItem(['status' => 'published', 'access_policy' => 'full_public', 'file_path' => 'repository/work.pdf', 'file_name' => 'work.pdf']);
        $this->assertTrue($item->canExposeFullText());
        $item->access_policy = 'metadata_only';
        $this->assertFalse($item->canExposeFullText());
    }

    public function test_repository_workflow_fields_are_mass_assignable_for_atomic_transitions(): void
    {
        $item = new RepositoryItem;
        $item->fill([
            'scheduled_for' => now()->addHour(),
            'withdrawn_at' => now(),
            'withdrawal_reason' => 'Superseded record',
            'withdrawn_by' => 42,
        ]);

        $this->assertNotNull($item->scheduled_for);
        $this->assertSame('Superseded record', $item->withdrawal_reason);
        $this->assertSame(42, $item->withdrawn_by);
    }

    public function test_repository_scheduling_is_a_distinct_approval_step(): void
    {
        $this->assertContains('scheduled', RepositoryWorkflow::TRANSITIONS['approved']);
        $this->assertSame(['published', 'embargoed', 'archived'], RepositoryWorkflow::TRANSITIONS['scheduled']);
    }

    public function test_external_resource_types_and_expired_access_are_enforced(): void
    {
        $this->assertSame(['licensed', 'open_access', 'partner', 'internal'], ExternalResource::TYPES);
        $resource = new ExternalResource([
            'is_active' => true, 'publication_status' => 'published', 'guest_access' => true,
            'contract_ends_at' => now()->subDay(),
        ]);
        $this->assertFalse($resource->canOpen(null));
    }

    public function test_optional_processing_features_are_not_falsely_enabled(): void
    {
        $this->assertFalse(config('digital_library.ocr.enabled'));
        $this->assertFalse(config('digital_library.presentation_preview.enabled'));
        $this->assertFalse(config('digital_library.author_self_submission'));
        $this->assertSame('private', basename(config('filesystems.disks.local.root')));
    }
}
