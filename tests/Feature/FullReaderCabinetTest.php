<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\ElectronicMaterial;
use App\Models\Catalog\Loan;
use App\Models\Catalog\ReaderNotification;
use App\Models\Catalog\ReaderProfile;
use App\Models\Catalog\Reservation;
use App\Models\ContactMessage;
use App\Models\LiteratureCollection;
use App\Models\User;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class FullReaderCabinetTest extends TestCase
{
    use BuildsAdminControlPlane;

    private User $reader;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        $this->reader = $this->makeControlPlaneUser('member');
        ReaderProfile::forUser($this->reader);
    }

    public function test_dashboard_is_private_and_contains_only_the_signed_in_readers_aggregates(): void
    {
        $ownCopy = BookCopy::factory()->create(['status' => 'issued']);
        Loan::factory()->create(['user_id' => $this->reader->getKey(), 'copy_id' => $ownCopy->getKey()]);
        $other = $this->makeControlPlaneUser('member');
        $otherCopy = BookCopy::factory()->create(['status' => 'issued']);
        Loan::factory()->create(['user_id' => $other->getKey(), 'copy_id' => $otherCopy->getKey()]);

        $response = $this->signInToLibraryAs($this->reader)->get('/dashboard');

        $response->assertOk();
        $this->assertStringContainsString('private', (string) $response->headers->get('Cache-Control'));
        $this->assertStringContainsString('no-store', (string) $response->headers->get('Cache-Control'));
        $response->assertSee($ownCopy->bibliographicRecord->title)->assertDontSee($otherCopy->bibliographicRecord->title);
        $response->assertDontSee('reader_profile_id')->assertDontSee('permissions');
    }

    public function test_profile_updates_only_permitted_fields_and_is_audited(): void
    {
        $this->signInToLibraryAs($this->reader)->patch('/dashboard/profile', [
            'phone' => '+7 701 111 22 33', 'additional_email' => 'reader.private@example.test',
            'locale' => 'kk', 'notification_preferences' => ['email' => '0'],
            'accessibility_preferences' => ['large_text' => '1'],
            'role' => 'admin', 'status' => 'blocked', 'name' => 'Forged name',
        ])->assertRedirect();

        $profile = ReaderProfile::forUser($this->reader)->fresh();
        $this->assertSame('+7 701 111 22 33', $profile->phone);
        $this->assertSame('active', $profile->status);
        $this->assertSame('reader', $this->reader->fresh()->role);
        $this->assertNotSame('Forged name', $this->reader->fresh()->name);
        ActivityLog::query()->where('action_type', 'profile.updated')->firstOrFail();
    }

    public function test_collection_crud_is_idempotent_and_does_not_delete_catalogue_books(): void
    {
        $response = $this->signInToLibraryAs($this->reader)->post('/dashboard/collections', ['title' => 'Экзамен']);
        $collection = LiteratureCollection::query()->where('user_id', (string) $this->reader->getKey())->firstOrFail();
        $response->assertRedirect(route('member.collections.show', $collection));
        $record = BibliographicRecord::factory()->create(['is_draft' => false]);

        $this->post(route('member.collections.items.add', $collection), ['bibliographic_record_id' => $record->getKey()])->assertRedirect();
        $this->post(route('member.collections.items.add', $collection), ['bibliographic_record_id' => $record->getKey()])->assertRedirect();
        $this->assertSame(1, $collection->items()->count());

        $this->delete(route('member.collections.destroy', $collection))->assertRedirect(route('member.collections.index'));
        $this->assertTrue(BibliographicRecord::query()->whereKey($record->getKey())->exists());
        ActivityLog::query()->where('action_type', 'collection.item_added')->firstOrFail();
        ActivityLog::query()->where('action_type', 'collection.deleted')->firstOrFail();
    }

    public function test_private_collection_is_a_safe_404_for_another_reader_and_xss_is_escaped(): void
    {
        $collection = LiteratureCollection::query()->create([
            'user_id' => (string) $this->reader->getKey(), 'created_by' => $this->reader->getKey(),
            'title' => '<script>alert(1)</script>', 'slug' => 'private-xss', 'collection_type' => 'personal',
            'visibility' => 'private', 'status' => 'published', 'owner_type' => 'reader',
        ]);
        $other = $this->makeControlPlaneUser('member');
        $this->signInToLibraryAs($other)->get(route('member.collections.show', $collection))->assertNotFound();
        $this->signInToLibraryAs($this->reader)->get(route('member.collections.show', $collection))
            ->assertOk()->assertSee('&lt;script&gt;alert(1)&lt;/script&gt;', false)->assertDontSee('<script>alert(1)</script>', false);
    }

    public function test_message_and_notification_idor_are_denied(): void
    {
        $other = $this->makeControlPlaneUser('member');
        $message = ContactMessage::query()->create(['category' => 'question', 'subject' => 'Private', 'body' => 'Secret', 'sender_id' => $other->getKey(), 'sender_email' => $other->email]);
        $notification = ReaderNotification::query()->create(['user_id' => $other->getKey(), 'event_type' => 'loan_due_soon', 'title' => 'Private']);

        $this->signInToLibraryAs($this->reader)->get(route('member.messages.show', $message))->assertNotFound();
        $this->post(route('member.notifications.read', $notification))->assertForbidden();
    }

    public function test_reader_notification_centre_does_not_expose_scientific_work_module(): void
    {
        ReaderNotification::query()->create([
            'user_id' => $this->reader->getKey(),
            'event_type' => 'repository_status_changed',
            'title' => 'Internal research workflow',
        ]);

        $this->signInToLibraryAs($this->reader)->get('/dashboard/notifications')
            ->assertOk()
            ->assertDontSee('Internal research workflow')
            ->assertDontSee('repository');
    }

    public function test_digital_page_uses_controlled_viewer_and_hides_restricted_material(): void
    {
        $record = BibliographicRecord::factory()->create();
        $allowed = ElectronicMaterial::query()->create(['bibliographic_record_id' => $record->getKey(), 'title' => 'Allowed PDF', 'file_path' => 'digital/allowed.pdf', 'file_type' => 'pdf', 'access_level' => 'authenticated', 'allow_download' => false, 'is_active' => true, 'workflow_status' => 'published']);
        ElectronicMaterial::query()->create(['bibliographic_record_id' => $record->getKey(), 'title' => 'Staff only', 'file_path' => 'digital/private.pdf', 'file_type' => 'pdf', 'access_level' => 'restricted', 'allow_download' => false, 'is_active' => true, 'workflow_status' => 'published']);

        $this->signInToLibraryAs($this->reader)->get('/dashboard/digital-materials')
            ->assertOk()->assertSee('Allowed PDF')->assertDontSee('Staff only')->assertSee('/digital-viewer/'.$allowed->getKey(), false)->assertDontSee('/storage/', false);
    }

    public function test_reader_card_has_real_codes_without_pii_and_cannot_be_selected_by_user_id(): void
    {
        $response = $this->signInToLibraryAs($this->reader)->get('/dashboard/card');
        $response->assertOk()->assertSee('<svg', false)->assertSee(ReaderProfile::forUser($this->reader)->barcode);
        $response->assertDontSee($this->reader->email, false)->assertDontSee('user_id', false);
        $this->get('/dashboard/card?user='.$this->adminUser->getKey())->assertOk()->assertDontSee($this->adminUser->name);
    }

    public function test_all_new_portal_translations_exist(): void
    {
        foreach (['ru', 'kk', 'en'] as $locale) {
            app()->setLocale($locale);
            foreach (['nav.profile', 'profile.title', 'card.title', 'digital.title', 'search.title', 'collections.title', 'restrictions.overdue'] as $suffix) {
                $key = 'librarian.member_portal.'.$suffix;
                $this->assertNotSame($key, __($key));
            }
        }
    }

    public function test_history_combines_closed_loans_and_reservations_without_staff_data(): void
    {
        $record = BibliographicRecord::factory()->create(['title' => 'Cancelled reservation title']);
        Reservation::factory()->create([
            'user_id' => $this->reader->getKey(),
            'bibliographic_record_id' => $record->getKey(),
            'status' => 'cancelled',
        ]);

        $this->signInToLibraryAs($this->reader)->get('/dashboard/history?from='.now()->subDay()->toDateString())
            ->assertOk()
            ->assertSee('Cancelled reservation title')
            ->assertDontSee('assigned_by')
            ->assertDontSee('internal_note');
    }

    public function test_dashboard_recommendations_are_available_explainable_and_preferences_apply(): void
    {
        $previous = BookCopy::factory()->create(['status' => 'available']);
        $previous->bibliographicRecord->update(['udc_code' => '004.4']);
        Loan::factory()->create([
            'user_id' => $this->reader->getKey(), 'copy_id' => $previous->getKey(),
            'status' => 'returned', 'returned_at' => now()->subDay(),
        ]);
        $candidate = BibliographicRecord::factory()->create(['title' => 'Explainable candidate', 'udc_code' => '004.7', 'is_draft' => false]);
        BookCopy::factory()->create(['bibliographic_record_id' => $candidate->getKey(), 'status' => 'available']);
        ReaderProfile::forUser($this->reader)->update(['accessibility_preferences' => ['large_text' => true]]);

        $this->signInToLibraryAs($this->reader)->get('/dashboard')
            ->assertOk()
            ->assertSee('Explainable candidate')
            ->assertSee('member-large-text', false);
    }

    public function test_member_search_flattens_the_catalogue_read_model_without_array_rendering_errors(): void
    {
        $record = BibliographicRecord::factory()->create([
            'title' => 'Nested search title',
            'primary_author' => 'Search Author',
            'isbn' => null,
        ]);
        BookCopy::factory()->create(['bibliographic_record_id' => $record->getKey(), 'status' => 'available']);

        $this->signInToLibraryAs($this->reader)->get('/dashboard/search?q=Nested')
            ->assertOk()
            ->assertSee('Nested search title')
            ->assertSee('Search Author')
            ->assertDontSee('htmlspecialchars');
    }
}
