<?php

namespace Tests\Feature;

use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\DataQualityIssue;
use App\Models\User;
use App\Services\DataQuality\BulkCorrectionService;
use App\Services\DataQuality\DataQualityScanner;
use App\Services\DataQuality\DuplicateDetectionService;
use App\Services\DataQuality\EncodingInspector;
use App\Services\DataQuality\ImportStagingService;
use App\Services\DataQuality\RecordMergeService;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

class DataQualityControlCenterTest extends TestCase
{
    use RefreshDatabase;

    private User $actor;

    protected function setUp(): void
    {
        parent::setUp();
        $this->seed([PermissionSeeder::class, RoleSeeder::class]);
        $this->actor = User::factory()->create(['is_active' => true]);
        $this->actor->assignRole('senior_librarian');
    }

    public function test_scan_creates_stable_issues_resolves_and_reopens_them(): void
    {
        $record = BibliographicRecord::factory()->create([
            'title' => '  Сапа   сынағы  ',
            'publication_year' => now()->year + 5,
            'isbn' => '9780306406158',
            'udc_code' => null,
        ]);
        $scanner = app(DataQualityScanner::class);

        $first = $scanner->scanModel($record, 'bibliographic_record');
        $this->assertGreaterThanOrEqual(4, $first['issues_created']);
        $count = DataQualityIssue::query()->where('entity_id', (string) $record->id)->count();

        $scanner->scanModel($record, 'bibliographic_record');
        $this->assertSame($count, DataQualityIssue::query()->where('entity_id', (string) $record->id)->count());

        $spacing = DataQualityIssue::query()->where('entity_id', (string) $record->id)->where('rule_code', 'bib.title.spacing')->firstOrFail();
        $spacing->update(['assigned_to' => $this->actor->id]);
        $record->update(['title' => 'Сапа сынағы']);
        $scanner->scanModel($record->fresh(), 'bibliographic_record');
        $this->assertSame('resolved', $spacing->fresh()->status);

        $record->update(['title' => 'Сапа   сынағы']);
        $scanner->scanModel($record->fresh(), 'bibliographic_record');
        $this->assertSame('reopened', $spacing->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['action_type' => 'data_quality.issue_reopened', 'entity_id' => (string) $spacing->id]);
        $this->assertDatabaseHas('reader_notifications', [
            'user_id' => $this->actor->id,
            'event_type' => 'data_quality_issue_reopened',
        ]);
    }

    public function test_full_and_record_scans_share_the_same_issue_fingerprint(): void
    {
        $record = BibliographicRecord::factory()->create(['udc_code' => null]);
        $scanner = app(DataQualityScanner::class);

        $scanner->scanModel($record, 'bibliographic_record');
        $before = DataQualityIssue::query()
            ->where('entity_type', 'bibliographic_record')
            ->where('entity_id', (string) $record->id)
            ->count();

        $scanner->execute($scanner->start('bibliographic_records', $this->actor));

        $this->assertSame($before, DataQualityIssue::query()
            ->where('entity_type', 'bibliographic_record')
            ->where('entity_id', (string) $record->id)
            ->count());
        $this->assertDatabaseMissing('data_quality_issues', [
            'entity_type' => 'bibliographic_records',
            'entity_id' => (string) $record->id,
        ]);
    }

    public function test_isbn_checks_and_kazakh_encoding_preview_are_safe(): void
    {
        $record = BibliographicRecord::factory()->create([
            'title' => 'Ќазаќстан єдебиеті',
            'isbn' => '0-306-40615-2',
        ]);
        app(DataQualityScanner::class)->scanModel($record, 'bibliographic_record');

        $this->assertDatabaseHas('data_quality_issues', ['entity_id' => (string) $record->id, 'rule_code' => 'bib.isbn.not_normalized']);
        $encoding = DataQualityIssue::query()->where('entity_id', (string) $record->id)->where('rule_code', 'encoding.legacy_kazakh_glyph')->firstOrFail();
        $this->assertSame('Ќазаќстан єдебиеті', $record->fresh()->title);
        $this->assertNotEmpty($encoding->context['characters']);

        $inspection = app(EncodingInspector::class)->inspect('ќала', 'title');
        $this->assertSame('қала', collect($inspection)->firstWhere('code', 'encoding.legacy_kazakh_glyph')['suggestion']);
        $this->assertSame('Әліппе: қазақ тілі', mb_convert_encoding('Әліппе: қазақ тілі', 'UTF-8', 'UTF-8'));
    }

