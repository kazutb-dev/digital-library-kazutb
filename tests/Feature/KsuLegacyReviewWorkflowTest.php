<?php

namespace Tests\Feature;

use App\Http\Controllers\Librarian\KsuRegisterController;
use App\Models\ActivityLog;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Ksu\KsuAuditEvent;
use App\Models\Ksu\KsuBook;
use App\Models\Ksu\KsuConflict;
use App\Models\Ksu\KsuEntry;
use App\Models\Ksu\KsuEntryItem;
use App\Models\User;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Route;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Concerns\BuildsAcquisitionOperations;
use Tests\TestCase;

class KsuLegacyReviewWorkflowTest extends TestCase
{
    use BuildsAcquisitionOperations;

    private User $actor;

    private KsuBook $partOne;

    private BibliographicRecord $record;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAcquisitionOperations();
        Gate::before(static fn (): bool => true);
        $this->registerRoutes();

        $this->actor = User::query()->create([
            'name' => 'KSU Senior Reviewer',
            'email' => 'ksu-reviewer@example.test',
            'password' => 'test-password',
            'locale' => 'ru',
        ]);
        $this->partOne = KsuBook::query()->create([
            'code' => 'KSU-1',
            'name' => 'KSU Part 1',
            'auto_numbering_enabled' => false,
            'requires_manual_decision' => true,
            'is_active' => true,
        ]);
        $this->record = BibliographicRecord::query()->create([
            'title' => 'Legacy KSU review record',
            'primary_author' => 'Source Author',
        ]);
    }

    public function test_default_queue_groups_exact_raw_values_with_ranges_and_examples(): void
    {
        foreach ([
            [101, 301, '2020-01-10'],
            [102, 302, '2020-02-15'],
            [103, 303, '2020-03-20'],
        ] as [$sourceInv, $sourceDoc, $date]) {
            $this->copy($sourceInv, $date);
            $this->conflict('12/2020', $sourceInv, $sourceDoc);
        }
        $this->copy(104, '2020-04-01');
        $this->conflict('13/2020', 104, 304);

        $response = $this->actingAs($this->actor)->get('/__test/ksu-review/conflicts');

        $response->assertOk()->assertViewHas('grouped', true);
        $groups = $response->viewData('groups');
        $this->assertSame(2, $groups->total());
        $group = $groups->getCollection()->firstWhere('ksu_number_raw', '12/2020');
        $this->assertNotNull($group);
        $this->assertSame(3, $group->conflict_count);
        $this->assertSame(101, $group->source_inv_min);
        $this->assertSame(103, $group->source_inv_max);
        $this->assertSame(301, $group->source_doc_min);
        $this->assertSame(303, $group->source_doc_max);
        $this->assertSame('2020-01-10', $group->registration_date_from);
        $this->assertSame('2020-03-20', $group->registration_date_to);
        $this->assertCount(3, $group->examples);
        $this->assertTrue($group->valid_historical_number);
        $response->assertSee('12/2020', false)
            ->assertSee('Строк: 3', false);
    }

    public function test_group_links_to_existing_entry_with_items_copy_foreign_keys_and_both_audits(): void
    {
        $entry = $this->entry('8/2020', 8, 2020);
        $first = $this->copy(201, '2020-05-01');
        $second = $this->copy(202, '2020-05-02');
        $this->conflict('9/2020', 201, 401);
        $this->conflict('9/2020', 202, 402);

        $this->actingAs($this->actor)->post('/__test/ksu-review/conflicts/resolve-group', [
            'ksu_number_raw' => '9/2020',
            'action' => 'link_existing',
            'ksu_entry_id' => $entry->getKey(),
            'resolution_note' => 'Verified the exact source group against the existing ledger entry.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame($entry->getKey(), (int) $first->refresh()->ksu_entry_id);
        $this->assertSame($entry->getKey(), (int) $second->refresh()->ksu_entry_id);
        $this->assertSame(2, KsuEntryItem::query()->where('ksu_entry_id', $entry->getKey())->count());
        $this->assertSame(2, KsuConflict::query()->where('ksu_number_raw', '9/2020')->where('status', 'resolved')->count());
        $this->assertSame(2, KsuConflict::query()->where('ksu_number_raw', '9/2020')->count());
        $this->assertDatabaseHas('ksu_audit_events', [
            'event_type' => 'legacy.group_linked',
            'ksu_entry_id' => $entry->getKey(),
        ]);
        $this->assertSame(2, KsuAuditEvent::query()->where('event_type', 'legacy.group_item_linked')->count());
        $this->assertDatabaseHas('activity_logs', [
            'action_type' => 'ksu.legacy.group_linked',
            'entity_type' => 'ksu_conflict_group',
            'entity_id' => '9/2020',
        ]);
    }

    public function test_valid_numeric_tuple_creates_historical_part_one_entry_then_links_group(): void
    {
        $first = $this->copy(301, '2018-01-12', '100.50');
        $second = $this->copy(302, '2018-02-14', '200.25');
        $this->conflict('14/2018', 301, 501);
        $this->conflict('14/2018', 302, 502);

        $this->actingAs($this->actor)->post('/__test/ksu-review/conflicts/resolve-group', [
            'ksu_number_raw' => '14/2018',
            'action' => 'create_historical',
            'resolution_note' => 'The untouched source tuple and both inventory rows were verified.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $entry = KsuEntry::query()->where('entry_number', '14/2018')->firstOrFail();
        $this->assertSame($this->partOne->getKey(), $entry->ksu_book_id);
        $this->assertSame(14, $entry->number);
        $this->assertSame(2018, $entry->year);
        $this->assertSame('legacy', $entry->status);
        $this->assertSame('2018-01-12', $entry->entry_date?->toDateString());
        $this->assertSame(2, $entry->copy_count);
        $this->assertSame('300.75', $entry->total_cost);
        $this->assertSame($entry->getKey(), (int) $first->refresh()->ksu_entry_id);
        $this->assertSame($entry->getKey(), (int) $second->refresh()->ksu_entry_id);
        $this->assertDatabaseCount('ksu_entry_items', 2);
        $this->assertDatabaseHas('ksu_audit_events', [
            'event_type' => 'legacy.historical_entry_created',
            'ksu_entry_id' => $entry->getKey(),
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action_type' => 'ksu.legacy.historical_created',
            'entity_type' => 'ksu_entry',
            'entity_id' => (string) $entry->getKey(),
        ]);
    }

    public static function malformedNumbers(): iterable
    {
        yield 'decimal number' => ['4.05/2026'];
        yield 'five digit padded year' => ['4/02026'];
    }

    #[DataProvider('malformedNumbers')]
    public function test_malformed_number_is_never_autocorrected_or_mutated(string $rawNumber): void
    {
        $copy = $this->copy(401, '2026-01-01');
        $conflict = $this->conflict($rawNumber, 401, 601);

        $this->actingAs($this->actor)->from('/__test/ksu-review/conflicts')->post('/__test/ksu-review/conflicts/resolve-group', [
            'ksu_number_raw' => $rawNumber,
            'action' => 'create_historical',
            'resolution_note' => 'Attempted strict historical recovery.',
        ])->assertRedirect('/__test/ksu-review/conflicts')->assertSessionHasErrors('ksu_number_raw');

        $this->assertDatabaseCount('ksu_entries', 0);
        $this->assertDatabaseCount('ksu_entry_items', 0);
        $this->assertDatabaseCount('ksu_audit_events', 0);
        $this->assertDatabaseCount('activity_logs', 0);
        $this->assertNull($copy->refresh()->ksu_entry_id);
        $this->assertSame('open', $conflict->refresh()->status);
        $this->assertSame($rawNumber, $conflict->ksu_number_raw);
    }

    public function test_missing_copy_rolls_back_the_whole_group(): void
    {
        $entry = $this->entry('20/2020', 20, 2020);
        $copy = $this->copy(501, '2020-01-01');
        $first = $this->conflict('21/2020', 501, 701);
        $second = $this->conflict('21/2020', 999999, 702);

        $this->actingAs($this->actor)->from('/__test/ksu-review/conflicts')->post('/__test/ksu-review/conflicts/resolve-group', [
            'ksu_number_raw' => '21/2020',
            'action' => 'link_existing',
            'ksu_entry_id' => $entry->getKey(),
            'resolution_note' => 'Both rows must resolve or neither may change.',
        ])->assertRedirect('/__test/ksu-review/conflicts')->assertSessionHasErrors('ksu_number_raw');

        $this->assertNull($copy->refresh()->ksu_entry_id);
        $this->assertSame('open', $first->refresh()->status);
        $this->assertSame('open', $second->refresh()->status);
        $this->assertDatabaseCount('ksu_entry_items', 0);
        $this->assertDatabaseCount('ksu_audit_events', 0);
        $this->assertDatabaseCount('activity_logs', 0);
    }

    public function test_source_error_is_audited_but_leave_unresolved_is_a_true_no_op(): void
    {
        $copy = $this->copy(601, '2020-06-01');
        $ignored = $this->conflict('source typo', 601, 801);

        $this->actingAs($this->actor)->post('/__test/ksu-review/conflicts/resolve-group', [
            'ksu_number_raw' => 'source typo',
            'action' => 'ignore',
            'resolution_note' => 'Confirmed as an error in the immutable source row.',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('ignored', $ignored->refresh()->status);
        $this->assertSame('source typo', $ignored->ksu_number_raw);
        $this->assertNull($copy->refresh()->ksu_entry_id);
        $this->assertDatabaseCount('ksu_entry_items', 0);
        $this->assertDatabaseHas('ksu_audit_events', ['event_type' => 'legacy.group_ignored']);
        $this->assertDatabaseHas('activity_logs', ['action_type' => 'ksu.legacy.group_ignored']);

        $unresolved = $this->conflict('30/2020', 601, 802);
        $auditCount = KsuAuditEvent::query()->count();
        $activityCount = ActivityLog::query()->count();
        $this->actingAs($this->actor)->post('/__test/ksu-review/conflicts/resolve-group', [
            'ksu_number_raw' => '30/2020',
            'action' => 'leave_unresolved',
        ])->assertRedirect()->assertSessionHasNoErrors();

        $this->assertSame('open', $unresolved->refresh()->status);
        $this->assertSame($auditCount, KsuAuditEvent::query()->count());
        $this->assertSame($activityCount, ActivityLog::query()->count());
    }

    private function copy(int $legacyInvId, string $registrationDate, ?string $price = null): BookCopy
    {
        return BookCopy::query()->create([
            'bibliographic_record_id' => $this->record->getKey(),
            'inventory_number' => 'LEGACY-'.$legacyInvId,
            'barcode' => 'LEG'.str_pad((string) $legacyInvId, 9, '0', STR_PAD_LEFT),
            'legacy_inv_id' => $legacyInvId,
            'legacy_doc_id' => 10000 + $legacyInvId,
            'registration_date' => $registrationDate,
            'price' => $price,
            'status' => 'available',
            'inventory_status' => 'active',
            'circulation_status' => 'available',
        ]);
    }

    private function conflict(string $rawNumber, int $sourceInvId, int $sourceDocId): KsuConflict
    {
        return KsuConflict::query()->create([
            'ksu_book_id' => $this->partOne->getKey(),
            'kind' => 'unresolved_link',
            'ksu_number_raw' => $rawNumber,
            'source_inv_id' => $sourceInvId,
            'source_doc_id' => $sourceDocId,
            'reason' => 'NO_EXACT_KSU1_MATCH',
            'payload' => ['source' => 'INV.T990t'],
            'status' => 'open',
        ]);
    }

    private function entry(string $entryNumber, int $number, int $year): KsuEntry
    {
        return KsuEntry::query()->create([
            'ksu_book_id' => $this->partOne->getKey(),
            'entry_number' => $entryNumber,
            'number' => $number,
            'year' => $year,
            'status' => 'legacy',
        ]);
    }

    private function registerRoutes(): void
    {
        Route::middleware('web')->prefix('__test/ksu-review')->group(function (): void {
            Route::get('/', fn () => response('KSU'))->name('librarian.ksu.index');
            Route::get('/conflicts', [KsuRegisterController::class, 'conflicts'])->name('librarian.ksu.conflicts');
            Route::post('/conflicts/resolve-group', [KsuRegisterController::class, 'resolveGroup'])->name('librarian.ksu.conflicts.resolve-group');
            Route::post('/conflicts/{conflict}/resolve', [KsuRegisterController::class, 'resolve'])->name('librarian.ksu.conflicts.resolve');
            Route::get('/entries/{entry}', fn (KsuEntry $entry) => response($entry->entry_number))->name('librarian.ksu.show');
        });
        Route::getRoutes()->refreshNameLookups();
    }
}
