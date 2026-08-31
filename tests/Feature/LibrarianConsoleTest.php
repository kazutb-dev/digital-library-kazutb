<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Catalog\BibliographicRecord;
use App\Models\Catalog\BookCopy;
use App\Models\Catalog\Fine;
use App\Models\Catalog\Loan;
use App\Models\Catalog\ReaderProfile;
use App\Models\Catalog\UdcCode;
use App\Models\User;
use App\Services\Catalog\CirculationService;
use App\Services\Catalog\DataQualityQueues;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

/**
 * Access boundaries and working pages of the librarian console (Master.md §22).
 */
class LibrarianConsoleTest extends TestCase
{
    use BuildsAdminControlPlane;

    private User $librarian;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        foreach ([
            'database/migrations/2026_08_28_100000_create_marc_recovery_model.php',
            'database/migrations/2026_08_28_100100_extend_catalogue_for_marc_recovery.php',
            'database/migrations/2026_08_29_120000_create_acquisition_batches_and_safe_number_sequences.php',
        ] as $migrationPath) {
            (require base_path($migrationPath))->up();
        }
        // Assertions in this acceptance scenario verify the Russian staff UI.
        // Keep the test user's locale explicit so middleware does not switch
        // the response back to the control-plane fixture default (Kazakh).
        $this->librarian = $this->makeControlPlaneUser('librarian', ['locale' => 'ru']);
    }

    /**
     * @return list<string>
     */
    private function consolePages(): array
    {
        return [
            '/librarian',
            '/librarian/catalog',
            '/librarian/catalog/create',
            '/librarian/udc-reference',
            '/librarian/copies',
            '/librarian/copies/create',
            '/librarian/circulation',
            '/librarian/circulation/issue',
            '/librarian/circulation/return',
            '/librarian/reservations',
            '/librarian/fines',
            '/librarian/data-cleanup',
            '/librarian/repository',
            '/librarian/news',
            '/librarian/reports',
            '/librarian/messages',
        ];
    }

    public function test_every_console_page_renders_for_a_librarian(): void
    {
        foreach ($this->consolePages() as $page) {
            $this->signInToLibraryAs($this->librarian)
                ->get($page)
                ->assertOk();
        }
    }

    public function test_members_are_forbidden_from_the_entire_console(): void
    {
        $member = $this->makeControlPlaneUser('member');

        foreach ($this->consolePages() as $page) {
            $this->signInToLibraryAs($member)
                ->get($page)
                ->assertForbidden();
        }
    }

    public function test_guests_are_redirected_to_login(): void
    {
        foreach (['/librarian', '/librarian/catalog'] as $page) {
            $response = $this->get($page);
            $response->assertRedirectContains('/login');
        }
    }

    public function test_librarian_cannot_delete_a_bibliographic_record(): void
    {
        // Historical §5.5: deleting a record is an admin-only capability.
        $record = BibliographicRecord::factory()->create();

        $this->signInToLibraryAs($this->librarian)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->delete(route('librarian.catalog.destroy', $record), ['reason' => 'Duplicate of another record'])
            ->assertForbidden();

        $this->assertNotSoftDeleted($record);
    }

    public function test_admin_can_delete_a_record_without_copies_and_it_is_audited(): void
    {
        $record = BibliographicRecord::factory()->create();

        $this->signInToLibraryAs($this->adminUser)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->delete(route('librarian.catalog.destroy', $record), ['reason' => 'Erroneously created during import'])
            ->assertRedirect(route('librarian.catalog.index'));

        $this->assertSoftDeleted($record);
        ActivityLog::query()
            ->where('action_type', 'delete')
            ->where('entity_type', 'bibliographic_record')
            ->firstOrFail();
    }

    public function test_a_record_with_live_copies_cannot_be_deleted(): void
    {
        $record = BibliographicRecord::factory()->create();
        BookCopy::factory()->create(['bibliographic_record_id' => $record->getKey(), 'status' => 'available']);

        $this->signInToLibraryAs($this->adminUser)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->delete(route('librarian.catalog.destroy', $record), ['reason' => 'Trying to delete a record that still has copies'])
            ->assertSessionHasErrors('reason');

        $this->assertNotSoftDeleted($record);
    }

    public function test_creating_a_record_without_required_fields_saves_it_as_a_draft(): void
    {
        $this->signInToLibraryAs($this->librarian)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.catalog.store'), [
                'title' => 'Черновик без обязательных полей',
                'language' => 'ru',
                'resource_type' => 'book',
            ])
            ->assertRedirect();

        $record = BibliographicRecord::query()->where('title', 'Черновик без обязательных полей')->firstOrFail();
        $this->assertTrue($record->is_draft, 'An incomplete record must land in the Data Cleanup queue as a draft.');
        $this->assertContains('primary_author', $record->missingRequiredFields());
    }

    public function test_a_complete_record_is_not_a_draft_and_is_audited(): void
    {
        $this->signInToLibraryAs($this->librarian)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.catalog.store'), [
                'title' => 'Полная библиографическая запись',
                'primary_author' => 'Тестов Т.Т.',
                'publisher' => 'Издательство КазУТБ',
                'publication_year' => 2025,
                'language' => 'ru',
                'udc_code' => '004',
                'annotation' => 'Аннотация для полной записи каталога.',
                'resource_type' => 'book',
            ])
            ->assertRedirect();

        $record = BibliographicRecord::query()->where('title', 'Полная библиографическая запись')->firstOrFail();
        $this->assertFalse($record->is_draft);
        $this->assertSame([], $record->missingRequiredFields());

        ActivityLog::query()
            ->where('action_type', 'metadata.create')
            ->where('entity_id', (string) $record->getKey())
            ->firstOrFail();
    }

    public function test_cataloguer_can_verify_an_imported_udc_description(): void
    {
        $code = UdcCode::query()->create([
            'code' => '004.92',
            'description' => 'Раздел 004.92',
            'is_verified' => false,
        ]);

        $this->signInToLibraryAs($this->librarian)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->patch(route('librarian.udc-reference.update', $code), [
                'description' => 'Системный анализ',
                'is_verified' => '1',
            ])
            ->assertRedirect();

        $this->assertDatabaseHas('udc_codes', [
            'id' => $code->getKey(),
            'description' => 'Системный анализ',
            'is_verified' => true,
        ]);
    }

    public function test_a_likely_duplicate_warns_before_saving(): void
    {
        BibliographicRecord::factory()->create([
            'title' => 'Основы библиотековедения',
            'primary_author' => 'Иванов И.И.',
            'publication_year' => 2020,
        ]);

        $this->signInToLibraryAs($this->librarian)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.catalog.store'), [
                'title' => 'Основы библиотековедения',
                'primary_author' => 'Иванов И.И.',
                'publication_year' => 2020,
                'language' => 'ru',
                'resource_type' => 'book',
            ])
            ->assertSessionHas('duplicate_warning');

        $this->assertSame(1, BibliographicRecord::query()->where('title', 'Основы библиотековедения')->count());
    }

    public function test_confirming_a_duplicate_creates_the_second_record(): void
    {
        BibliographicRecord::factory()->create([
            'title' => 'Основы библиотековедения',
            'primary_author' => 'Иванов И.И.',
            'publication_year' => 2020,
        ]);

        $this->signInToLibraryAs($this->librarian)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.catalog.store'), [
                'title' => 'Основы библиотековедения',
                'primary_author' => 'Иванов И.И.',
                'publication_year' => 2020,
                'language' => 'ru',
                'resource_type' => 'book',
                'confirmed_duplicate' => '1',
            ])
            ->assertRedirect();

        $this->assertSame(2, BibliographicRecord::query()->where('title', 'Основы библиотековедения')->count());
    }

    public function test_bulk_copy_intake_generates_sequential_inventory_numbers(): void
    {
        $record = BibliographicRecord::factory()->create();

        $this->signInToLibraryAs($this->librarian)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.copies.store'), [
                'bibliographic_record_id' => $record->getKey(),
                'quantity' => 5,
                'inventory_number' => 'INV-900010',
                'barcode' => 'BC90001000',
                'condition' => 'new',
                'access_restriction' => 'free',
            ])
            ->assertRedirect();

        $copies = BookCopy::query()->where('bibliographic_record_id', $record->getKey())->orderBy('inventory_number')->get();
        $this->assertCount(5, $copies);
        $this->assertSame(
            ['INV-900010', 'INV-900011', 'INV-900012', 'INV-900013', 'INV-900014'],
            $copies->pluck('inventory_number')->all(),
        );
        $this->assertSame(5, $copies->where('status', 'available')->count());
    }

    public function test_copy_card_contains_all_eight_dir_sections_and_real_copy_fields(): void
    {
        $copy = BookCopy::factory()->create([
            'inventory_number' => 'DIR-INV-7001',
            'barcode' => 'DIR-BC-7001',
            'ksu_number' => 'КСУ-17',
            'supplier_name' => 'ТОО Поставщик',
        ]);

        $this->signInToLibraryAs($this->librarian)
            ->get(route('librarian.copies.show', $copy))
            ->assertOk()
            ->assertSee('1. Идентификация экземпляра')
            ->assertSee('2. Местоположение')
            ->assertSee('3. Статус экземпляра')
            ->assertSee('4. История использования')
            ->assertSee('5. Сроки и ограничения')
            ->assertSee('6. Физическое состояние')
            ->assertSee('7. Учёт и стоимость')
            ->assertSee('8. Библиографическая привязка')
            ->assertSee('DIR-INV-7001')
            ->assertSee('DIR-BC-7001')
            ->assertSee('КСУ-17')
            ->assertSee('ТОО Поставщик');
    }

    public function test_duplicate_inventory_numbers_are_rejected(): void
    {
        $record = BibliographicRecord::factory()->create();
        BookCopy::factory()->create(['inventory_number' => 'INV-777000']);

        $this->signInToLibraryAs($this->librarian)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.copies.store'), [
                'bibliographic_record_id' => $record->getKey(),
                'quantity' => 1,
                'inventory_number' => 'INV-777000',
                'condition' => 'new',
                'access_restriction' => 'free',
            ])
            ->assertSessionHasErrors('inventory_number');
    }

    public function test_writing_off_a_copy_requires_a_comment_and_is_audited(): void
    {
        $copy = BookCopy::factory()->create(['status' => 'available']);
        $senior = $this->makeControlPlaneUser('senior_librarian', ['locale' => 'ru']);
        $writeOff = [
            'action' => 'write_off',
            'writeoff_date' => now()->toDateString(),
            'writeoff_act' => 'ACT-CONSOLE-001',
            'writeoff_reason' => 'Физический износ, восстановление невозможно',
        ];

        $this->signInToLibraryAs($senior)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.copies.status', $copy), $writeOff)
            ->assertSessionHasErrors('comment');

        $this->signInToLibraryAs($senior)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.copies.status', $copy), [
                ...$writeOff,
                'comment' => 'Физический износ, восстановление невозможно',
            ])
            ->assertRedirect();

        $this->assertSame('written_off', $copy->fresh()->status);
        ActivityLog::query()
            ->where('action_type', 'copies.status_change')
            ->where('entity_id', (string) $copy->getKey())
            ->firstOrFail();
    }

    public function test_a_copy_on_loan_cannot_change_status(): void
    {
        $copy = BookCopy::factory()->create(['status' => 'available']);
        $reader = $this->makeControlPlaneUser('member');
        $senior = $this->makeControlPlaneUser('senior_librarian', ['locale' => 'ru']);
        ReaderProfile::forUser($reader);
        app(CirculationService::class)->issue($reader, $copy, $this->librarian);

        $this->signInToLibraryAs($senior)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.copies.status', $copy), [
                'action' => 'write_off',
                'comment' => 'Попытка списать выданный экземпляр',
                'writeoff_date' => now()->toDateString(),
                'writeoff_act' => 'ACT-CONSOLE-LOAN-001',
                'writeoff_reason' => 'Экземпляр признан непригодным к использованию',
            ])
            ->assertSessionHasErrors('action');

        $this->assertSame('issued', $copy->fresh()->status);
    }

    public function test_waiving_a_fine_requires_a_reason_and_is_audited(): void
    {
        $reader = $this->makeControlPlaneUser('member');
        $fine = Fine::factory()->create(['user_id' => $reader->getKey(), 'amount' => 500]);

        $this->signInToLibraryAs($this->librarian)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.fines.resolve', $fine), ['action' => 'waived'])
            ->assertSessionHasErrors('reason');

        $this->signInToLibraryAs($this->librarian)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.fines.resolve', $fine), [
                'action' => 'waived',
                'reason' => 'Списано по решению заведующего отделом обслуживания',
            ])
            ->assertRedirect();

        $this->assertSame('waived', $fine->fresh()->status);
        ActivityLog::query()->where('action_type', 'fines.waived')->firstOrFail();
    }

    public function test_the_overview_reports_real_aggregates(): void
    {
        BibliographicRecord::factory()->draft()->count(3)->create();

        $response = $this->signInToLibraryAs($this->librarian)->get('/librarian');

        $response->assertOk();
        // The old mockup's invented figures must be gone for good.
        $response->assertDontSee('Batch #8492');
        $response->assertDontSee('Merge Conflicts');
        $response->assertDontSee('Good morning');
        $response->assertSee((string) __('librarian.overview.metrics.draft_records'));
    }

    public function test_data_cleanup_surfaces_real_anomaly_queues(): void
    {
        BibliographicRecord::factory()->draft()->count(2)->create();

        $response = $this->signInToLibraryAs($this->librarian)->get('/librarian/data-cleanup?issue=drafts');

        $response->assertOk();
        $response->assertDontSee('B-8921');
        $response->assertDontSee('Critical Anomalies');
        $response->assertSee((string) __('librarian.data_cleanup.issues.drafts'));
    }

    public function test_unplaced_copy_queue_counts_blank_shelves_and_links_directly_to_edit(): void
    {
        $branch = Branch::query()->firstOrFail();
        $copy = BookCopy::factory()->create([
            'branch_id' => $branch->getKey(),
            'shelf_location' => '   ',
        ]);
        BookCopy::factory()->create([
            'branch_id' => $branch->getKey(),
            'shelf_location' => 'A-12',
        ]);

        $this->assertSame(1, app(DataQualityQueues::class)->counts()['unplaced_copies']);

        $this->signInToLibraryAs($this->librarian)
            ->get(route('librarian.data-cleanup', ['issue' => 'unplaced_copies']))
            ->assertOk()
            ->assertSee($copy->inventory_number)
            ->assertSee(route('librarian.copies.edit', $copy), false)
            ->assertSee(__('librarian.data_cleanup.edit_copy_location'));
    }

    public function test_copy_and_reader_lookup_endpoints_answer_the_desk(): void
    {
        $copy = BookCopy::factory()->create(['barcode' => 'BC55550001', 'status' => 'available']);
        $reader = $this->makeControlPlaneUser('member', ['name' => 'Лукпанова Асель']);
        ReaderProfile::forUser($reader);

        $copyPayload = $this->signInToLibraryAs($this->librarian)
            ->getJson(route('librarian.circulation.copy-lookup', ['q' => 'BC55550001']))
            ->assertOk()
            ->json();
        $this->assertSame($copy->inventory_number, $copyPayload['data']['inventory_number']);

        $readerPayload = $this->signInToLibraryAs($this->librarian)
            ->getJson(route('librarian.circulation.reader-lookup', ['q' => 'Лукпанова']))
            ->assertOk()
            ->json();
        $this->assertNotEmpty($readerPayload['data']);
        $this->assertSame('Лукпанова Асель', $readerPayload['data'][0]['name']);
    }

    public function test_isbn_lookup_explains_that_a_specific_physical_copy_is_required(): void
    {
        $record = BibliographicRecord::factory()->create([
            'title' => 'Русско-казахский словарь',
            'isbn' => '9965-17-469-5',
        ]);
        BookCopy::factory()->count(2)->create([
            'bibliographic_record_id' => $record->getKey(),
            'status' => 'available',
            'barcode' => null,
        ]);

        $payload = $this->signInToLibraryAs($this->librarian)
            ->getJson(route('librarian.circulation.copy-lookup', ['q' => '9965174695']))
            ->assertOk()
            ->json();

        $this->assertNull($payload['data']);
        $this->assertSame('isbn', $payload['match_type']);
        $this->assertSame($record->getKey(), $payload['editions'][0]['id']);
        $this->assertSame(2, $payload['editions'][0]['available_copies_count']);

        $this->signInToLibraryAs($this->librarian)
            ->get('/librarian/copies?search=9965-17-469-5&lang=ru')
            ->assertOk()
            ->assertSee('Русско-казахский словарь');
    }

    public function test_issue_post_never_chooses_an_arbitrary_copy_from_an_isbn(): void
    {
        $record = BibliographicRecord::factory()->create(['isbn' => '9965-17-469-5']);
        BookCopy::factory()->count(2)->create([
            'bibliographic_record_id' => $record->getKey(),
            'status' => 'available',
        ]);
        $reader = $this->makeControlPlaneUser('member');
        ReaderProfile::forUser($reader);

        $this->signInToLibraryAs($this->librarian)
            ->withoutMiddleware(PreventRequestForgery::class)
            ->post(route('librarian.circulation.issue.store'), [
                'reader_id' => $reader->getKey(),
                'copy_code' => '9965-17-469-5',
            ])
            ->assertSessionHasErrors('copy_code');

        $this->assertSame(0, Loan::query()->count());
    }

    public function test_unknown_copy_lookup_returns_null_rather_than_an_error(): void
    {
        $payload = $this->signInToLibraryAs($this->librarian)
            ->getJson(route('librarian.circulation.copy-lookup', ['q' => 'NO-SUCH-BARCODE']))
            ->assertOk()
            ->json();

        $this->assertNull($payload['data']);
    }
}