    public function test_duplicate_score_is_advisory_and_distinguishes_volumes(): void
    {
        $base = BibliographicRecord::factory()->create([
            'title' => 'Қазақстан тарихы',
            'primary_author' => 'А. Автор',
            'publication_year' => 2020,
            'publisher' => 'Ғылым',
            'language' => 'kk',
            'isbn' => '9780306406157',
        ]);
        $exact = BibliographicRecord::factory()->create($base->only([
            'title', 'primary_author', 'publication_year', 'publisher', 'language', 'isbn', 'udc_code', 'resource_type',
        ]));
        $volume = BibliographicRecord::factory()->create([
            ...$base->only(['primary_author', 'publication_year', 'publisher', 'language', 'isbn', 'udc_code', 'resource_type']),
            'title' => 'Қазақстан тарихы. Том 2',
        ]);

        $matches = app(DuplicateDetectionService::class)->candidates($base, $base->id);
        $this->assertSame('exact', $matches->first(fn (array $match) => $match['record']->is($exact))['level']);
        $this->assertNotSame('exact', $matches->first(fn (array $match) => $match['record']->is($volume))['level']);
        $this->assertSame('active', $exact->fresh()->merge_status);
    }

    public function test_approved_merge_moves_copies_and_keeps_a_source_tombstone(): void
    {
        $cataloguer = User::factory()->create(['is_active' => true]);
        $cataloguer->assignRole('cataloguer');
        $target = BibliographicRecord::factory()->create(['title' => 'Exact work', 'isbn' => '9780306406157']);
        $source = BibliographicRecord::factory()->create(['title' => 'Exact work', 'isbn' => '9780306406157']);
        $copy = BookCopy::factory()->create(['bibliographic_record_id' => $source->id]);
        $group = app(DuplicateDetectionService::class)->detectAndStore($target)->first();
        $merges = app(RecordMergeService::class);

        $operation = $merges->propose($group, $target, $source, ['title' => 'target'], 'Verified exact duplicate from the same source.', $cataloguer);
        $merges->approve($operation, $this->actor);
        $merges->execute($operation->fresh(), $this->actor);

        $this->assertSame($target->id, $copy->fresh()->bibliographic_record_id);
        $this->assertSoftDeleted('bibliographic_records', ['id' => $source->id]);
        $this->assertSame('merged', BibliographicRecord::withTrashed()->findOrFail($source->id)->merge_status);
        $this->assertSame($target->id, BibliographicRecord::withTrashed()->findOrFail($source->id)->merged_into_id);
        $this->assertSame('executed', $operation->fresh()->status);
        $this->assertFalse($merges->rollbackSafety($operation->fresh())['safe']);
    }

