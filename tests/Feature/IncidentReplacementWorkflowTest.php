<?php

namespace Tests\Feature;

use App\Exceptions\CirculationException;
use App\Models\Branch;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\CirculationIncidentCase;
use App\Models\Catalog\Fine;
use App\Models\Catalog\ReaderProfile;
use App\Models\Catalog\ReplacementCandidate;
use App\Models\Setting;
use App\Models\User;
use App\Services\Catalog\CirculationService;
use App\Services\Catalog\IncidentCaseService;
use Illuminate\Database\QueryException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class IncidentReplacementWorkflowTest extends TestCase
{
    use BuildsAdminControlPlane;

    private CirculationService $circulation;

    private IncidentCaseService $incidents;

    private User $reader;

    private BookCopy $copy;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        $this->circulation = app(CirculationService::class);
        $this->incidents = app(IncidentCaseService::class);
        $this->reader = $this->makeControlPlaneUser('member');
        ReaderProfile::forUser($this->reader);
        $this->copy = BookCopy::factory()->create(['status' => 'available']);
    }

    public function test_lost_return_opens_case_links_single_fine_and_keeps_copy_lost(): void
    {
        $case = $this->openLost(4200);
        $this->assertSame('lost', $this->copy->fresh()->status);
        $this->assertSame('awaiting_reader', $case->status);
        $this->assertNotNull($case->fine_id);
        $this->assertSame(1, Fine::query()->where('loan_id', $case->loan_id)->where('reason', 'lost')->count());
        $this->assertDatabaseHas('activity_logs', ['action_type' => 'incident.opened', 'entity_id' => (string) $case->id]);
        $this->assertDatabaseHas('reader_notifications', ['user_id' => $this->reader->id, 'event_type' => 'incident_opened']);
    }

    public function test_damaged_return_records_severity_and_preliminary_status(): void
    {
        $this->circulation->issue($this->reader, $this->copy, $this->adminUser);
        $this->circulation->returnCopy($this->copy, $this->adminUser, 'damaged', 'damaged', 1500, 'Water', [
            'damage_severity' => 'severe', 'damage_description' => 'Water damage',
            'preliminary_action' => 'write_off', 'open_replacement_case' => true,
        ]);
        $case = CirculationIncidentCase::query()->firstOrFail();
        $this->assertSame('severe', $case->damage_severity);
        $this->assertSame('written_off', $this->copy->fresh()->status);
        $this->assertSame(1, Fine::query()->where('reason', 'damaged')->count());
    }

    public function test_exact_candidate_is_reviewed_strongly_but_never_auto_approved(): void
    {
        $case = $this->openLost();
        $candidate = $this->proposeExact($case);
        $reviewed = $this->incidents->review($candidate, $this->adminUser, $this->allCriteria(true), 'Exact edition');
        $this->assertTrue($reviewed->isbn_matches);
        $this->assertGreaterThanOrEqual(80, $reviewed->match_score);
        $this->assertSame('under_review', $reviewed->status);
        $this->assertSame('under_review', $case->fresh()->status);
    }

    public function test_different_author_or_work_blocks_approval_without_exception(): void
    {
        $case = $this->openLost();
        $candidate = $this->proposeExact($case);
        $criteria = $this->allCriteria(true);
        $criteria['author_matches_or_approved'] = false;
        $criteria['work_matches'] = false;
        $candidate = $this->incidents->review($candidate, $this->adminUser, $criteria, 'Mismatch');
        $this->expectException(ValidationException::class);
        $this->incidents->decide($candidate, $this->adminUser, 'approve', 'Not acceptable');
    }

    public function test_damaged_candidate_is_rejected_and_case_remains_open(): void
    {
        $case = $this->openLost();
        $candidate = $this->proposeExact($case, ['copy_condition' => 'damaged']);
        $criteria = $this->allCriteria(true);
        $criteria['usable_condition'] = false;
        $criteria['no_serious_damage'] = false;
        $candidate = $this->incidents->review($candidate, $this->adminUser, $criteria, 'Damaged candidate');
        $case = $this->incidents->decide($candidate, $this->adminUser, 'reject', 'Physical damage');
        $this->assertSame('awaiting_reader', $case->status);
        $this->assertSame('rejected', $candidate->fresh()->status);
    }

    public function test_year_tolerance_comes_from_settings(): void
    {
        Setting::query()->updateOrCreate(['key' => 'replacement_year_tolerance'], ['value' => 2, 'type' => 'integer', 'group' => 'incidents']);
        $case = $this->openLost();
        $candidate = $this->proposeExact($case, ['publication_year' => $this->copy->bibliographicRecord->publication_year + 3]);
        $candidate = $this->incidents->review($candidate, $this->adminUser, $this->allCriteria(true));
        $this->assertSame(3, $candidate->year_difference);
        $this->assertFalse($candidate->year_within_tolerance);
    }

    public function test_approved_replacement_creates_distinct_copy_and_resolves_case(): void
    {
        [$case, $candidate] = $this->reviewedExact();
        $case = $this->incidents->decide($candidate, $this->adminUser, 'approve', 'Exact replacement', false, [], 'fine_and_replacement', true);
        $branch = Branch::query()->create(['code' => 'TST', 'name' => 'Test branch', 'type' => 'library', 'is_active' => true]);
        $new = $this->incidents->registerReplacement($case, $this->adminUser, [
            'inventory_number' => 'REPL-0001', 'barcode' => 'REPLBC0001',
            'branch_id' => $branch->id, 'storage_sigla' => 'TST', 'condition' => 'new',
            'registration_date' => today(), 'bibliographic_record_id' => $candidate->bibliographic_record_id,
        ]);
        $this->assertNotSame($this->copy->id, $new->id);
        $this->assertSame('reader_replacement', $new->acquisition_source);
        $this->assertSame('lost', $this->copy->fresh()->status);
        $this->assertSame('resolved', $case->fresh()->status);
        $this->assertSame('pending', $case->fine?->fresh()->status);
        $this->assertDatabaseHas('copy_history', ['copy_id' => $new->id, 'event_type' => 'replacement_registered']);
    }

    public function test_replacement_inventory_and_barcode_are_unique(): void
    {
        BookCopy::factory()->create(['inventory_number' => 'DUP-I', 'barcode' => 'DUP-B']);
        $this->expectException(QueryException::class);
        BookCopy::factory()->create(['inventory_number' => 'DUP-I', 'barcode' => 'OTHER-B']);
    }

    public function test_candidate_and_copy_cannot_be_approved_or_registered_twice(): void
    {
        [$case, $candidate] = $this->reviewedExact();
        $case = $this->incidents->decide($candidate, $this->adminUser, 'approve', 'Approved', false, [], 'replacement', false);
        try {
            $this->incidents->decide($candidate, $this->adminUser, 'approve', 'Again');
            $this->fail('Second approval must fail');
        } catch (CirculationException) {
            $this->assertTrue(true);
        }
        $branch = Branch::query()->create(['code' => 'T2', 'name' => 'B2', 'type' => 'library', 'is_active' => true]);
        $data = ['inventory_number' => 'R2', 'barcode' => 'RB2', 'branch_id' => $branch->id, 'storage_sigla' => 'B2', 'condition' => 'new', 'registration_date' => today(), 'bibliographic_record_id' => $candidate->bibliographic_record_id];
        $this->incidents->registerReplacement($case, $this->adminUser, $data);
        $this->expectException(HttpException::class);
        $this->incidents->registerReplacement($case, $this->adminUser, [...$data, 'inventory_number' => 'R3', 'barcode' => 'RB3']);
    }

    public function test_open_case_blocks_new_loans_even_when_profile_is_active(): void
    {
        $this->openLost();
        $this->assertSame('active', $this->reader->readerProfile->fresh()->status);
        $this->expectException(CirculationException::class);
        $this->circulation->issue($this->reader, BookCopy::factory()->create(), $this->adminUser);
    }

    public function test_reader_remains_blocked_when_replacement_is_resolved_but_fine_remains(): void
    {
        [$case, $candidate] = $this->reviewedExact();
        $case = $this->incidents->decide($candidate, $this->adminUser, 'approve', 'Keep damage fine', false, [], 'fine_and_replacement', true);
        $branch = Branch::query()->create(['code' => 'T3', 'name' => 'B3', 'type' => 'library', 'is_active' => true]);
        $this->incidents->registerReplacement($case, $this->adminUser, [
            'inventory_number' => 'R-FINE', 'barcode' => 'RB-FINE', 'branch_id' => $branch->id,
            'storage_sigla' => 'B3', 'condition' => 'new', 'registration_date' => today(),
            'bibliographic_record_id' => $candidate->bibliographic_record_id,
        ]);
        $this->expectException(CirculationException::class);
        $this->circulation->issue($this->reader, BookCopy::factory()->create(), $this->adminUser);
    }

    public function test_authorized_non_replacement_resolution_and_cancellation_are_audited(): void
    {
        $case = $this->openLost();
        $resolved = $this->incidents->resolveWithoutReplacement($case, $this->adminUser, 'write_off', 'Irrecoverable loss', true);
        $this->assertSame('resolved', $resolved->status);
        $this->assertSame('written_off', $this->copy->fresh()->status);
        $this->assertDatabaseHas('activity_logs', ['action_type' => 'incident.resolved', 'entity_id' => (string) $case->id]);

        $otherReader = $this->makeControlPlaneUser('member');
        ReaderProfile::forUser($otherReader);
        $otherCopy = BookCopy::factory()->create();
        $this->circulation->issue($otherReader, $otherCopy, $this->adminUser);
        $this->circulation->returnCopy($otherCopy, $this->adminUser, 'damaged', 'lost', null, 'Administrative error');
        $otherCase = CirculationIncidentCase::query()->where('reader_id', $otherReader->id)->firstOrFail();
        $cancelled = $this->incidents->cancel($otherCase, $this->adminUser, 'Return was entered against the wrong copy');
        $this->assertSame('cancelled', $cancelled->status);
        $this->assertDatabaseHas('activity_logs', ['action_type' => 'incident.cancelled', 'entity_id' => (string) $otherCase->id]);
    }

    public function test_reader_routes_are_owner_scoped(): void
    {
        $case = $this->openLost();
        $this->signInToLibraryAs($this->reader)->get(route('member.incidents.show', $case))->assertOk();
        $other = $this->makeControlPlaneUser('member');
        ReaderProfile::forUser($other);
        $this->signInToLibraryAs($other)->get(route('member.incidents.show', $case))->assertForbidden();
    }

    public function test_permissions_and_all_locales_expose_incident_labels(): void
    {
        $librarian = $this->makeControlPlaneUser('librarian');
        $senior = $this->makeControlPlaneUser('senior_librarian');
        $director = $this->makeControlPlaneUser('director');
        $this->assertTrue($librarian->can('incidents.create'));
        $this->assertFalse($librarian->can('incidents.approve_exception'));
        $this->assertTrue($senior->can('incidents.approve'));
        $this->assertTrue($director->can('incidents.approve_exception'));
        $this->assertFalse($director->can('incidents.register_replacement'));
        foreach (['ru', 'kk', 'en'] as $locale) {
            $this->assertNotSame('incidents.member.title', trans('incidents.member.title', [], $locale));
        }
    }

    public function test_staff_queue_report_and_csv_export_render(): void
    {
        $case = $this->openLost();

        $this->signInToLibraryAs($this->adminUser)
            ->get(route('librarian.incidents.index'))
            ->assertOk()
            ->assertSee($case->case_number);

        $this->signInToLibraryAs($this->adminUser)
            ->get(route('librarian.reports.index'))
            ->assertOk()
            ->assertSee(__('incidents.report.title'));

        $this->signInToLibraryAs($this->adminUser)
            ->get(route('librarian.reports.export', ['type' => 'incidents']))
            ->assertOk()
            ->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    private function openLost(float $fine = 1000): CirculationIncidentCase
    {
        $this->circulation->issue($this->reader, $this->copy, $this->adminUser);
        $this->circulation->returnCopy($this->copy, $this->adminUser, 'damaged', 'lost', $fine, 'Lost');

        return CirculationIncidentCase::query()->firstOrFail();
    }

    private function proposeExact(CirculationIncidentCase $case, array $overrides = []): ReplacementCandidate
    {
        $record = $this->copy->bibliographicRecord;

        return $this->incidents->propose($case, $this->adminUser, [
            'bibliographic_record_id' => $record->id, 'isbn' => $record->isbn, 'author' => $record->primary_author,
            'title' => $record->title, 'publisher' => $record->publisher, 'publication_year' => $record->publication_year,
            'language' => $record->language, 'resource_type' => $record->resource_type, 'udc_code' => $record->udc_code,
            'content_description' => $record->annotation, 'copy_condition' => 'good', ...$overrides,
        ]);
    }

    private function reviewedExact(): array
    {
        $case = $this->openLost();
        $candidate = $this->incidents->review($this->proposeExact($case), $this->adminUser, $this->allCriteria(true), 'Exact');

        return [$case, $candidate];
    }

    private function allCriteria(bool $value): array
    {
        return collect([...ReplacementCandidate::REQUIRED_CRITERIA, 'value_comparable', 'complete_set'])
            ->mapWithKeys(fn ($criterion) => [$criterion => $value])->all();
    }
}
