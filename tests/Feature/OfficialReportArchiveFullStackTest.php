<?php

namespace Tests\Feature;

use App\Jobs\GenerateOfficialReportExport;
use App\Models\Branch;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Fund;
use App\Models\OfficialReportSnapshot;
use App\Models\ReportExportJob;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Catalog\LibraryNotificationService;
use App\Services\Reports\OfficialReportRenderer;
use App\Services\Reports\OfficialReportSnapshotService;
use App\Services\Reports\ReportRegistry;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;
use ZipArchive;

class OfficialReportArchiveFullStackTest extends TestCase
{
    use BuildsAdminControlPlane;

    private User $librarian;

    private User $director;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        foreach ([
            'database/migrations/2026_08_12_200000_create_official_report_archive.php',
            'database/migrations/2026_08_12_210000_extend_official_report_governance.php',
            'database/migrations/2026_08_12_230000_harden_official_report_archive.php',
        ] as $path) {
            (require base_path($path))->up();
        }
        Storage::fake('local');
        $this->librarian = $this->makeControlPlaneUser('librarian');
        $this->director = $this->makeControlPlaneUser('director');
    }

    public function test_registry_and_archive_screen_expose_four_explicit_official_datasets(): void
    {
        $registry = app(ReportRegistry::class);

        $this->assertSame(['acquisitions', 'fund-usage', 'users', 'electronic-resources'], $registry->officialCodes());
        $this->assertContains('audit-summary', $registry->codes());
        $this->assertSame('repository.items+repository_usage_daily', $registry->get('repository')->dataset);
        foreach ($registry->officialDefinitions() as $definition) {
            $this->assertTrue($definition->official);
            $this->assertNotSame('', $definition->dataset);
            $this->assertSame(['csv', 'pdf', 'xlsx', 'docx'], $definition->exports);
            $this->assertContains('preset', $definition->filters);
            $expectedPermission = $definition->code === 'acquisitions'
                ? 'reports.view_acquisitions|reports.view_ops|reports.view_full'
                : 'reports.view_ops|reports.view_full';
            $this->assertSame($expectedPermission, $definition->permission);
        }

        $this->signInToLibraryAs($this->librarian)
            ->get(route('librarian.reports.official.index'))
            ->assertOk()
            ->assertSee(__('official_reports.title'))
            ->assertSee('name="report"', false)
            ->assertSee('name="preset"', false)
            ->assertSee('name="access_type"', false);
    }

    public function test_snapshot_workflow_is_separated_immutable_revisioned_and_protected(): void
    {
        $snapshot = $this->capture();

        $this->assertSame('generated', $snapshot->status);
        $this->assertMatchesRegularExpression('/^ACQ-\d{4}-[A-F0-9]{8}-R001$/', $snapshot->report_number);
        $this->assertSame($snapshot->report_number, data_get($snapshot->source_data, 'report_number'));
        $this->assertTrue($snapshot->sourceIsIntact());
        $this->assertTrue($snapshot->retention_until->isFuture());
        Storage::disk('local')->assertExists($snapshot->archive_path);
        $this->assertSame($snapshot->source_hash, hash('sha256', Storage::disk('local')->get($snapshot->archive_path)));

        $this->signInToLibraryAs($this->librarian)
            ->post(route('librarian.reports.official.submit', $snapshot))
            ->assertRedirect();
        $snapshot->refresh();
        $this->assertSame('pending_review', $snapshot->status);

        $this->signInToLibraryAs($this->librarian)
            ->post(route('librarian.reports.official.approve', $snapshot))
            ->assertForbidden();
        $this->signInToLibraryAs($this->director)
            ->post(route('librarian.reports.official.approve', $snapshot), ['decision_note' => 'Approved independently'])
            ->assertRedirect();
        $snapshot->refresh();
        $this->assertSame('approved', $snapshot->status);
        $this->assertSame($this->director->getKey(), $snapshot->approved_by);

        try {
            $snapshot->update(['decision_note' => 'tampered']);
            $this->fail('Approved reports must reject updates.');
        } catch (DomainException) {
            $this->assertTrue(true);
        }
        try {
            $snapshot->delete();
            $this->fail('Approved reports must reject deletion.');
        } catch (DomainException) {
            $this->assertTrue(true);
        }

        $source = $this->signInToLibraryAs($this->director)
            ->get(route('librarian.reports.official.source', $snapshot));
        $source->assertOk();
        $this->assertStringContainsString('private', (string) $source->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $source->headers->get('Cache-Control'));
        $this->assertStringContainsString($snapshot->report_number, $source->streamedContent());

        $outsider = $this->makeControlPlaneUser('member');
        $this->signInToLibraryAs($outsider)
            ->get(route('librarian.reports.official.show', $snapshot))
            ->assertForbidden();
        $this->signInToLibraryAs($outsider)
            ->get(route('librarian.reports.official.source', $snapshot))
            ->assertForbidden();

        $this->signInToLibraryAs($this->librarian)
            ->post(route('librarian.reports.official.revisions.store', $snapshot), ['revision_note' => 'Corrected official revision'])
            ->assertRedirect();
        $revision = OfficialReportSnapshot::query()->where('lineage_id', $snapshot->lineage_id)->where('revision', 2)->firstOrFail();
        $this->assertSame($snapshot->getKey(), $revision->previous_snapshot_id);
        $this->assertStringEndsWith('-R002', $revision->report_number);
        $this->assertNotSame($snapshot->source_hash, $revision->source_hash);

        $this->signInToLibraryAs($this->librarian)->post(route('librarian.reports.official.submit', $revision))->assertRedirect();
        $this->signInToLibraryAs($this->director)->post(route('librarian.reports.official.approve', $revision))->assertRedirect();
        $this->assertSame('superseded', $snapshot->refresh()->status);
        $this->assertSame($revision->getKey(), $snapshot->superseded_by_snapshot_id);
        $this->assertSame('approved', $revision->refresh()->status);

        $this->signInToLibraryAs($this->director)
            ->post(route('librarian.reports.official.archive', $snapshot))
            ->assertRedirect();
        $this->assertSame('archived', $snapshot->refresh()->status);
        $this->assertSame($this->director->getKey(), $snapshot->archived_by);
    }

    public function test_export_jobs_are_idempotent_queued_private_and_notify_the_requester(): void
    {
        Queue::fake();
        $snapshot = $this->approve($this->capture());
        $parameters = ['format' => 'csv', 'idempotency_key' => 'same-client-operation-2026'];

        $first = $this->signInToLibraryAs($this->librarian)
            ->postJson(route('librarian.reports.official.exports.store', $snapshot), $parameters)
            ->assertAccepted()
            ->assertJsonPath('status', 'queued');
        $second = $this->signInToLibraryAs($this->librarian)
            ->postJson(route('librarian.reports.official.exports.store', $snapshot), $parameters)
            ->assertAccepted();
        $this->assertSame($first->json('id'), $second->json('id'));
        $this->assertSame(1, ReportExportJob::query()->count());
        Queue::assertPushed(GenerateOfficialReportExport::class, fn (GenerateOfficialReportExport $job): bool => $job->queue === 'reports');

        $export = ReportExportJob::query()->firstOrFail();
        (new GenerateOfficialReportExport($export->getKey()))->handle(
            app(OfficialReportRenderer::class),
            app(AuditLogger::class),
            app(LibraryNotificationService::class),
        );
        $export->refresh();
        $this->assertSame('ready', $export->status);
        $this->assertSame(100, $export->progress);
        $this->assertTrue($export->retention_until->isFuture());
        Storage::disk('local')->assertExists($export->file_path);
        $this->assertSame($export->file_hash, hash('sha256', Storage::disk('local')->get($export->file_path)));
        $this->assertDatabaseHas('reader_notifications', [
            'user_id' => $this->librarian->getKey(),
            'event_type' => 'report_export_ready',
        ]);

        $this->signInToLibraryAs($this->librarian)
            ->getJson(route('librarian.reports.official.exports.status', $export))
            ->assertOk()->assertJsonPath('status', 'ready')->assertJsonPath('progress', 100);
        $download = $this->signInToLibraryAs($this->librarian)
            ->get(route('librarian.reports.official.exports.download', $export));
        $download->assertOk();
        $this->assertStringContainsString('private', (string) $download->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $download->headers->get('Cache-Control'));
        $this->assertSame(Storage::disk('local')->get($export->file_path), $download->streamedContent());

        $outsider = $this->makeControlPlaneUser('member');
        $this->signInToLibraryAs($outsider)
            ->getJson(route('librarian.reports.official.exports.status', $export))
            ->assertForbidden();
        $this->signInToLibraryAs($outsider)
            ->get(route('librarian.reports.official.exports.download', $export))
            ->assertForbidden();

        $failed = ReportExportJob::query()->create([
            'public_id' => (string) str()->uuid(),
            'snapshot_id' => $snapshot->getKey(),
            'requested_by' => $this->librarian->getKey(),
            'format' => 'invalid',
            'status' => 'queued',
            'idempotency_key' => hash('sha256', 'forced-failure'),
        ]);
        try {
            (new GenerateOfficialReportExport($failed->getKey()))->handle(
                app(OfficialReportRenderer::class),
                app(AuditLogger::class),
                app(LibraryNotificationService::class),
            );
            $this->fail('The forced bad format must fail.');
        } catch (RuntimeException) {
            $this->assertSame('failed', $failed->refresh()->status);
        }
        $this->assertDatabaseHas('reader_notifications', [
            'user_id' => $this->librarian->getKey(),
            'event_type' => 'report_export_failed',
        ]);
    }

    public function test_snapshot_exports_are_real_files_and_never_requery_changed_live_data(): void
    {
        $copy = $this->acquisitionFixture();
        $snapshot = $this->approve($this->capture());
        $copy->update(['price' => 9999.99]);
        $renderer = app(OfficialReportRenderer::class);

        foreach (['csv', 'pdf', 'xlsx', 'docx'] as $format) {
            $rendered = $renderer->render($snapshot, $format);
            $this->assertFileExists($rendered['path']);
            $this->assertSame(hash_file('sha256', $rendered['path']), $rendered['hash']);

            if ($format === 'csv') {
                $contents = file_get_contents($rendered['path']);
                $this->assertStringContainsString($snapshot->report_number, $contents);
                $this->assertStringContainsString('1234.5', $contents);
                $this->assertStringNotContainsString('9999.99', $contents);
                $handle = fopen($rendered['path'], 'rb');
                $this->assertIsResource($handle);
                $parsedRows = [];
                while (($parsed = fgetcsv($handle)) !== false) {
                    $parsedRows[] = $parsed;
                }
                fclose($handle);
                $this->assertGreaterThan(5, count($parsedRows));
            } elseif ($format === 'pdf') {
                $pdf = (string) file_get_contents($rendered['path']);
                $this->assertStringStartsWith('%PDF', $pdf);
                $this->assertStringContainsString('%%EOF', $pdf);
                $this->assertMatchesRegularExpression('/\/Type\s*\/Page\b/', $pdf);
            } else {
                $zip = new ZipArchive;
                $this->assertTrue($zip->open($rendered['path']) === true);
                $entry = $format === 'xlsx' ? 'xl/worksheets/sheet1.xml' : 'word/document.xml';
                $this->assertNotFalse($zip->locateName('[Content_Types].xml'));
                $this->assertNotFalse($zip->locateName($entry));
                $contentTypes = new \DOMDocument;
                $this->assertTrue($contentTypes->loadXML((string) $zip->getFromName('[Content_Types].xml')));
                $document = new \DOMDocument;
                $this->assertTrue($document->loadXML((string) $zip->getFromName($entry)));
                if ($format === 'docx') {
                    $this->assertStringContainsString($snapshot->report_number, (string) $zip->getFromName($entry));
                    $this->assertStringContainsString($this->director->name, (string) $zip->getFromName($entry));
                }
                $zip->close();
            }
            @unlink($rendered['path']);
        }
    }

    public function test_kazakh_official_exports_preserve_unicode_in_real_document_packages(): void
    {
        $snapshot = $this->approve($this->capture('kk'));
        app()->setLocale('kk');
        $renderer = app(OfficialReportRenderer::class);
        $expectedTitle = __('analytics.reports.acquisitions.title');

        foreach (['csv', 'pdf', 'xlsx', 'docx'] as $format) {
            $rendered = $renderer->render($snapshot, $format);
            if ($format === 'csv') {
                $contents = (string) file_get_contents($rendered['path']);
                $this->assertStringContainsString(__('analytics.columns.received_date'), $contents);
            } elseif ($format === 'pdf') {
                $contents = (string) file_get_contents($rendered['path']);
                $this->assertStringStartsWith('%PDF', $contents);
                $this->assertStringContainsString('%%EOF', $contents);
            } else {
                $zip = new ZipArchive;
                $this->assertTrue($zip->open($rendered['path']) === true);
                $entry = $format === 'xlsx' ? 'xl/workbook.xml' : 'word/document.xml';
                $xml = (string) $zip->getFromName($entry);
                $this->assertStringContainsString($expectedTitle, $xml);
                $document = new \DOMDocument;
                $this->assertTrue($document->loadXML($xml));
                $zip->close();
            }
            @unlink($rendered['path']);
        }
        app()->setLocale('ru');
    }

    public function test_database_guards_archive_hash_and_retention_are_enforced(): void
    {
        Queue::fake();
        $snapshot = $this->approve($this->capture());

        try {
            DB::table('official_report_snapshots')->where('id', $snapshot->getKey())->update([
                'source_hash' => str_repeat('0', 64),
            ]);
            $this->fail('The database trigger must reject direct source mutation.');
        } catch (QueryException) {
            $this->assertTrue($snapshot->fresh()->sourceIsIntact());
        }
        try {
            DB::table('official_report_snapshots')->where('id', $snapshot->getKey())->update([
                'decision_note' => 'query-builder tamper',
            ]);
            $this->fail('Locked workflow metadata must be immutable at database level.');
        } catch (QueryException) {
            $this->assertNotSame('query-builder tamper', $snapshot->fresh()->decision_note);
        }

        $archive = Storage::disk('local')->get($snapshot->archive_path);
        Storage::disk('local')->put($snapshot->archive_path, '{"tampered":true}');
        $this->expectException(RuntimeException::class);
        try {
            app(OfficialReportSnapshotService::class)->assertIntegrity($snapshot->fresh());
        } finally {
            Storage::disk('local')->put($snapshot->archive_path, $archive);
        }
    }

    public function test_stale_queue_leases_recover_and_expired_private_files_return_gone(): void
    {
        Queue::fake();
        $snapshot = $this->approve($this->capture());
        Storage::disk('local')->put('official-reports/exports/stale.csv', 'stale');
        $stale = ReportExportJob::query()->create([
            'public_id' => (string) str()->uuid(),
            'snapshot_id' => $snapshot->getKey(),
            'requested_by' => $this->librarian->getKey(),
            'format' => 'csv',
            'status' => 'generating',
            'progress' => 75,
            'idempotency_key' => hash('sha256', 'stale-export'),
            'active_key' => hash('sha256', 'stale-active'),
            'lease_token' => (string) str()->uuid(),
            'lease_expires_at' => now('UTC')->subMinute(),
            'file_disk' => 'local',
            'file_path' => 'official-reports/exports/stale.csv',
        ]);

        $this->artisan('library:reports-sweep')->assertSuccessful();
        $this->assertSame('queued', $stale->refresh()->status);
        $this->assertNull($stale->lease_token);
        Storage::disk('local')->assertMissing('official-reports/exports/stale.csv');
        Queue::assertPushed(GenerateOfficialReportExport::class, fn (GenerateOfficialReportExport $job): bool => $job->exportId === $stale->getKey());

        Storage::disk('local')->put('official-reports/exports/expired.csv', 'expired');
        $expired = ReportExportJob::query()->create([
            'public_id' => (string) str()->uuid(),
            'snapshot_id' => $snapshot->getKey(),
            'requested_by' => $this->librarian->getKey(),
            'format' => 'csv',
            'status' => 'ready',
            'progress' => 100,
            'idempotency_key' => hash('sha256', 'expired-export'),
            'file_disk' => 'local',
            'file_path' => 'official-reports/exports/expired.csv',
            'file_name' => 'expired.csv',
            'mime_type' => 'text/csv',
            'file_size' => 7,
            'file_hash' => hash('sha256', 'expired'),
            'retention_until' => now('UTC')->subSecond(),
        ]);

        $this->signInToLibraryAs($this->librarian)
            ->get(route('librarian.reports.official.exports.download', $expired))
            ->assertGone();
        $this->artisan('library:reports-sweep')->assertSuccessful();
        $this->assertNotNull($expired->fresh()->file_deleted_at);
        Storage::disk('local')->assertMissing('official-reports/exports/expired.csv');
    }

    public function test_retention_sweep_never_marks_a_file_deleted_when_storage_rejects_deletion(): void
    {
        Queue::fake();
        $snapshot = $this->approve($this->capture());
        $export = ReportExportJob::query()->create([
            'public_id' => (string) str()->uuid(),
            'snapshot_id' => $snapshot->getKey(),
            'requested_by' => $this->librarian->getKey(),
            'format' => 'csv',
            'status' => 'ready',
            'progress' => 100,
            'idempotency_key' => hash('sha256', 'retention-storage-failure'),
            'active_key' => hash('sha256', 'retention-storage-failure-active'),
            'file_disk' => 'local',
            'file_path' => 'official-reports/exports/cannot-delete.csv',
            'file_name' => 'cannot-delete.csv',
            'mime_type' => 'text/csv',
            'file_size' => 13,
            'file_hash' => hash('sha256', 'cannot-delete'),
            'retention_until' => now('UTC')->subSecond(),
        ]);
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('delete')
            ->once()
            ->with('official-reports/exports/cannot-delete.csv')
            ->andReturnFalse();
        Storage::shouldReceive('disk')->once()->with('local')->andReturn($disk);

        $this->artisan('library:reports-sweep')->assertSuccessful();

        $export->refresh();
        $this->assertNull($export->file_deleted_at);
        $this->assertNotNull($export->active_key);
        $this->assertDatabaseHas('activity_logs', [
            'action_type' => 'official_report.export_retention_failed',
            'entity_type' => 'official_report_export',
            'entity_id' => $export->public_id,
        ]);
    }

    public function test_stale_lease_recovery_stays_fail_closed_when_storage_rejects_cleanup(): void
    {
        Queue::fake();
        $snapshot = $this->approve($this->capture());
        $leaseToken = (string) str()->uuid();
        $export = ReportExportJob::query()->create([
            'public_id' => (string) str()->uuid(),
            'snapshot_id' => $snapshot->getKey(),
            'requested_by' => $this->librarian->getKey(),
            'format' => 'csv',
            'status' => 'generating',
            'progress' => 75,
            'idempotency_key' => hash('sha256', 'recovery-storage-failure'),
            'active_key' => hash('sha256', 'recovery-storage-failure-active'),
            'lease_token' => $leaseToken,
            'lease_expires_at' => now('UTC')->subMinute(),
            'file_disk' => 'local',
            'file_path' => 'official-reports/exports/cannot-clean.csv',
        ]);
        $disk = \Mockery::mock(FilesystemAdapter::class);
        $disk->shouldReceive('exists')
            ->once()
            ->with('official-reports/exports/cannot-clean.csv')
            ->andReturnTrue();
        $disk->shouldReceive('delete')
            ->once()
            ->with('official-reports/exports/cannot-clean.csv')
            ->andReturnFalse();
        Storage::shouldReceive('disk')->once()->with('local')->andReturn($disk);

        $this->artisan('library:reports-sweep')->assertSuccessful();

        $export->refresh();
        $this->assertSame('generating', $export->status);
        $this->assertSame($leaseToken, $export->lease_token);
        $this->assertDatabaseHas('activity_logs', [
            'action_type' => 'official_report.export_recovery_failed',
            'entity_type' => 'official_report_export',
            'entity_id' => $export->public_id,
        ]);
    }

    public function test_status_api_never_discloses_internal_export_errors(): void
    {
        $snapshot = $this->approve($this->capture());
        $export = ReportExportJob::query()->create([
            'public_id' => (string) str()->uuid(),
            'snapshot_id' => $snapshot->getKey(),
            'requested_by' => $this->librarian->getKey(),
            'format' => 'csv',
            'status' => 'failed',
            'idempotency_key' => hash('sha256', 'secret-failure'),
            'error_message' => 'SQLSTATE password=/srv/private/report-secret',
            'public_error_code' => 'REPORT_INTERNAL_ERROR',
        ]);

        $response = $this->signInToLibraryAs($this->librarian)
            ->getJson(route('librarian.reports.official.exports.status', $export))
            ->assertOk()
            ->assertJsonPath('error.code', 'REPORT_INTERNAL_ERROR');
        $this->assertStringNotContainsString('SQLSTATE', $response->getContent());
        $this->assertStringNotContainsString('/srv/private', $response->getContent());
    }

    public function test_draft_delete_never_removes_database_row_when_archive_is_unavailable(): void
    {
        $snapshot = $this->capture();
        Storage::disk('local')->delete($snapshot->archive_path);

        try {
            app(OfficialReportSnapshotService::class)
                ->deleteDraft($snapshot, $this->librarian);
            $this->fail('A missing canonical archive must stop draft deletion.');
        } catch (RuntimeException) {
            $this->assertDatabaseHas('official_report_snapshots', ['id' => $snapshot->getKey()]);
        }
    }

    private function capture(string $locale = 'ru'): OfficialReportSnapshot
    {
        $this->librarian->update(['locale' => $locale]);
        $this->signInToLibraryAs($this->librarian)
            ->withSession(['locale' => $locale])
            ->post(route('librarian.reports.official.store'), [
                'report' => 'acquisitions',
                'preset' => 'custom',
                'from' => now(config('app.library_timezone', 'Asia/Almaty'))->subDay()->toDateString(),
                'to' => now(config('app.library_timezone', 'Asia/Almaty'))->addDay()->toDateString(),
                'revision_note' => 'Official fixture',
            ])
            ->assertRedirect();

        return OfficialReportSnapshot::query()->latest('id')->firstOrFail();
    }

    private function approve(OfficialReportSnapshot $snapshot): OfficialReportSnapshot
    {
        $this->signInToLibraryAs($this->librarian)
            ->post(route('librarian.reports.official.submit', $snapshot))
            ->assertRedirect();
        $this->signInToLibraryAs($this->director)
            ->post(route('librarian.reports.official.approve', $snapshot), ['decision_note' => 'Approved'])
            ->assertRedirect();

        return $snapshot->refresh();
    }

    private function acquisitionFixture(): BookCopy
    {
        $record = BibliographicRecord::factory()->create([
            'title' => 'Immutable source fixture',
            'resource_type' => 'book',
            'language' => 'ru',
            'udc_code' => '004',
        ]);

        return BookCopy::factory()->create([
            'bibliographic_record_id' => $record->getKey(),
            'branch_id' => Branch::query()->value('id'),
            'fund_id' => Fund::query()->value('id'),
            'price' => 1234.50,
            'acquisition_source' => 'purchase',
            'registration_date' => now(config('app.library_timezone', 'Asia/Almaty'))->toDateString(),
            'status' => 'available',
        ]);
    }
}