    public function test_merge_requires_group_membership_and_independent_approval(): void
    {
        $cataloguer = User::factory()->create(['is_active' => true]);
        $cataloguer->assignRole('cataloguer');
        $target = BibliographicRecord::factory()->create(['title' => 'Reviewed duplicate', 'isbn' => '9780306406157']);
        $source = BibliographicRecord::factory()->create(['title' => 'Reviewed duplicate', 'isbn' => '9780306406157']);
        $outsider = BibliographicRecord::factory()->create(['title' => 'Unrelated record', 'isbn' => null]);
        $group = app(DuplicateDetectionService::class)->detectAndStore($target)->firstOrFail();
        $merges = app(RecordMergeService::class);

        try {
            $merges->propose($group, $target, $outsider, [], 'This record was not part of the reviewed duplicate group.', $cataloguer);
            $this->fail('A record outside the duplicate group was accepted.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('duplicate group', $exception->getMessage());
        }

        $operation = $merges->propose($group, $target, $source, [], 'Both records were reviewed as exact duplicates.', $cataloguer);
        $this->expectException(\RuntimeException::class);
        $this->expectExceptionMessage('independent approval');
        $merges->approve($operation, $cataloguer);
    }

    public function test_bulk_workflow_previews_executes_and_rolls_back_only_safe_changes(): void
    {
        $approver = User::factory()->create(['is_active' => true]);
        $approver->assignRole('senior_librarian');
        $record = BibliographicRecord::factory()->create(['title' => '  Safe   title  ']);
        $bulk = app(BulkCorrectionService::class);

        $batch = $bulk->preview('bibliographic_record', [$record->id], 'normalize_spaces', ['field' => 'title'], 'Normalize unambiguous whitespace.', $this->actor);
        $this->assertSame('  Safe   title  ', $record->fresh()->title);
        $this->assertSame('Safe title', $batch->items->first()->after_snapshot['title']);
        $this->assertDatabaseHas('reader_notifications', [
            'user_id' => $this->actor->id,
            'event_type' => 'data_quality_bulk_approval_required',
        ]);

        $bulk->approve($batch, $approver);
        $bulk->execute($batch->fresh(), $this->actor);
        $this->assertSame('Safe title', $record->fresh()->title);

        $bulk->rollback($batch->fresh(), $this->actor);
        $this->assertSame('  Safe   title  ', $record->fresh()->title);
        $this->assertSame('rolled_back', $batch->fresh()->status);
    }

    public function test_staging_is_idempotent_and_does_not_touch_catalog_before_approval(): void
    {
        $importer = User::factory()->create(['is_active' => true]);
        $importer->assignRole('admin');
        $csv = "title,primary_author,publisher,publication_year,language,udc_code,isbn,resource_type,annotation\n".
            "Staged unique title,Author,Publisher,2020,en,004,9780306406157,book,Annotation\n";
        $file = UploadedFile::fake()->createWithContent('catalog.csv', $csv);
        $imports = app(ImportStagingService::class);
        $before = BibliographicRecord::query()->count();

        $batch = $imports->upload($file, 'csv', null, $importer, 'UTF-8');
        $this->assertSame($before, BibliographicRecord::query()->count());
        $this->assertSame('ready', $batch->rows->first()->status);

        $imports->approve($batch, $this->actor);
        $imports->import($batch->fresh(), $this->actor);
        $this->assertDatabaseHas('bibliographic_records', ['title' => 'Staged unique title']);
        $this->assertSame(0, $batch->fresh()->reconciliation['difference']);

        $this->expectException(\RuntimeException::class);
        $imports->upload(UploadedFile::fake()->createWithContent('again.csv', $csv), 'csv', null, $this->actor, 'UTF-8');
    }

    public function test_permissions_separate_operational_decisions(): void
    {
        $librarian = User::factory()->create(['is_active' => true]);
        $librarian->assignRole('librarian');
        $cataloguer = User::factory()->create(['is_active' => true]);
        $cataloguer->assignRole('cataloguer');
        $director = User::factory()->create(['is_active' => true]);
        $director->assignRole('director');
        $admin = User::factory()->create(['is_active' => true]);
        $admin->assignRole('admin');

        $this->assertTrue($librarian->can('data_quality.correct'));
        $this->assertFalse($librarian->can('data_quality.merge'));
        $this->assertTrue($cataloguer->can('data_quality.merge'));
        $this->assertFalse($cataloguer->can('data_quality.approve_merge'));
        $this->assertTrue($this->actor->can('data_quality.approve_merge'));
        $this->assertTrue($director->can('data_quality.view_reports'));
        $this->assertFalse($director->can('data_quality.correct'));
        $this->assertTrue($admin->can('data_quality.import'));
        $this->assertFalse($admin->can('data_quality.approve_merge'));
    }

    public function test_queue_and_csv_are_permission_protected_and_translated(): void
    {
        foreach (['ru', 'kk', 'en'] as $locale) {
            $this->assertNotSame('data_quality.title', trans('data_quality.title', [], $locale));
            $this->assertNotSame('settings.data_quality.title', trans('settings.data_quality.title', [], $locale));
        }

        $record = BibliographicRecord::factory()->create(['udc_code' => null]);
        app(DataQualityScanner::class)->scanModel($record, 'bibliographic_record');
        $response = $this->signInAs($this->actor)->get(route('librarian.data-quality.index'));
        $response->assertOk()->assertSee('DQI-');
        $csv = $this->signInAs($this->actor)->get(route('librarian.data-quality.export'));
        $csv->assertOk()->assertDownload();
        $this->assertStringNotContainsString("\n=", $csv->streamedContent());

        $member = User::factory()->create(['is_active' => true]);
        $member->assignRole('member');
        $this->signInAs($member)->get(route('librarian.data-quality.index'))->assertForbidden();
    }

    private function signInAs(User $user): static
    {
        $role = (string) $user->getRoleNames()->first();
        $this->actingAs($user)->withSession([
            'library.user' => [
                'id' => (string) $user->id,
                'local_id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $role === 'member' ? 'reader' : 'librarian',
                'canonical_role' => $role,
            ],
        ]);

        return $this;
    }
}
