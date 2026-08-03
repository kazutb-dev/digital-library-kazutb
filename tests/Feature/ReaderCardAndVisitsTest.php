<?php

namespace Tests\Feature;

use App\Exceptions\CirculationException;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Catalog\LibraryVisit;
use App\Models\Catalog\ReaderProfile;
use App\Models\User;
use App\Services\Catalog\LibraryVisitService;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

/**
 * ДИР §9.4 — a scannable code on the reader card, and the attendance record it
 * feeds. Attendance is deliberately independent of circulation.
 */
class ReaderCardAndVisitsTest extends TestCase
{
    use BuildsAdminControlPlane;

    private LibraryVisitService $visits;

    private User $librarian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();

        $this->visits = app(LibraryVisitService::class);
        $this->librarian = $this->makeControlPlaneUser('librarian');
    }

    private function reader(): User
    {
        $reader = $this->makeControlPlaneUser('member');
        ReaderProfile::forUser($reader);

        return $reader;
    }

    public function test_a_new_profile_is_issued_a_scannable_barcode(): void
    {
        $profile = ReaderProfile::forUser($this->makeControlPlaneUser('member'));

        $this->assertNotNull($profile->barcode);
        $this->assertMatchesRegularExpression('/^RDR\d{8}$/', $profile->barcode);
    }

    public function test_barcodes_are_unique_across_readers(): void
    {
        $codes = collect(range(1, 5))
            ->map(fn (): string => (string) ReaderProfile::forUser($this->makeControlPlaneUser('member'))->barcode);

        $this->assertCount(5, $codes->unique(), 'Every card must carry a distinct code.');
    }

    /**
     * A profile created before §9.4 must not stay unscannable.
     */
    public function test_a_legacy_profile_without_a_barcode_is_backfilled_on_contact(): void
    {
        $reader = $this->reader();
        $profile = ReaderProfile::forUser($reader);
        $profile->forceFill(['barcode' => null])->save();

        $refreshed = ReaderProfile::forUser($reader->fresh());

        $this->assertNotNull($refreshed->barcode);
    }

    public function test_the_printable_reader_card_shows_the_barcode_and_ticket(): void
    {
        $reader = $this->reader();
        $profile = ReaderProfile::forUser($reader);

        $response = $this->signInToLibraryAs($this->librarian)
            ->get('/librarian/readers/'.$reader->getKey().'/card');

        $response->assertOk();
        $response->assertSee($profile->barcode);
        $response->assertSee($profile->ticket_number);
        $response->assertSee($reader->name);
        $response->assertSee(__('librarian.circulation.reader_card'));
    }

    public function test_the_circulation_reader_lookup_resolves_a_scanned_card_barcode(): void
    {
        $reader = $this->reader();
        $profile = ReaderProfile::forUser($reader);

        $response = $this->signInToLibraryAs($this->librarian)
            ->getJson('/librarian/circulation/reader-lookup?q='.$profile->barcode);

        $response->assertOk();
        $response->assertJsonPath('data.0.id', $reader->getKey());
        $response->assertJsonPath('data.0.barcode', $profile->barcode);
    }

    public function test_a_visit_is_recorded_from_a_scanned_barcode(): void
    {
        $reader = $this->reader();
        $profile = ReaderProfile::forUser($reader);
        $branch = Branch::query()->where('is_active', true)->firstOrFail();

        $response = $this->signInToLibraryAs($this->librarian)->post('/librarian/visits', [
            'code' => $profile->barcode,
            'branch_id' => $branch->getKey(),
        ]);

        $response->assertRedirect();
        $visit = LibraryVisit::query()->where('user_id', $reader->getKey())->firstOrFail();
        $this->assertSame((int) $branch->getKey(), (int) $visit->branch_id);
        $this->assertSame((int) $this->librarian->getKey(), (int) $visit->scanned_by);
        $this->assertSame('desk', $visit->source);
        ActivityLog::query()->where('action_type', 'visit.record')->firstOrFail();
    }

    public function test_the_ticket_number_also_works_as_a_scan_code(): void
    {
        $reader = $this->reader();
        $profile = ReaderProfile::forUser($reader);

        $resolved = $this->visits->findReaderByCode($profile->ticket_number);

        $this->assertSame((int) $reader->getKey(), (int) $resolved?->getKey());
    }

    public function test_an_unknown_code_is_rejected_without_creating_a_visit(): void
    {
        $response = $this->signInToLibraryAs($this->librarian)->post('/librarian/visits', [
            'code' => 'RDR99999999',
        ]);

        $response->assertSessionHasErrors('code');
        $this->assertSame(0, LibraryVisit::query()->count());
    }

    /**
     * Readers tap twice and door hardware repeats — neither should double-count.
     */
    public function test_a_repeat_scan_within_the_dedupe_window_does_not_double_count(): void
    {
        $reader = $this->reader();

        $first = $this->visits->record($reader, null, $this->librarian);
        $second = $this->visits->record($reader, null, $this->librarian);

        $this->assertFalse($first['duplicate']);
        $this->assertTrue($second['duplicate']);
        $this->assertSame((int) $first['visit']->getKey(), (int) $second['visit']->getKey());
        $this->assertSame(1, LibraryVisit::query()->count());
    }

    public function test_a_scan_after_the_dedupe_window_is_a_new_visit(): void
    {
        $reader = $this->reader();

        $first = $this->visits->record($reader, null, $this->librarian);
        $first['visit']->forceFill([
            'scanned_at' => now()->subMinutes(LibraryVisitService::DEDUPE_MINUTES + 5),
        ])->save();

        $second = $this->visits->record($reader, null, $this->librarian);

        $this->assertFalse($second['duplicate']);
        $this->assertSame(2, LibraryVisit::query()->count());
    }

    /**
     * Attendance is not circulation: a blocked reader who walks in still
     * walked in, and the door must not become an enforcement point.
     */
    public function test_a_blocked_reader_can_still_be_counted_as_present(): void
    {
        $reader = $this->reader();
        ReaderProfile::forUser($reader)->update(['status' => 'blocked', 'block_reason' => 'Unpaid fine']);

        $result = $this->visits->record($reader, null, $this->librarian);

        $this->assertFalse($result['duplicate']);
        $this->assertSame(1, LibraryVisit::query()->count());
    }

    public function test_a_user_without_a_reader_ticket_cannot_be_counted(): void
    {
        $stranger = $this->makeControlPlaneUser('member');

        try {
            $this->visits->record($stranger, null, $this->librarian);
            $this->fail('Attendance must stay tied to registered readers.');
        } catch (CirculationException $exception) {
            $this->assertSame('visit_reader_not_registered', $exception->reasonCode);
        }
    }

    /**
     * An unattended kiosk has no staff account behind the scan.
     */
    public function test_an_unattended_kiosk_scan_needs_no_staff_account(): void
    {
        $result = $this->visits->record($this->reader(), null, null, 'kiosk');

        $this->assertNull($result['visit']->scanned_by);
        $this->assertSame('kiosk', $result['visit']->source);
    }

    public function test_the_visits_screen_lists_recent_entries(): void
    {
        $reader = $this->reader();
        $this->visits->record($reader, null, $this->librarian);

        $response = $this->signInToLibraryAs($this->librarian)->get('/librarian/visits');

        $response->assertOk();
        $response->assertSee(__('librarian.visits.title'));
        $response->assertSee($reader->name);
        $response->assertSee(__('librarian.visits.sources.desk'));
    }

    public function test_the_lookup_endpoint_confirms_the_card_holder(): void
    {
        $reader = $this->reader();
        $profile = ReaderProfile::forUser($reader);

        $response = $this->signInToLibraryAs($this->librarian)
            ->getJson('/librarian/visits/lookup?code='.$profile->barcode);

        $response->assertOk();
        $response->assertJsonPath('data.name', $reader->name);
        $response->assertJsonPath('data.ticket', $profile->ticket_number);
    }

    public function test_daily_totals_are_zero_filled_across_quiet_days(): void
    {
        $reader = $this->reader();
        $visit = $this->visits->record($reader, null, $this->librarian)['visit'];
        $visit->forceFill(['scanned_at' => now()->subDays(2)->setTime(10, 0)])->save();

        $series = $this->visits->dailyTotals(now()->subDays(4)->startOfDay(), now()->endOfDay());

        $this->assertCount(5, $series);
        $this->assertSame(1, $series[now()->subDays(2)->toDateString()]);
        $this->assertSame(0, $series[now()->subDays(3)->toDateString()]);
    }

    public function test_branch_totals_split_visits_by_location(): void
    {
        $branches = Branch::query()->where('is_active', true)->orderBy('id')->take(2)->get();
        $this->assertCount(2, $branches, 'The seeded structure must provide two branches for this test.');

        $this->visits->record($this->reader(), (int) $branches[0]->getKey(), $this->librarian);
        $this->visits->record($this->reader(), (int) $branches[1]->getKey(), $this->librarian);
        $this->visits->record($this->reader(), (int) $branches[1]->getKey(), $this->librarian);

        $rows = $this->visits->branchTotals(now()->subDay(), now()->addDay());

        $this->assertSame($branches[1]->name, $rows->first()->branch);
        $this->assertSame(2, (int) $rows->first()->visits);
    }

    public function test_the_attendance_report_renders_and_exports(): void
    {
        $reader = $this->reader();
        $this->visits->record($reader, null, $this->librarian);

        $page = $this->signInToLibraryAs($this->librarian)->get('/librarian/reports');
        $page->assertOk();
        $page->assertSee(__('librarian.reports.visits'));
        $page->assertSee(__('librarian.reports.visit_metrics.total'));

        $export = $this->signInToLibraryAs($this->librarian)->get('/librarian/reports/visits/export');
        $export->assertOk();
        $export->assertHeader('Content-Type', 'text/csv; charset=UTF-8');
    }

    public function test_a_member_cannot_reach_the_attendance_desk(): void
    {
        $this->signInToLibraryAs($this->reader())->get('/librarian/visits')->assertForbidden();
    }

    public function test_visit_labels_exist_in_every_locale(): void
    {
        $keys = [
            'librarian.nav.visits',
            'librarian.visits.title',
            'librarian.visits.subtitle',
            'librarian.visits.code',
            'librarian.visits.code_help',
            'librarian.visits.record',
            'librarian.visits.recent',
            'librarian.visits.empty',
            'librarian.visits.reader_not_found',
            'librarian.visits.recorded_success',
            'librarian.visits.duplicate_success',
            'librarian.visits.dedupe_hint',
            'librarian.visits.preview_found',
            'librarian.visits.unattended',
            'librarian.visits.open_report',
            'librarian.visits.branch_unspecified',
            'librarian.reports.visits',
            'librarian.reports.visits_hint',
            'librarian.reports.visits_empty',
            'librarian.reports.visits_weekly_note',
            'librarian.reports.columns.visits',
            'librarian.reports.columns.unique_readers',
            'librarian.circulation.reader_card',
            'librarian.circulation.reader_card_scan_note',
            'librarian.errors.visit_reader_not_registered',
        ];

        foreach (['ru', 'kk', 'en'] as $locale) {
            app()->setLocale($locale);
            foreach ($keys as $key) {
                $this->assertNotSame($key, __($key), "Missing {$locale} translation for {$key}");
            }
            foreach (LibraryVisit::SOURCES as $source) {
                $key = 'librarian.visits.sources.'.$source;
                $this->assertNotSame($key, __($key), "Missing {$locale} label for visit source {$source}");
            }
            foreach (['today', 'today_readers', 'week'] as $metric) {
                $key = 'librarian.visits.metrics.'.$metric;
                $this->assertNotSame($key, __($key), "Missing {$locale} label for visit metric {$metric}");
            }
        }
        app()->setLocale('ru');
    }
}
