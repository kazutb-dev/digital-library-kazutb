<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\ReaderProfile;
use App\Models\Setting;
use App\Models\User;
use App\Services\Catalog\CirculationService;
use App\Services\Catalog\LoanPeriodPolicy;
use App\Services\Catalog\ReservationInsightService;
use App\Services\Catalog\ReservationQueueService;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

/**
 * ДИР §9.3 — the loan period is derived from how many copies the library holds
 * of the edition (3–7 days), with reading-room stock still on its own rule.
 */
class LoanPeriodScaleTest extends TestCase
{
    use BuildsAdminControlPlane;

    private LoanPeriodPolicy $policy;

    private CirculationService $circulation;

    private User $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();

        $this->policy = app(LoanPeriodPolicy::class);
        $this->circulation = app(CirculationService::class);
        $this->reader = $this->makeControlPlaneUser('member');
        ReaderProfile::forUser($this->reader);
    }

    /** Writes through the model so the json cast on `value` is applied. */
    private function setSetting(string $key, int $value): void
    {
        Setting::query()->where('key', $key)->firstOrFail()->update(['value' => $value]);
    }

    private function recordWithCopies(int $count, array $copyAttributes = []): BibliographicRecord
    {
        $record = BibliographicRecord::factory()->create();
        BookCopy::factory()->count($count)->create([
            'bibliographic_record_id' => $record->getKey(),
            'status' => 'available',
            ...$copyAttributes,
        ]);

        return $record;
    }

    public function test_the_seeded_scale_matches_the_dir_range_of_three_to_seven_days(): void
    {
        $days = collect($this->policy->tiers())->pluck('days');

        $this->assertSame(3, $days->min(), 'ДИР §9.3 lower bound.');
        $this->assertSame(7, $days->max(), 'ДИР §9.3 upper bound.');
    }

    public function test_a_single_copy_edition_is_issued_for_three_days(): void
    {
        $record = $this->recordWithCopies(1);
        $copy = $record->copies()->first();

        $loan = $this->circulation->issue($this->reader, $copy, $this->adminUser);

        $this->assertSame(3, $this->policy->daysForCopy($copy));
        $this->assertSame(now()->addDays(3)->toDateString(), $loan->due_at->toDateString());
    }

    public function test_a_ten_copy_edition_is_issued_for_seven_days(): void
    {
        $record = $this->recordWithCopies(10);
        $copy = $record->copies()->first();

        $loan = $this->circulation->issue($this->reader, $copy, $this->adminUser);

        $this->assertSame(7, $this->policy->daysForCopy($copy));
        $this->assertSame(now()->addDays(7)->toDateString(), $loan->due_at->toDateString());
    }

    /**
     * The tier boundaries are where an off-by-one would hide.
     */
    public function test_every_tier_boundary_resolves_to_the_expected_period(): void
    {
        $expected = [
            0 => 3, 1 => 3, 2 => 3,   // scarce, ceiling 2
            3 => 5, 4 => 5, 5 => 5,   // standard, ceiling 5
            6 => 7, 7 => 7, 50 => 7,  // abundant
        ];

        foreach ($expected as $copies => $days) {
            $this->assertSame($days, $this->policy->daysForCopyCount($copies), "copies={$copies}");
        }
    }

    public function test_lost_and_written_off_copies_do_not_make_an_edition_look_abundant(): void
    {
        $record = BibliographicRecord::factory()->create();
        BookCopy::factory()->create(['bibliographic_record_id' => $record->getKey(), 'status' => 'available']);
        BookCopy::factory()->count(8)->create(['bibliographic_record_id' => $record->getKey(), 'status' => 'written_off']);

        $this->assertSame(1, $this->policy->circulatingCopies((int) $record->getKey()));
        $this->assertSame(3, $this->policy->daysForRecord((int) $record->getKey()));
    }

    public function test_issued_copies_still_count_toward_the_pool(): void
    {
        $record = BibliographicRecord::factory()->create();
        BookCopy::factory()->create(['bibliographic_record_id' => $record->getKey(), 'status' => 'available']);
        BookCopy::factory()->count(6)->create(['bibliographic_record_id' => $record->getKey(), 'status' => 'issued']);

        // Scarcity is about the size of the pool, not what is free right now.
        $this->assertSame(7, $this->policy->circulatingCopies((int) $record->getKey()));
        $this->assertSame(7, $this->policy->daysForRecord((int) $record->getKey()));
    }

    public function test_reading_room_stock_keeps_its_own_period_regardless_of_copy_count(): void
    {
        $record = $this->recordWithCopies(10, ['access_restriction' => 'reading_room']);
        $copy = $record->copies()->first();

        $this->assertSame(
            (int) Setting::valueFor('reference_loan_period_days', 1),
            $this->policy->daysForCopy($copy),
            'Reading-room material is a different axis from scarcity.',
        );

        $loan = $this->circulation->issue($this->reader, $copy, $this->adminUser);
        $this->assertSame(now()->addDay()->toDateString(), $loan->due_at->toDateString());
    }

    public function test_the_scale_is_retunable_from_settings_without_touching_code(): void
    {
        $this->setSetting('loan_period_scarce_days', 1);
        $this->setSetting('loan_period_scarce_max_copies', 4);
        $this->setSetting('loan_period_abundant_days', 21);

        $this->assertSame(1, $this->policy->daysForCopyCount(4));
        $this->assertSame(21, $this->policy->daysForCopyCount(99));
    }

    /**
     * A saved standard ceiling below the scarce one would otherwise create an
     * unreachable tier.
     */
    public function test_inverted_thresholds_are_normalised_instead_of_creating_a_dead_tier(): void
    {
        $this->setSetting('loan_period_scarce_max_copies', 8);
        $this->setSetting('loan_period_standard_max_copies', 2);

        $tiers = $this->policy->tiers();

        $this->assertGreaterThan($tiers[0]['max_copies'], $tiers[1]['max_copies']);
        $this->assertSame(3, $this->policy->daysForCopyCount(8));
        $this->assertSame(5, $this->policy->daysForCopyCount(9));
    }

    public function test_the_derived_period_is_recorded_in_the_audit_trail(): void
    {
        $record = $this->recordWithCopies(1);
        $loan = $this->circulation->issue($this->reader, $record->copies()->first(), $this->adminUser);

        $entry = ActivityLog::query()
            ->where('action_type', 'circulation.issue')
            ->where('entity_id', (string) $loan->getKey())
            ->firstOrFail();

        $this->assertSame(3, (int) data_get($entry->new_values, 'loan_period_days'));
    }

    /**
     * The queue forecast assumes copies turn over once per loan period, so it
     * must use the same derived number rather than a flat library-wide one.
     */
    public function test_the_reservation_forecast_uses_the_derived_period(): void
    {
        $record = BibliographicRecord::factory()->create();
        BookCopy::factory()->count(2)->create(['bibliographic_record_id' => $record->getKey(), 'status' => 'issued']);

        $reservation = app(ReservationQueueService::class)
            ->create($this->reader, $record);

        // 2 copies → scarce tier → 3 days. Position 1 ÷ 2 copies → ceil(1.5) = 2.
        $this->assertSame(1, $reservation->queue_position);
        $this->assertSame(2, app(ReservationInsightService::class)->estimatedDaysUntilAvailable($reservation));
    }

    /**
     * @return array<string, mixed>
     */
    private function settingsPayload(array $overrides = []): array
    {
        return [
            'max_active_reservations' => 3,
            'reservation_lifespan_days' => 3,
            'max_active_loans' => 5,
            'standard_loan_period_days' => 14,
            'reference_loan_period_days' => 1,
            'loan_period_scarce_max_copies' => 2,
            'loan_period_scarce_days' => 3,
            'loan_period_standard_max_copies' => 5,
            'loan_period_standard_days' => 5,
            'loan_period_abundant_days' => 7,
            'renewal_allowed' => 1,
            'renewal_period_days' => 14,
            'overdue_blocking_enabled' => 1,
            'fine_per_overdue_day' => 100,
            'notification_channels' => ['in_app'],
            // The dictionaries are slug lists: no spaces allowed.
            'news_categories' => 'anons',
            'message_categories' => 'obshchii-vopros',
            'default_ui_language' => 'ru',
            'results_per_page' => 20,
            'catalog_page_size' => 12,
            ...$overrides,
        ];
    }

    public function test_the_scale_is_editable_through_the_admin_settings_form(): void
    {
        $response = $this->signInToLibraryAs($this->adminUser)->patch(
            '/admin/settings',
            $this->settingsPayload([
                'loan_period_scarce_days' => 4,
                'loan_period_abundant_days' => 6,
            ]),
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $this->assertSame(4, (int) Setting::valueFor('loan_period_scarce_days'));
        $this->assertSame(6, (int) Setting::valueFor('loan_period_abundant_days'));
        $this->assertSame(4, $this->policy->daysForCopyCount(1));
        $this->assertSame(6, $this->policy->daysForCopyCount(99));
    }

    /**
     * A standard ceiling at or below the scarce one is rejected at the form so
     * the librarian is told rather than silently getting a normalised scale.
     */
    public function test_the_form_rejects_a_standard_ceiling_below_the_scarce_one(): void
    {
        $response = $this->signInToLibraryAs($this->adminUser)->patch(
            '/admin/settings',
            $this->settingsPayload([
                'loan_period_scarce_max_copies' => 6,
                'loan_period_standard_max_copies' => 4,
            ]),
        );

        $response->assertSessionHasErrors('loan_period_standard_max_copies');
        $this->assertSame(2, (int) Setting::valueFor('loan_period_scarce_max_copies'), 'A rejected save must change nothing.');
    }

    public function test_loan_scale_settings_are_labelled_in_every_locale(): void
    {
        $keys = [
            'settings.circulation.loan_scale_title',
            'settings.circulation.loan_scale_description',
            'settings.circulation.loan_scale_current',
            'settings.circulation.loan_scale_row',
            'settings.circulation.loan_scale_note',
            'settings.circulation.loan_period_scarce_max_copies',
            'settings.circulation.loan_period_scarce_max_copies_help',
            'settings.circulation.loan_period_scarce_days',
            'settings.circulation.loan_period_scarce_days_help',
            'settings.circulation.loan_period_standard_max_copies',
            'settings.circulation.loan_period_standard_max_copies_help',
            'settings.circulation.loan_period_standard_days',
            'settings.circulation.loan_period_standard_days_help',
            'settings.circulation.loan_period_abundant_days',
            'settings.circulation.loan_period_abundant_days_help',
            'librarian.catalog.loan_period_hint',
            'librarian.catalog.loan_period_scale',
        ];

        foreach (['ru', 'kk', 'en'] as $locale) {
            app()->setLocale($locale);
            foreach ($keys as $key) {
                $this->assertNotSame($key, __($key), "Missing {$locale} translation for {$key}");
            }
        }
        app()->setLocale('ru');
    }
}
