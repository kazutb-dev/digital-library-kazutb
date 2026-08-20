<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\CopyHistory;
use App\Models\DataQualityIssue;
use App\Services\DataQuality\DataQualityScanner;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class BarcodeMarkingWorkflowTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
    }

    public function test_single_assignment_links_a_generated_barcode_to_the_existing_copy_and_audits_it(): void
    {
        $staff = $this->makeControlPlaneUser('librarian');
        $copy = BookCopy::factory()->create(['barcode' => null, 'status' => 'available']);
        $before = BookCopy::query()->count();

        $this->signInToLibraryAs($staff)->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.copies.barcode.assign', $copy), ['mode' => 'generate', 'inventory_number_confirmation' => $copy->inventory_number, 'confirmed' => '1'])
            ->assertRedirect(route('librarian.copies.show', $copy));

        $copy->refresh();
        $this->assertSame($before, BookCopy::query()->count());
        $this->assertSame('KUTB'.str_pad((string) $copy->getKey(), 8, '0', STR_PAD_LEFT), $copy->barcode);
        $this->assertDatabaseHas('copy_history', ['copy_id' => $copy->getKey(), 'event_type' => 'barcode_assigned']);
        $this->assertDatabaseHas('activity_logs', ['entity_id' => (string) $copy->getKey(), 'action_type' => 'copies.barcode_assign']);
    }

    public function test_barcode_assignment_is_blocked_when_inventory_confirmation_does_not_match(): void
    {
        $staff = $this->makeControlPlaneUser('librarian');
        $copy = BookCopy::factory()->create(['barcode' => null]);

        $this->signInToLibraryAs($staff)->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.copies.barcode.assign', $copy), [
                'mode' => 'generate', 'inventory_number_confirmation' => 'WRONG-INVENTORY', 'confirmed' => '1',
            ])->assertSessionHasErrors('inventory_number_confirmation');

        $this->assertNull($copy->fresh()->barcode);
    }

    public function test_existing_physical_barcode_can_be_scanned_but_cannot_be_silently_reassigned(): void
    {
        $staff = $this->makeControlPlaneUser('librarian');
        $first = BookCopy::factory()->create(['barcode' => 'KUTB00999991']);
        $second = BookCopy::factory()->create(['barcode' => null]);

        $this->signInToLibraryAs($staff)->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.copies.barcode.assign', $second), ['mode' => 'existing', 'barcode' => $first->barcode, 'inventory_number_confirmation' => $second->inventory_number, 'confirmed' => '1'])
            ->assertSessionHasErrors('barcode');
        $this->assertNull($second->fresh()->barcode);

        $this->signInToLibraryAs($staff)->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.copies.barcode.assign', $first), ['mode' => 'existing', 'barcode' => 'KUTB00999992', 'inventory_number_confirmation' => $first->inventory_number, 'confirmed' => '1'])
            ->assertSessionHasErrors('barcode');
        $this->assertSame('KUTB00999991', $first->fresh()->barcode);
    }

    public function test_physical_confirmation_requires_the_exact_barcode(): void
    {
        $staff = $this->makeControlPlaneUser('librarian');
        $copy = BookCopy::factory()->create(['barcode' => 'KUTB00999881']);

        $this->signInToLibraryAs($staff)->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.copies.barcode.confirm', $copy), ['scanned_barcode' => 'KUTB-WRONG'])
            ->assertSessionHasErrors('scanned_barcode');
        $this->assertSame(0, CopyHistory::query()->where('copy_id', $copy->getKey())->where('event_type', 'barcode_confirmed')->count());

        $this->signInToLibraryAs($staff)->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.copies.barcode.confirm', $copy), ['scanned_barcode' => $copy->barcode])
            ->assertSessionHas('success');
        $this->assertDatabaseHas('copy_history', ['copy_id' => $copy->getKey(), 'event_type' => 'barcode_confirmed']);
    }

    public function test_batch_is_previewed_and_only_eligible_unmarked_copies_are_prepared(): void
    {
        $lead = $this->makeControlPlaneUser('senior_librarian');
        $ready = BookCopy::factory()->create(['barcode' => null, 'status' => 'available']);
        $marked = BookCopy::factory()->create(['barcode' => 'KUTB00999771', 'status' => 'available']);
        $lost = BookCopy::factory()->create(['barcode' => null, 'status' => 'lost']);
        $ids = [$ready->id, $marked->id, $lost->id];

        $this->signInToLibraryAs($lead)->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.copies.barcode-batches.preview'), ['copy_ids' => $ids])
            ->assertOk()->assertSee($ready->inventory_number)->assertSee($lost->inventory_number);

        $this->signInToLibraryAs($lead)->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.copies.barcode-batches.prepare'), ['copy_ids' => $ids, 'confirmed' => '1'])
            ->assertRedirectContains('/librarian/copy-labels');

        $this->assertNotNull($ready->fresh()->barcode);
        $this->assertSame('KUTB00999771', $marked->fresh()->barcode);
        $this->assertNull($lost->fresh()->barcode);
    }

    public function test_batch_preparation_is_idempotent_for_an_already_marked_copy(): void
    {
        $lead = $this->makeControlPlaneUser('senior_librarian');
        $copy = BookCopy::factory()->create(['barcode' => 'KUTB00999661']);

        $this->signInToLibraryAs($lead)->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.copies.barcode-batches.prepare'), ['copy_ids' => [$copy->id], 'confirmed' => '1'])
            ->assertSessionHasErrors('copy_ids');

        $this->assertSame('KUTB00999661', $copy->fresh()->barcode);
        $this->assertSame(0, ActivityLog::query()->where('entity_id', (string) $copy->id)->where('action_type', 'copies.barcode_assign')->count());
    }

    public function test_label_contains_only_machine_identifier_inventory_and_optional_sigla(): void
    {
        $staff = $this->makeControlPlaneUser('librarian');
        $record = BibliographicRecord::factory()->create(['title' => 'A title that must not be printed']);
        $copy = BookCopy::factory()->create(['bibliographic_record_id' => $record->id, 'barcode' => 'KUTB00999551', 'inventory_number' => 'INV-99551', 'storage_sigla' => 'NB']);

        $this->signInToLibraryAs($staff)->get(route('librarian.copies.label', $copy))
            ->assertOk()->assertSee('KUTB00999551')->assertSee('INV-99551')->assertSee('NB')
            ->assertDontSee('A title that must not be printed')->assertDontSee('<svg class="qr"', false);
    }

    public function test_reader_and_director_cannot_assign_barcodes(): void
    {
        $copy = BookCopy::factory()->create(['barcode' => null]);
        foreach (['member', 'director'] as $role) {
            $user = $this->makeControlPlaneUser($role);
            $this->signInToLibraryAs($user)->withoutMiddleware(PreventRequestForgery::class)
                ->post(route('librarian.copies.barcode.assign', $copy), ['mode' => 'generate', 'inventory_number_confirmation' => $copy->inventory_number, 'confirmed' => '1'])
                ->assertForbidden();
        }
        $this->assertNull($copy->fresh()->barcode);
    }

    public function test_cataloguer_can_assign_one_barcode_but_cannot_prepare_a_print_batch(): void
    {
        $cataloguer = $this->makeControlPlaneUser('cataloguer');
        $copy = BookCopy::factory()->create(['barcode' => null]);

        $this->signInToLibraryAs($cataloguer)->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.copies.barcode.assign', $copy), ['mode' => 'generate', 'inventory_number_confirmation' => $copy->inventory_number, 'confirmed' => '1'])
            ->assertRedirect();
        $this->signInToLibraryAs($cataloguer)->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.copies.barcode-batches.preview'), ['copy_ids' => [$copy->id]])
            ->assertForbidden();
    }

    public function test_marking_pages_are_localized_in_ru_kk_and_en_without_raw_keys(): void
    {
        $staff = $this->makeControlPlaneUser('librarian');
        $copy = BookCopy::factory()->create(['barcode' => null]);
        $labels = [
            'ru' => 'Маркировка экземпляра',
            'kk' => 'Дананы таңбалау',
            'en' => 'Copy marking',
        ];

        foreach ($labels as $locale => $label) {
            $staff->update(['locale' => $locale]);
            $this->signInToLibraryAs($staff)->get(route('librarian.copies.show', [$copy, 'lang' => $locale]))
                ->assertOk()->assertSee($label)->assertDontSee('librarian.copies.marking');
        }
    }

    public function test_assignment_scoped_revalidation_resolves_the_missing_barcode_recommendation(): void
    {
        $staff = $this->makeControlPlaneUser('librarian');
        $copy = BookCopy::factory()->create(['barcode' => null, 'status' => 'available']);
        app(DataQualityScanner::class)->scanModel($copy, 'book_copy');
        $issue = DataQualityIssue::query()->where('entity_type', 'book_copy')->where('entity_id', (string) $copy->id)
            ->where('rule_code', 'copy.barcode.missing')->firstOrFail();
        $this->assertNotContains($issue->status, ['resolved', 'dismissed']);

        $this->signInToLibraryAs($staff)->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.copies.barcode.assign', $copy), ['mode' => 'generate', 'inventory_number_confirmation' => $copy->inventory_number, 'confirmed' => '1'])
            ->assertRedirect();

        $this->assertContains($issue->fresh()->status, ['resolved', 'dismissed']);
    }
}
