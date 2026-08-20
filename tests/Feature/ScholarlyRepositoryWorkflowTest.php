<?php

namespace Tests\Feature;

use App\Console\Commands\SweepDigitalLibraryServices;
use App\Models\Catalog\RepositoryApproval;
use App\Models\Catalog\RepositoryAuthor;
use App\Models\Catalog\RepositoryItem;
use App\Models\Catalog\RepositoryItemVersion;
use App\Models\News;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\Repository\RepositoryUsageRecorder;
use App\Services\Repository\RepositoryWorkflow;
use Database\Seeders\PermissionSeeder;
use Database\Seeders\RepositorySeeder;
use Database\Seeders\RoleSeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Illuminate\Foundation\Http\Middleware\VerifyCsrfToken;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Spatie\Permission\PermissionRegistrar;
use Tests\TestCase;

class ScholarlyRepositoryWorkflowTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        config([
            'app.locale' => 'kk',
            'app.fallback_locale' => 'kk',
            'cache.default' => 'array',
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => ':memory:',
            'permission.testing' => false,
            'session.driver' => 'array',
        ]);
        DB::purge();
        DB::setDefaultConnection('sqlite');
        DB::reconnect('sqlite');
        Storage::fake('local');
        $this->withoutMiddleware([VerifyCsrfToken::class, ValidateCsrfToken::class]);

        $this->createSqliteSchema();
        app(PermissionSeeder::class)->run();
        app(RoleSeeder::class)->run();
        app(PermissionRegistrar::class)->forgetCachedPermissions();
    }

    public function test_only_an_authorised_library_employee_can_create_an_intake_record(): void
    {
        $librarian = $this->makeUser('librarian');
        $director = $this->makeUser('director');
        $admin = $this->makeUser('admin');
        $member = $this->makeUser('member');

        $this->assertTrue($librarian->can('repository.upload'));
        $this->assertFalse($director->can('repository.upload'));
        $this->assertFalse($admin->can('repository.upload'));
        $this->assertFalse($admin->can('repository.approve'));
        $this->assertFalse($admin->can('repository.publish'));

        $payload = [
            'title' => 'Тестовая научная работа',
            'authors' => 'Автор Тестов',
            'work_type' => 'scientific_article',
            'language' => 'ru',
            'access_policy' => 'full_public',
            'copyright_status' => 'unknown',
        ];

        $librarianPayload = $payload + [
            'file' => UploadedFile::fake()->createWithContent('work.pdf', "%PDF-1.4\n% intake test\n%%EOF\n"),
        ];
        $this->signInAs($librarian)->post('/librarian/repository', $librarianPayload)->assertRedirect();
        $this->assertDatabaseHas('repository_items', [
            'title' => 'Тестовая научная работа',
            'uploaded_by' => $librarian->getKey(),
            'status' => 'draft',
            'access_policy' => 'full_public',
        ]);
        $created = RepositoryItem::query()->sole();
        $this->assertTrue($created->hasPublishablePdf());
        Storage::disk('local')->assertExists($created->file_path);
        $this->assertSame(1, $created->versions()->count());

        // A stale or manually assigned permission is not an undocumented
        // administrator override: the repository policy still owns the
        // editorial boundary.
        $admin->givePermissionTo([
            'repository.upload',
            'repository.create',
            'repository.approve',
            'repository.publish',
            'repository.review_metadata',
        ]);
        $this->assertTrue($admin->can('repository.upload'));
        $this->assertFalse($admin->can('create', RepositoryItem::class));
        $this->assertFalse($admin->can('approve', $created));
        $this->assertFalse($admin->can('publish', $created));

        $this->signInAs($director)->post('/librarian/repository', $payload)->assertForbidden();
        $this->signInAs($admin)->post('/librarian/repository', $payload)->assertForbidden();
        $this->signInAs($member)->post('/librarian/repository', $payload)->assertForbidden();
        $this->assertSame(1, RepositoryItem::query()->count());
    }

    public function test_quality_review_handoff_and_director_only_approval_are_audited(): void
    {
        $librarian = $this->makeUser('librarian');
        $director = $this->makeUser('director');
        $admin = $this->makeUser('admin');
        $workflow = app(RepositoryWorkflow::class);
        $item = $this->readyItem($librarian, ['status' => 'quality_review']);

        $workflow->transition($item, 'pending_approval', $librarian, 'Проверка качества завершена.');
        $this->assertSame('pending_approval', $item->refresh()->status);
        $this->assertSame($librarian->getKey(), $item->reviewed_by);

        try {
            $workflow->transition($item, 'approved', $admin);
            $this->fail('A system administrator must not business-approve repository content.');
        } catch (AuthorizationException) {
            $this->assertSame('pending_approval', $item->refresh()->status);
        }

        $workflow->transition($item, 'approved', $director, 'Утверждено руководителем.');
        $this->assertSame('approved', $item->refresh()->status);
        $this->assertSame($director->getKey(), $item->approved_by);

        $this->assertDatabaseHas('repository_reviews', [
            'repository_item_id' => $item->getKey(),
            'decision' => 'pending_approval',
            'reviewer_id' => $librarian->getKey(),
        ]);
        $this->assertDatabaseHas('activity_logs', [
            'action_type' => 'repository.approved',
            'entity_id' => (string) $item->getKey(),
            'actor_id' => $director->getKey(),
        ]);

        $returned = $this->readyItem($librarian, ['status' => 'pending_approval']);
        $workflow->transition($returned, 'changes_requested', $director, 'Уточнить данные о правах.');
        $this->assertSame('changes_requested', $returned->refresh()->status);
    }

    public function test_uploader_cannot_self_approve_even_when_the_uploader_is_a_director(): void
    {
        $director = $this->makeUser('director');
        $item = $this->readyItem($director, ['status' => 'pending_approval']);

        $this->expectException(AuthorizationException::class);
        app(RepositoryWorkflow::class)->transition($item, 'approved', $director);
    }

    public function test_publication_requires_director_approval_and_a_pdf_while_file_policy_stays_independent(): void
    {
        $librarian = $this->makeUser('librarian');
        $director = $this->makeUser('director');
        $admin = $this->makeUser('admin');
        $workflow = app(RepositoryWorkflow::class);

        $legacyApproval = $this->readyItem($librarian, [
            'status' => 'approved',
            'approved_by' => $admin->getKey(),
        ]);
        $this->assertTransitionError(
            fn () => $workflow->transition($legacyApproval, 'published', $director),
            'status',
        );

        $missingPdf = $this->readyItem($librarian, [
            'status' => 'approved',
            'approved_by' => $director->getKey(),
            'file_path' => null,
            'file_name' => null,
        ]);
        $this->assertTransitionError(
            fn () => $workflow->transition($missingPdf, 'published', $director),
            'file',
        );

        $invalidPath = 'repository/tests/not-really-a-pdf.pdf';
        Storage::disk('local')->put($invalidPath, 'plain text with a misleading extension');
        $invalidPdf = $this->readyItem($librarian, [
            'status' => 'approved',
            'approved_by' => $director->getKey(),
            'file_path' => $invalidPath,
            'file_name' => 'not-really-a-pdf.pdf',
        ]);
        $this->assertTransitionError(
            fn () => $workflow->transition($invalidPdf, 'published', $director),
            'file',
        );

        $restricted = $this->readyItem($librarian, [
            'status' => 'approved',
            'approved_by' => $director->getKey(),
            'access_policy' => 'restricted',
        ]);
        $workflow->transition($restricted, 'published', $director);
        $this->assertSame('published', $restricted->refresh()->status);
        $this->assertFalse($restricted->canExposeFullText());

        $publishable = $this->readyItem($librarian, [
            'status' => 'approved',
            'approved_by' => $director->getKey(),
        ]);
        $workflow->transition($publishable, 'published', $director);

        $this->assertSame('published', $publishable->refresh()->status);
        $this->assertNotNull($publishable->published_at);
    }

    public function test_scheduler_rechecks_release_readiness_for_scheduled_and_expired_embargo_records(): void
    {
        $librarian = $this->makeUser('librarian');
        $director = $this->makeUser('director');
        $admin = $this->makeUser('admin');

        $scheduledReady = $this->readyItem($librarian, [
            'status' => 'scheduled',
            'scheduled_for' => now()->subMinute(),
            'approved_by' => $director->getKey(),
        ]);
        $scheduledInvalid = $this->readyItem($librarian, [
            'status' => 'scheduled',
            'scheduled_for' => now()->subMinute(),
            'approved_by' => $admin->getKey(),
        ]);
        $expiredEmbargoReady = $this->readyItem($librarian, [
            'status' => 'embargoed',
            'embargo_until' => now()->subMinute(),
            'post_embargo_access_policy' => 'full_public',
            'approved_by' => $director->getKey(),
        ]);
        $expiredEmbargoRestricted = $this->readyItem($librarian, [
            'status' => 'embargoed',
            'embargo_until' => now()->subMinute(),
            'approved_by' => $director->getKey(),
            'access_policy' => 'restricted',
            'post_embargo_access_policy' => 'restricted',
        ]);

        $method = new \ReflectionMethod(SweepDigitalLibraryServices::class, 'sweepRepository');
        $method->invoke(app(SweepDigitalLibraryServices::class), app(AuditLogger::class));

        $this->assertSame('published', $scheduledReady->refresh()->status);
        $this->assertSame('scheduled', $scheduledInvalid->refresh()->status);
        $this->assertSame('published', $expiredEmbargoReady->refresh()->status);
        $this->assertSame('published', $expiredEmbargoRestricted->refresh()->status);
        $this->assertFalse($expiredEmbargoRestricted->canExposeFullText());
    }

    public function test_responsible_reviewer_and_director_can_stream_private_pdf_but_admin_cannot_enter_workflow(): void
    {
        $librarian = $this->makeUser('librarian');
        $director = $this->makeUser('director');
        $admin = $this->makeUser('admin');
        $item = $this->readyItem($librarian, ['status' => 'pending_approval']);

        $this->signInAs($librarian)->get(route('librarian.repository'))->assertOk();
        $this->signInAs($librarian)->get(route('librarian.repository.edit', $item))->assertOk();
        $this->signInAs($librarian)->get(route('librarian.repository.file', $item))->assertOk();
        $this->signInAs($director)->get(route('librarian.repository.edit', $item))->assertOk();
        $this->signInAs($director)->get(route('librarian.repository.file', $item))->assertOk();
        $this->signInAs($admin)->get(route('librarian.repository.file', $item))->assertForbidden();
    }

    public function test_pdf_replacements_create_an_auditable_version_history(): void
    {
        $librarian = $this->makeUser('librarian');
        $base = [
            'title' => 'Версионируемая работа',
            'authors' => 'Автор Версий',
            'work_type' => 'research_report',
            'language' => 'ru',
            'access_policy' => 'full_public',
            'copyright_status' => 'unknown',
        ];

        $this->signInAs($librarian)->post(route('librarian.repository.store'), $base + [
            'file' => UploadedFile::fake()->createWithContent('version-1.pdf', "%PDF-1.4\n% first version\n%%EOF\n"),
        ])->assertRedirect();

        $item = RepositoryItem::query()->sole();
        $firstPath = $item->file_path;
        $this->assertSame(1, $item->version_number);

        $this->signInAs($librarian)->patch(route('librarian.repository.update', $item), $base + [
            'file' => UploadedFile::fake()->createWithContent('version-2.pdf', "%PDF-1.4\n% corrected second version\n%%EOF\n"),
            'version_reason' => 'Исправлены метаданные внутри PDF.',
        ])->assertRedirect();

        $item->refresh();
        $this->assertSame(2, $item->version_number);
        $this->assertNotSame($firstPath, $item->file_path);
        Storage::disk('local')->assertExists($firstPath);
        Storage::disk('local')->assertExists($item->file_path);
        $this->assertDatabaseHas('repository_item_versions', [
            'repository_item_id' => $item->getKey(),
            'version_number' => 1,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('repository_item_versions', [
            'repository_item_id' => $item->getKey(),
            'version_number' => 2,
            'file_name' => 'version-2.pdf',
            'change_reason' => 'Исправлены метаданные внутри PDF.',
            'is_active' => true,
        ]);
        $this->signInAs($librarian)
            ->get(route('librarian.repository.edit', $item))
            ->assertOk()
            ->assertSee('repository-version-history', false)
            ->assertSee('version-2.pdf');
    }

    public function test_director_withdrawal_keeps_a_public_tombstone_and_permanently_closes_the_file(): void
    {
        $librarian = $this->makeUser('librarian');
        $director = $this->makeUser('director');
        $item = $this->readyItem($librarian, [
            'title' => 'Работа для отзыва',
            'status' => 'published',
            'approved_by' => $director->getKey(),
            'published_at' => now()->subDay(),
        ]);

        app(RepositoryWorkflow::class)->transition($item, 'withdrawn', $director, 'Подтверждённая причина отзыва.');
        $item->refresh();

        $this->assertSame('withdrawn', $item->status);
        $this->assertSame($director->getKey(), $item->withdrawn_by);
        $this->assertSame('Подтверждённая причина отзыва.', $item->withdrawal_reason);
        $this->assertNotNull($item->withdrawn_at);
        $this->assertDatabaseHas('repository_reviews', [
            'repository_item_id' => $item->getKey(),
            'decision' => 'withdrawn',
            'reviewer_id' => $director->getKey(),
        ]);

        $this->get(route('repository.show', $item))
            ->assertOk()
            ->assertSee('repository-withdrawal-tombstone', false);
        $this->get(route('repository.download', $item))->assertNotFound();
        $this->get(route('repository.view', $item))->assertNotFound();
    }

    public function test_public_metadata_file_policies_tombstones_filters_and_range_viewer_are_enforced(): void
    {
        $librarian = $this->makeUser('librarian');
        $director = $this->makeUser('director');
        $member = $this->makeUser('member');
        $public = $this->readyItem($librarian, [
            'title' => 'Открытая демо-работа',
            'status' => 'published',
            'approved_by' => $director->getKey(),
            'published_at' => now(),
        ]);
        $notDirectorApproved = $this->readyItem($librarian, [
            'title' => 'Работа без утверждения директором',
            'status' => 'published',
            'approved_by' => $librarian->getKey(),
            'published_at' => now(),
        ]);
        $authenticated = $this->readyItem($librarian, [
            'title' => 'Работа после входа',
            'status' => 'published',
            'access_policy' => 'authenticated',
            'approved_by' => $director->getKey(),
            'published_at' => now(),
        ]);
        $metadataOnly = $this->readyItem($librarian, [
            'title' => 'Только публичные метаданные',
            'status' => 'published',
            'access_policy' => 'metadata_public',
            'approved_by' => $director->getKey(),
            'published_at' => now(),
        ]);
        $restricted = $this->readyItem($librarian, [
            'title' => 'Закрытая работа',
            'status' => 'published',
            'access_policy' => 'restricted',
            'approved_by' => $director->getKey(),
            'published_at' => now(),
        ]);
        $embargo = $this->readyItem($librarian, [
            'title' => 'Работа под эмбарго',
            'status' => 'embargoed',
            'embargo_until' => now()->addMonth(),
            'post_embargo_access_policy' => 'full_public',
            'approved_by' => $director->getKey(),
        ]);
        $withdrawn = $this->readyItem($librarian, [
            'title' => 'Отозванная работа',
            'status' => 'withdrawn',
            'approved_by' => $director->getKey(),
            'withdrawn_by' => $director->getKey(),
            'withdrawn_at' => now(),
            'withdrawal_reason' => 'Исправление научной записи.',
            'published_at' => now()->subDay(),
        ]);
        $legacyAbstract = $this->readyItem($librarian, [
            'title' => 'Автореферат с прежним кодом',
            'work_type' => 'abstract_of_thesis',
            'status' => 'published',
            'approved_by' => $director->getKey(),
            'published_at' => now(),
        ]);
        $this->assertSame('thesis_abstract', $legacyAbstract->work_type);
        $draft = $this->readyItem($librarian, [
            'title' => 'Черновик работы',
            'status' => 'draft',
        ]);

        $this->get('/repository?lang=ru')
            ->assertOk()
            ->assertSee('Открытая демо-работа', false)
            ->assertSee('Работа после входа', false)
            ->assertSee('Только публичные метаданные', false)
            ->assertSee('Закрытая работа', false)
            ->assertSee('Работа под эмбарго', false)
            ->assertSee('Отозванная работа', false)
            ->assertDontSee('Работа без утверждения директором', false)
            ->assertDontSee('Черновик работы', false);

        $this->get('/repository?lang=ru&access=authenticated')->assertOk()->assertSee('Работа после входа')->assertDontSee('Открытая демо-работа');
        $this->get('/repository?lang=ru&access=metadata_public')->assertOk()->assertSee('Только публичные метаданные');
        $this->get('/repository?lang=ru&work_type=abstract_of_thesis')->assertOk()->assertSee('Автореферат с прежним кодом');
        $this->get('/repository?lang=ru&q='.urlencode('Закрытая работа'))->assertOk()->assertSee('Закрытая работа')->assertDontSee('Открытая демо-работа');

        $this->get(route('repository.show', $public))
            ->assertOk()
            ->assertSee('repository-detail-citation', false)
            ->assertSee(route('repository.show', $public), false)
            ->assertSee('/repository/'.$public->getKey().'/view', false);
        $this->get(route('repository.download', $public))->assertOk();
        $this->withHeader('Range', 'bytes=0-9')
            ->get(route('repository.view', $public))
            ->assertStatus(206)
            ->assertHeader('Accept-Ranges', 'bytes')
            ->assertHeader('Content-Range', 'bytes 0-9/'.$public->file_size);

        foreach ([$authenticated, $metadataOnly, $restricted, $embargo] as $metadataVisible) {
            $this->get(route('repository.show', $metadataVisible))->assertOk();
        }
        $this->get(route('repository.download', $authenticated))->assertForbidden();
        $this->signInAs($member)->get(route('repository.download', $authenticated))->assertOk();
        $this->actingAs($member)->get(route('repository.download', $metadataOnly))->assertForbidden();
        $this->actingAs($member)->get(route('repository.download', $restricted))->assertForbidden();
        $this->actingAs($member)->get(route('repository.download', $embargo))->assertForbidden();

        $this->get(route('repository.show', $withdrawn))
            ->assertOk()
            ->assertSee('repository-withdrawal-tombstone', false)
            ->assertSee('Исправление научной записи.');
        $this->get(route('repository.download', $withdrawn))->assertNotFound();
        $this->get(route('repository.view', $withdrawn))->assertNotFound();

        foreach ([$notDirectorApproved, $draft] as $hidden) {
            $this->get(route('repository.show', $hidden))->assertNotFound();
            $this->get(route('repository.download', $hidden))->assertNotFound();
        }

        $this->assertDatabaseHas('repository_usage_daily', [
            'repository_item_id' => $public->getKey(),
            'event_type' => 'metadata_view',
            'role_name' => 'guest',
            'locale' => 'ru',
            'event_count' => 1,
        ]);
        $this->assertDatabaseHas('repository_usage_daily', [
            'repository_item_id' => $public->getKey(),
            'event_type' => 'pdf_view',
            'role_name' => 'guest',
            'event_count' => 1,
        ]);
        $this->assertDatabaseHas('repository_usage_daily', [
            'repository_item_id' => $authenticated->getKey(),
            'event_type' => 'download',
            'role_name' => 'member',
            'event_count' => 1,
        ]);
        $this->assertFalse(Schema::hasTable('repository_access_events'));
        foreach (['user_id', 'created_at', 'updated_at', 'ip_address', 'user_agent', 'referrer', 'search_query'] as $behaviouralField) {
            $this->assertFalse(Schema::hasColumn('repository_usage_daily', $behaviouralField));
        }
    }

    public function test_public_repository_facets_are_exact_localised_and_never_escape_public_metadata(): void
    {
        $librarian = $this->makeUser('librarian');
        $director = $this->makeUser('director');
        $target = $this->readyItem($librarian, [
            'title' => 'Целевая фасетная работа',
            'authors' => ['Legacy Facet Author'],
            'work_type' => 'master_thesis',
            'year' => 2026,
            'language' => 'kk',
            'faculty' => 'Факультет инженерии',
            'department' => 'Кафедра ИТ',
            'educational_programme' => 'Информационные системы',
            'supervisor' => 'Профессор Тестов',
            'udc_code' => '004.8',
            'status' => 'published',
            'access_policy' => 'full_public',
            'approved_by' => $director->getKey(),
            'published_at' => now(),
        ]);
        RepositoryAuthor::query()->create([
            'repository_item_id' => $target->getKey(),
            'display_name' => 'Айдана Серикова',
            'normalised_name' => 'айдана серикова',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $other = $this->readyItem($librarian, [
            'title' => 'Другая публичная работа',
            'authors' => ['Другой Автор'],
            'work_type' => 'scientific_article',
            'year' => 2025,
            'language' => 'ru',
            'faculty' => 'Факультет экономики',
            'department' => 'Кафедра экономики',
            'educational_programme' => 'Экономика',
            'supervisor' => 'Другой Руководитель',
            'udc_code' => '330',
            'status' => 'published',
            'approved_by' => $director->getKey(),
            'published_at' => now()->subDay(),
        ]);
        $embargoed = $this->readyItem($librarian, [
            'title' => 'Ещё не открытая работа под эмбарго',
            'authors' => ['Автор Под Эмбарго'],
            'status' => 'embargoed',
            'access_policy' => 'full_public',
            'post_embargo_access_policy' => 'full_public',
            'embargo_until' => now()->addMonth(),
            'approved_by' => $director->getKey(),
        ]);
        $hidden = $this->readyItem($librarian, [
            'title' => 'Непубличная внутренняя работа',
            'authors' => ['Скрытый Автор'],
            'faculty' => 'Секретный факультет',
            'internal_review_notes' => 'INTERNAL-FACET-DECISION',
            'status' => 'draft',
        ]);
        RepositoryAuthor::query()->create([
            'repository_item_id' => $hidden->getKey(),
            'display_name' => 'Скрытый Нормализованный Автор',
            'normalised_name' => 'скрытый нормализованный автор',
            'is_primary' => true,
            'sort_order' => 0,
        ]);

        $query = http_build_query([
            'lang' => 'ru',
            'work_type' => 'master_thesis',
            'year' => 2026,
            'language' => 'kk',
            'access' => 'full_public',
            'faculty' => 'Факультет инженерии',
            'department' => 'Кафедра ИТ',
            'educational_programme' => 'Информационные системы',
            'author' => 'айдана серикова',
            'supervisor' => 'Профессор Тестов',
            'udc' => '004.8',
        ]);
        $response = $this->get('/repository?'.$query)
            ->assertOk()
            ->assertSee('Целевая фасетная работа')
            ->assertDontSee('Другая публичная работа')
            ->assertDontSee('Непубличная внутренняя работа')
            ->assertDontSee('INTERNAL-FACET-DECISION')
            ->assertDontSee('Секретный факультет')
            ->assertDontSee('Скрытый Нормализованный Автор')
            ->assertSee('Факультет')
            ->assertSee('Образовательная программа')
            ->assertSee('Научный руководитель')
            ->assertSee('name="educational_programme"', false)
            ->assertSee('name="author"', false)
            ->assertSee('name="udc"', false);

        // Every chip URL retains the active advanced facets. Pagination uses
        // the same URL builder, tested separately below.
        $response
            ->assertSee('faculty='.urlencode('Факультет инженерии'), false)
            ->assertSee('department='.urlencode('Кафедра ИТ'), false)
            ->assertSee('educational_programme='.urlencode('Информационные системы'), false)
            ->assertSee('author='.urlencode('айдана серикова'), false)
            ->assertSee('supervisor='.urlencode('Профессор Тестов'), false)
            ->assertSee('udc=004.8', false);

        // Canonical author rows are case-insensitive; legacy JSON remains an
        // exact, parameter-bound membership check rather than a broad LIKE.
        $this->get('/repository?lang=ru&author='.urlencode('АЙДАНА СЕРИКОВА'))
            ->assertOk()
            ->assertSee('Целевая фасетная работа')
            ->assertDontSee('Другая публичная работа');
        $this->get('/repository?lang=ru&author='.urlencode('Legacy Facet Author'))
            ->assertOk()
            ->assertSee('Целевая фасетная работа')
            ->assertDontSee('Другая публичная работа');
        $this->get('/repository?lang=ru&author='.urlencode('Legacy Facet'))
            ->assertOk()
            ->assertDontSee('Целевая фасетная работа');

        // Non-scalar and markup-bearing values are ignored without a 500 and
        // cannot be reflected into the page or converted into SQL fragments.
        $this->get('/repository?lang=ru&faculty[]=x&author[]=y&udc='.urlencode('<svg data-facet-xss="REPOSITORY_FACET_XSS">'))
            ->assertOk()
            ->assertSee('Целевая фасетная работа')
            ->assertSee('Другая публичная работа')
            ->assertDontSee('REPOSITORY_FACET_XSS', false);
        $this->get('/repository?lang=ru&udc='.urlencode("004.8' OR 1=1 --"))
            ->assertOk()
            ->assertDontSee('Целевая фасетная работа')
            ->assertDontSee('Другая публичная работа');

        $this->get('/repository?lang=ru&access=full_public')
            ->assertOk()
            ->assertSee('Целевая фасетная работа')
            ->assertSee('Другая публичная работа')
            ->assertDontSee('Ещё не открытая работа под эмбарго');

        $this->get('/repository?lang=kk')->assertOk()
            ->assertSee('Білім беру бағдарламасы')
            ->assertSee('Ғылыми жетекші');
        $this->get('/repository?lang=en')->assertOk()
            ->assertSee('Educational programme')
            ->assertSee('Supervisor');

        $this->assertTrue($other->isPublicMetadataVisible());
        $this->assertTrue($embargoed->isPublicMetadataVisible());
    }

    public function test_public_repository_popularity_and_pagination_are_truthful_and_preserve_facets(): void
    {
        Schema::create('settings', function (Blueprint $table): void {
            $table->id();
            $table->string('key')->unique();
            $table->json('value')->nullable();
            $table->string('type')->nullable();
            $table->string('group')->nullable();
            $table->text('description')->nullable();
            $table->timestamps();
        });
        DB::table('settings')->insert([
            'key' => 'results_per_page',
            'value' => '10',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $librarian = $this->makeUser('librarian');
        $director = $this->makeUser('director');
        $items = collect();
        foreach (range(1, 11) as $number) {
            $items->push($this->readyItem($librarian, [
                'title' => sprintf('Фасетная работа %02d', $number),
                'authors' => ['Автор Пагинации'],
                'faculty' => 'Общий факультет',
                'department' => 'Общая кафедра',
                'educational_programme' => 'Общая программа',
                'supervisor' => 'Общий руководитель',
                'udc_code' => '001',
                'status' => 'published',
                'approved_by' => $director->getKey(),
                'published_at' => now()->subMinutes($number),
            ]));
        }

        DB::table('repository_usage_daily')->insert([
            'repository_item_id' => $items->last()->getKey(),
            'occurred_on' => today('UTC')->toDateString(),
            'event_type' => 'metadata_view',
            'role_name' => 'guest',
            'locale' => 'ru',
            'event_count' => 50,
        ]);

        $facetQuery = [
            'lang' => 'ru',
            'faculty' => 'Общий факультет',
            'department' => 'Общая кафедра',
            'educational_programme' => 'Общая программа',
            'author' => 'Автор Пагинации',
            'supervisor' => 'Общий руководитель',
            'udc' => '001',
        ];
        $response = $this->get('/repository?'.http_build_query($facetQuery))
            ->assertOk()
            ->assertSee('page=2', false);
        foreach (array_keys(array_diff_key($facetQuery, ['lang' => true])) as $parameter) {
            $response->assertSee($parameter.'=', false);
        }

        $this->get('/repository?'.http_build_query($facetQuery + ['sort' => 'popular']))
            ->assertOk()
            ->assertSeeInOrder(['Фасетная работа 11', 'Фасетная работа 01'])
            ->assertSee('Популярные');
    }

    public function test_repository_database_default_is_metadata_only_until_rights_are_approved(): void
    {
        $uploader = $this->makeUser('librarian');
        $id = DB::table('repository_items')->insertGetId([
            'title' => 'Проверка значения по умолчанию',
            'authors' => json_encode(['Автор'], JSON_UNESCAPED_UNICODE),
            'work_type' => 'research_report',
            'uploaded_by' => $uploader->getKey(),
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->assertSame('metadata_only', DB::table('repository_items')->find($id)->access_policy);
    }

    public function test_homepage_shows_the_latest_approved_repository_work_before_news(): void
    {
        $librarian = $this->makeUser('librarian');
        $director = $this->makeUser('director');
        $this->readyItem($librarian, [
            'title' => 'Последняя работа на главной',
            'status' => 'published',
            'approved_by' => $director->getKey(),
            'published_at' => now(),
        ]);

        $this->get('/?lang=ru')
            ->assertOk()
            ->assertSee('homepage-repository-latest', false)
            ->assertSee('Последняя работа на главной');

        $welcome = file_get_contents(resource_path('views/welcome.blade.php'));
        $this->assertNotFalse($welcome);
        $this->assertLessThan(
            strpos($welcome, "@include('home.news')"),
            strpos($welcome, "@include('home.repository')"),
        );
    }

    public function test_demo_seeder_creates_all_seven_clearly_labelled_records_with_valid_pdf_files(): void
    {
        config()->set('demo_users.enabled', true);
        $uploader = $this->makeUser('librarian');
        $uploader->update(['email' => 'demo-librarian@kazutb.local']);
        $director = $this->makeUser('director');
        $director->update(['email' => 'demo-director@kazutb.local']);

        app(RepositorySeeder::class)->run();
        app(RepositorySeeder::class)->run();

        $items = RepositoryItem::query()->orderBy('work_type')->get();
        $this->assertCount(7, $items);
        $this->assertEqualsCanonicalizing(RepositoryItem::WORK_TYPES, $items->pluck('work_type')->all());

        foreach ($items as $item) {
            $this->assertStringStartsWith('[ДЕМО]', $item->title);
            $this->assertSame('published', $item->status);
            $this->assertSame('full_public', $item->access_policy);
            $this->assertSame($director->getKey(), $item->approved_by);
            $this->assertNotNull($item->active_approval_id);
            $this->assertTrue($item->hasDirectorApproval());
            Storage::disk('local')->assertExists($item->file_path);
            $pdf = Storage::disk('local')->get($item->file_path);
            $this->assertStringStartsWith('%PDF-1.4', $pdf);
            $this->assertStringEndsWith("%%EOF\n", $pdf);
            $this->assertSame('application/pdf', (new \finfo(FILEINFO_MIME_TYPE))->buffer($pdf));
            $this->assertSame(1, $item->versions()->count());
            $this->assertSame(3, $item->reviews()->count());
        }
    }

    public function test_changed_metadata_invalidates_exact_approval_and_prevents_a_stale_scheduled_release(): void
    {
        $librarian = $this->makeUser('librarian');
        $director = $this->makeUser('director');
        $scheduled = $this->readyItem($librarian, [
            'status' => 'scheduled',
            'scheduled_for' => now()->addHour(),
            'approved_by' => $director->getKey(),
        ]);
        $approvalId = $scheduled->active_approval_id;
        $versionId = $scheduled->activeApproval->repository_item_version_id;

        $this->signInAs($librarian)
            ->patch(route('librarian.repository.update', $scheduled), $this->editPayload($scheduled, [
                'title' => 'Исправленные после утверждения метаданные',
            ]))
            ->assertRedirect();

        $scheduled->refresh();
        $this->assertSame('metadata_review', $scheduled->status);
        $this->assertNull($scheduled->active_approval_id);
        $this->assertNull($scheduled->approved_by);
        $this->assertNull($scheduled->scheduled_for);
        $this->assertDatabaseHas('repository_approvals', [
            'id' => $approvalId,
            'repository_item_version_id' => $versionId,
            'approver_role_snapshot' => 'director',
        ]);

        $method = new \ReflectionMethod(SweepDigitalLibraryServices::class, 'sweepRepository');
        $method->invoke(app(SweepDigitalLibraryServices::class), app(AuditLogger::class));
        $this->assertSame('metadata_review', $scheduled->refresh()->status);

        $published = $this->readyItem($librarian, [
            'title' => 'Неизменяемая опубликованная работа',
            'status' => 'published',
            'approved_by' => $director->getKey(),
            'published_at' => now(),
        ]);
        $this->signInAs($librarian)
            ->patch(route('librarian.repository.update', $published), $this->editPayload($published, ['title' => 'Подмена']))
            ->assertSessionHasErrors('title');
        $this->assertSame('Неизменяемая опубликованная работа', $published->refresh()->title);
        $this->assertNotNull($published->active_approval_id);

        // Bulk SQL maintenance is covered by the same invariant via the
        // additive migration trigger, not only by this HTTP controller.
        DB::table('repository_items')->where('id', $published->getKey())->update(['title' => 'SQL-изменение']);
        $published->refresh();
        $this->assertSame('metadata_review', $published->status);
        $this->assertNull($published->active_approval_id);
        $this->get(route('repository.show', $published))->assertNotFound();
    }

    public function test_new_post_publication_version_retains_old_pdf_and_requires_fresh_approval(): void
    {
        $librarian = $this->makeUser('librarian');
        $director = $this->makeUser('director');
        $published = $this->readyItem($librarian, [
            'title' => 'Работа с новой редакцией',
            'status' => 'published',
            'approved_by' => $director->getKey(),
            'published_at' => now(),
        ]);
        $oldPath = $published->file_path;
        $oldApprovalId = $published->active_approval_id;

        $this->signInAs($librarian)->post(route('librarian.repository.revisions.store', $published), [
            'file' => UploadedFile::fake()->createWithContent('version-2.pdf', "%PDF-1.4\n% approved work corrected\n%%EOF\n"),
            'version_reason' => 'Исправлена подтверждённая опечатка в полном тексте.',
        ])->assertRedirect(route('librarian.repository.edit', $published));

        $published->refresh();
        $this->assertSame('metadata_review', $published->status);
        $this->assertSame(2, $published->version_number);
        $this->assertNull($published->active_approval_id);
        $this->assertNull($published->approved_by);
        $this->assertNotSame($oldPath, $published->file_path);
        Storage::disk('local')->assertExists($oldPath);
        Storage::disk('local')->assertExists($published->file_path);
        $this->assertDatabaseHas('repository_item_versions', [
            'repository_item_id' => $published->getKey(),
            'version_number' => 1,
            'file_path' => $oldPath,
            'is_active' => false,
        ]);
        $this->assertDatabaseHas('repository_item_versions', [
            'repository_item_id' => $published->getKey(),
            'version_number' => 2,
            'change_reason' => 'Исправлена подтверждённая опечатка в полном тексте.',
            'is_active' => true,
        ]);
        $this->assertDatabaseHas('repository_approvals', ['id' => $oldApprovalId]);
        $this->get(route('repository.show', $published))->assertNotFound();
    }

    public function test_rights_matrix_and_explicit_post_embargo_policy_are_enforced(): void
    {
        $librarian = $this->makeUser('librarian');
        $director = $this->makeUser('director');
        $member = $this->makeUser('member');
        $workflow = app(RepositoryWorkflow::class);

        foreach ([
            ['copyright_status' => 'licensed', 'licence_type' => 'annual', 'licence_text' => null],
            ['copyright_status' => 'permission_granted', 'permission_date' => null],
            ['copyright_status' => 'restricted', 'access_policy' => 'full_public'],
            ['embargo_until' => now()->addDay(), 'post_embargo_access_policy' => null],
        ] as $invalidRights) {
            $item = $this->readyItem($librarian, ['status' => 'pending_approval'] + $invalidRights);
            $this->assertTransitionError(
                fn () => $workflow->transition($item, 'approved', $director),
                'copyright_status',
            );
        }

        $licensed = $this->readyItem($librarian, [
            'status' => 'pending_approval',
            'copyright_status' => 'licensed',
            'licence_type' => 'annual',
            'licence_text' => 'Разрешён публичный показ полного текста.',
        ]);
        $workflow->transition($licensed, 'approved', $director);
        $this->assertTrue($licensed->refresh()->hasDirectorApproval());

        $restricted = $this->readyItem($librarian, [
            'status' => 'pending_approval',
            'copyright_status' => 'restricted',
            'access_policy' => 'metadata_public_file_authenticated',
        ]);
        $workflow->transition($restricted, 'approved', $director);
        $workflow->transition($restricted, 'published', $director);
        $this->assertFalse($restricted->refresh()->canExposeFullText());
        $this->assertTrue($restricted->canExposeFullText($member));

        $embargoed = $this->readyItem($librarian, [
            'status' => 'pending_approval',
            'embargo_until' => now()->addDay(),
            'post_embargo_access_policy' => 'metadata_public_file_authenticated',
        ]);
        $workflow->transition($embargoed, 'approved', $director);
        $workflow->transition($embargoed, 'embargoed', $director);
        $this->assertFalse($embargoed->refresh()->canExposeFullText($member));

        $this->travel(2)->days();
        $method = new \ReflectionMethod(SweepDigitalLibraryServices::class, 'sweepRepository');
        $method->invoke(app(SweepDigitalLibraryServices::class), app(AuditLogger::class));
        $this->assertSame('published', $embargoed->refresh()->status);
        $this->assertSame('metadata_public_file_authenticated', $embargoed->effectiveAccessPolicy());
        $this->assertFalse($embargoed->canExposeFullText());
        $this->assertTrue($embargoed->canExposeFullText($member));
    }

    public function test_usage_is_success_only_anonymous_daily_aggregated_throttled_and_pruned(): void
    {
        $librarian = $this->makeUser('librarian');
        $director = $this->makeUser('director');
        $item = $this->readyItem($librarian, [
            'status' => 'published',
            'approved_by' => $director->getKey(),
            'published_at' => now(),
        ]);

        RateLimiter::for('repository-read', fn () => Limit::perMinute(2)->by('repository-feature-test'));
        $this->get(route('repository.show', $item))->assertOk();
        $this->get(route('repository.show', $item))->assertOk();
        $this->get(route('repository.show', $item))->assertTooManyRequests();
        $this->assertDatabaseHas('repository_usage_daily', [
            'repository_item_id' => $item->getKey(),
            'event_type' => 'metadata_view',
            'role_name' => 'guest',
            'event_count' => 2,
        ]);

        RateLimiter::for('repository-read', fn () => Limit::perMinute(100)->by('repository-success-test'));
        $this->withHeader('Range', 'bytes=0-9')->get(route('repository.view', $item))->assertStatus(206);
        $this->withHeader('Range', 'bytes=999999-1000000')->get(route('repository.view', $item))->assertStatus(416);
        $this->head(route('repository.view', $item))->assertOk();
        $this->assertDatabaseHas('repository_usage_daily', [
            'repository_item_id' => $item->getKey(),
            'event_type' => 'pdf_view',
            'event_count' => 1,
        ]);

        app(RepositoryUsageRecorder::class)->record($item, 'download', null, 'unsupported');
        $this->assertDatabaseHas('repository_usage_daily', [
            'repository_item_id' => $item->getKey(),
            'event_type' => 'download',
            'role_name' => 'guest',
            'locale' => 'kk',
            'event_count' => 1,
        ]);

        foreach (['user_id', 'created_at', 'updated_at', 'ip_address', 'user_agent', 'referrer'] as $personalField) {
            $this->assertFalse(Schema::hasColumn('repository_usage_daily', $personalField));
        }

        DB::table('repository_usage_daily')->update(['occurred_on' => today('UTC')->subDays(31)->toDateString()]);
        $this->artisan('repository:usage-prune', ['--days' => 30])->assertSuccessful();
        $this->assertDatabaseCount('repository_usage_daily', 0);
    }

    public function test_inactive_sessions_cannot_use_authenticated_or_campus_repository_access(): void
    {
        config()->set('digital_access.campus_ranges', ['127.0.0.0/8']);
        $librarian = $this->makeUser('librarian');
        $director = $this->makeUser('director');
        $member = $this->makeUser('member');
        $authenticated = $this->readyItem($librarian, [
            'status' => 'published',
            'access_policy' => 'metadata_public_file_authenticated',
            'approved_by' => $director->getKey(),
            'published_at' => now(),
        ]);
        $campus = $this->readyItem($librarian, [
            'status' => 'published',
            'access_policy' => 'campus_only',
            'approved_by' => $director->getKey(),
            'published_at' => now(),
        ]);

        $this->signInAs($member)->get(route('repository.download', $authenticated))->assertOk();
        $this->actingAs($member)->get(route('repository.download', $campus))->assertOk();
        $member->update(['is_active' => false]);
        $this->actingAs($member->refresh())->get(route('repository.download', $authenticated))->assertForbidden();
        $this->actingAs($member)->get(route('repository.download', $campus))->assertForbidden();

        $librarian->update(['is_active' => false]);
        $this->assertFalse($librarian->refresh()->can('create', RepositoryItem::class));
    }

    public function test_role_snapshot_survives_later_role_revocation_and_approver_deletion(): void
    {
        $librarian = $this->makeUser('librarian');
        $director = $this->makeUser('director');
        $item = $this->readyItem($librarian, [
            'status' => 'published',
            'approved_by' => $director->getKey(),
            'published_at' => now(),
        ]);
        $approval = $item->activeApproval;

        $this->assertSame('director', $approval->approver_role_snapshot);
        $this->assertSame($item->checksum_sha256, $approval->checksum_sha256);
        $director->removeRole('director');
        $this->assertTrue($item->refresh()->hasDirectorApproval());
        $this->get(route('repository.show', $item))->assertOk();

        $director->delete();
        $this->assertNull($approval->refresh()->approver_id);
        $this->assertTrue($item->refresh()->hasDirectorApproval());
        $this->get(route('repository.show', $item))->assertOk();

        $this->expectException(\LogicException::class);
        $approval->update(['approver_role_snapshot' => 'admin']);
    }

    public function test_mutated_valid_pdf_fails_checksum_integrity_and_is_never_released(): void
    {
        $librarian = $this->makeUser('librarian');
        $director = $this->makeUser('director');
        $published = $this->readyItem($librarian, [
            'status' => 'published',
            'approved_by' => $director->getKey(),
            'published_at' => now(),
        ]);
        $this->assertTrue($published->hasStoredPublishablePdf());
        $this->get(route('repository.download', $published))->assertOk();

        // Still a syntactically valid PDF, but no longer the exact version the
        // director approved.
        Storage::disk('local')->put($published->file_path, "%PDF-1.4\n% malicious replacement after approval\n%%EOF\n");
        $this->assertFalse($published->refresh()->hasStoredPublishablePdf());
        $this->assertFalse($published->readyForPublicRelease());
        $this->get(route('repository.download', $published))->assertNotFound();
        $this->get(route('repository.view', $published))->assertNotFound();

        $scheduled = $this->readyItem($librarian, [
            'status' => 'scheduled',
            'scheduled_for' => now()->subMinute(),
            'approved_by' => $director->getKey(),
        ]);
        Storage::disk('local')->put($scheduled->file_path, "%PDF-1.4\n% different valid PDF\n%%EOF\n");
        $method = new \ReflectionMethod(SweepDigitalLibraryServices::class, 'sweepRepository');
        $method->invoke(app(SweepDigitalLibraryServices::class), app(AuditLogger::class));
        $this->assertSame('scheduled', $scheduled->refresh()->status);
    }

    public function test_public_news_and_repository_are_bidirectionally_linked_without_mixing_entities(): void
    {
        $this->createNewsSchema();
        (require base_path('database/migrations/2026_08_12_280000_link_news_to_scholarly_repository.php'))->up();

        $librarian = $this->makeUser('librarian');
        $director = $this->makeUser('director');
        $public = $this->readyItem($librarian, [
            'title' => 'Работа со связанной новостью',
            'status' => 'published',
            'approved_by' => $director->getKey(),
            'published_at' => now(),
        ]);
        $hidden = $this->readyItem($librarian, [
            'title' => 'Неутверждённая скрытая работа',
            'status' => 'draft',
        ]);

        $linked = News::query()->create($this->newsAttributes([
            'slug' => 'linked-work-announcement',
            'slug_ru' => 'linked-work-announcement',
            'title' => 'Новость о научной работе',
            'title_ru' => 'Новость о научной работе',
            'repository_item_id' => $public->getKey(),
        ]));
        News::query()->create($this->newsAttributes([
            'slug' => 'hidden-work-announcement',
            'slug_ru' => 'hidden-work-announcement',
            'title' => 'Новость со скрытой записью',
            'title_ru' => 'Новость со скрытой записью',
            'repository_item_id' => $hidden->getKey(),
        ]));
        News::query()->create($this->newsAttributes([
            'slug' => 'staff-only-announcement',
            'slug_ru' => 'staff-only-announcement',
            'title' => 'Служебная новость',
            'title_ru' => 'Служебная новость',
            'visibility' => 'staff',
            'repository_item_id' => $public->getKey(),
        ]));

        $this->assertSame($public->getKey(), $linked->repositoryItem->getKey());
        $this->assertTrue($public->linkedNews()->whereKey($linked)->exists());

        $this->get('/news/linked-work-announcement?lang=ru')
            ->assertOk()
            ->assertSee('news-linked-repository', false)
            ->assertSee('/repository/'.$public->getKey().'?lang=ru', false);
        $this->get('/news/hidden-work-announcement?lang=ru')
            ->assertOk()
            ->assertDontSee('news-linked-repository', false)
            ->assertDontSee('/repository/'.$hidden->getKey().'?lang=ru', false);
        $this->get(route('repository.show', ['item' => $public, 'lang' => 'ru']))
            ->assertOk()
            ->assertSee('repository-linked-news', false)
            ->assertSee('/news/linked-work-announcement?lang=ru', false)
            ->assertDontSee('staff-only-announcement', false);

        $this->assertDatabaseHas('news', [
            'id' => $linked->getKey(),
            'repository_item_id' => $public->getKey(),
        ]);
        $this->assertDatabaseHas('repository_items', [
            'id' => $public->getKey(),
            'title' => 'Работа со связанной новостью',
        ]);
    }

    private function createSqliteSchema(): void
    {
        Schema::create('users', function (Blueprint $table): void {
            $table->id();
            $table->string('name');
            $table->string('email')->unique();
            $table->string('password');
            $table->string('ad_login')->nullable()->unique();
            $table->string('role')->default('reader');
            $table->string('auth_provider')->nullable();
            $table->string('external_id')->nullable();
            $table->string('role_source')->nullable();
            $table->string('department')->nullable();
            $table->boolean('is_active')->default(true);
            $table->string('locale')->default('ru');
            $table->boolean('must_change_password')->default(false);
            $table->timestamp('email_verified_at')->nullable();
            $table->rememberToken();
            $table->timestamps();
        });

        (require base_path('database/migrations/2026_07_28_170726_create_permission_tables.php'))->up();
        (require base_path('database/migrations/2026_07_28_171000_create_activity_logs_table.php'))->up();

        Schema::create('reader_notifications', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('user_id')->constrained('users')->cascadeOnDelete();
            $table->string('event_type', 64);
            $table->string('title', 500);
            $table->text('body')->nullable();
            $table->json('payload')->nullable();
            $table->timestampTz('read_at')->nullable();
            $table->timestampsTz();
        });

        Schema::create('repository_items', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->nullable()->unique();
            $table->string('title', 1000);
            $table->json('authors');
            $table->string('work_type', 32);
            $table->unsignedSmallInteger('year')->nullable();
            $table->string('department')->nullable();
            $table->string('udc_code', 64)->nullable();
            $table->text('abstract')->nullable();
            $table->json('keywords')->nullable();
            $table->string('language', 8)->default('ru');
            $table->string('file_path')->nullable();
            $table->string('file_name')->nullable();
            $table->unsignedBigInteger('file_size')->nullable();
            $table->string('checksum_sha256', 64)->nullable();
            $table->unsignedInteger('version_number')->default(1);
            $table->string('status', 32)->default('draft');
            $table->foreignId('uploaded_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('reviewed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('review_notes')->nullable();
            $table->timestampTz('published_at')->nullable();
            $table->json('title_translations')->nullable();
            $table->string('original_title', 1000)->nullable();
            $table->string('supervisor')->nullable();
            $table->string('reviewer')->nullable();
            $table->string('university')->nullable();
            $table->string('faculty')->nullable();
            $table->string('educational_programme')->nullable();
            $table->string('degree_level', 64)->nullable();
            $table->date('defence_date')->nullable();
            $table->date('publication_date')->nullable();
            $table->json('abstract_translations')->nullable();
            $table->json('keyword_translations')->nullable();
            $table->unsignedInteger('page_count')->nullable();
            $table->text('bibliography')->nullable();
            $table->string('doi')->nullable();
            $table->string('isbn_issn', 64)->nullable();
            $table->text('source')->nullable();
            $table->string('rights_holder')->nullable();
            $table->string('copyright_status', 48)->default('unknown');
            $table->string('licence_type', 64)->nullable();
            $table->text('licence_text')->nullable();
            $table->string('permission_document_path')->nullable();
            $table->date('permission_date')->nullable();
            $table->string('access_policy', 64)->default('metadata_only');
            $table->timestampTz('embargo_until')->nullable();
            $table->timestampTz('scheduled_for')->nullable();
            $table->timestampTz('withdrawn_at')->nullable();
            $table->text('withdrawal_reason')->nullable();
            $table->foreignId('withdrawn_by')->nullable()->constrained('users')->nullOnDelete();
            $table->text('internal_review_notes')->nullable();
            $table->timestampsTz();
        });

        Schema::create('repository_authors', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repository_item_id')->constrained()->cascadeOnDelete();
            $table->foreignId('user_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('display_name');
            $table->string('normalised_name');
            $table->string('orcid', 19)->nullable();
            $table->string('affiliation')->nullable();
            $table->boolean('is_primary')->default(false);
            $table->unsignedInteger('sort_order')->default(0);
            $table->timestampsTz();
        });

        Schema::create('repository_item_versions', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repository_item_id')->constrained()->cascadeOnDelete();
            $table->unsignedInteger('version_number');
            $table->string('storage_disk', 32)->default('local');
            $table->string('file_path');
            $table->string('file_name');
            $table->unsignedBigInteger('file_size');
            $table->string('mime_type', 160);
            $table->string('checksum_sha256', 64);
            $table->text('change_reason');
            $table->foreignId('created_by')->nullable()->constrained('users')->nullOnDelete();
            $table->boolean('is_active')->default(true);
            $table->timestampsTz();
            $table->unique(['repository_item_id', 'version_number']);
        });

        Schema::create('repository_reviews', function (Blueprint $table): void {
            $table->id();
            $table->foreignId('repository_item_id')->constrained()->cascadeOnDelete();
            $table->string('review_type', 32);
            $table->string('decision', 32);
            $table->text('comment')->nullable();
            $table->foreignId('reviewer_id')->nullable()->constrained('users')->nullOnDelete();
            $table->timestampsTz();
        });

        (require base_path('database/migrations/2026_08_11_100000_make_scholarly_repository_public_by_default.php'))->up();
        (require base_path('database/migrations/2026_08_12_120000_add_repository_usage_and_normalize_work_types.php'))->up();
        (require base_path('database/migrations/2026_08_12_240000_harden_scholarly_repository.php'))->up();
        (require base_path('database/migrations/2026_08_12_270000_install_repository_approval_invalidation_trigger.php'))->up();
        (require base_path('database/migrations/2026_08_12_290000_make_repository_usage_kazakh_default.php'))->up();
    }

    private function createNewsSchema(): void
    {
        Schema::create('news', function (Blueprint $table): void {
            $table->id();
            $table->uuid('public_id')->nullable()->unique();
            $table->string('slug')->unique();
            $table->string('title');
            $table->string('category')->default('announcement');
            $table->text('body')->default('');
            $table->text('excerpt')->nullable();
            $table->string('cover_image')->nullable();
            $table->string('status')->default('draft');
            $table->timestampTz('publish_at')->nullable();
            $table->boolean('show_on_homepage')->default(false);
            $table->string('language', 8)->default('ru');
            $table->foreignId('created_by')->nullable();
            $table->foreignId('published_by')->nullable();
            $table->string('type')->default('announcement');
            $table->timestampTz('published_at')->nullable();
            $table->string('visibility')->default('public');
            $table->boolean('is_featured')->default(false);
            $table->string('title_kk')->nullable();
            $table->string('title_ru')->nullable();
            $table->string('title_en')->nullable();
            $table->text('excerpt_kk')->nullable();
            $table->text('excerpt_ru')->nullable();
            $table->text('excerpt_en')->nullable();
            $table->text('content_kk')->nullable();
            $table->text('content_ru')->nullable();
            $table->text('content_en')->nullable();
            $table->string('slug_kk')->nullable();
            $table->string('slug_ru')->nullable();
            $table->string('slug_en')->nullable();
            $table->string('image_alt_kk')->nullable();
            $table->string('image_alt_ru')->nullable();
            $table->string('image_alt_en')->nullable();
            $table->timestampTz('starts_at')->nullable();
            $table->timestampTz('ends_at')->nullable();
            $table->string('timezone')->default('Asia/Almaty');
            $table->string('venue')->nullable();
            $table->string('venue_kk')->nullable();
            $table->string('venue_ru')->nullable();
            $table->string('venue_en')->nullable();
            $table->string('online_url')->nullable();
            $table->string('registration_url')->nullable();
            $table->boolean('registration_required')->default(false);
            $table->string('organizer')->nullable();
            $table->string('contact_name')->nullable();
            $table->foreignId('annual_plan_item_id')->nullable();
            $table->unsignedBigInteger('view_count')->default(0);
            $table->timestampsTz();
            $table->softDeletesTz();
        });
    }

    /** @param array<string, mixed> $overrides */
    private function newsAttributes(array $overrides = []): array
    {
        return array_merge([
            'slug' => 'repository-announcement-'.str()->random(8),
            'title' => 'Новость репозитория',
            'category' => 'announcement',
            'body' => 'Опубликована новая научная работа.',
            'status' => 'published',
            'publish_at' => now(),
            'published_at' => now(),
            'type' => 'announcement',
            'visibility' => 'public',
            'language' => 'ru',
            'title_kk' => 'Репозиторий жаңалығы',
            'content_kk' => 'Жаңа ғылыми жұмыс жарияланды.',
        ], $overrides);
    }

    private function makeUser(string $role): User
    {
        $suffix = str()->lower(str()->random(8));
        $user = User::query()->create([
            'name' => ucfirst($role).' Repository Test',
            'email' => "{$role}.{$suffix}@example.test",
            'ad_login' => "{$role}_{$suffix}",
            'role' => match ($role) {
                'admin' => 'admin',
                'member' => 'reader',
                default => 'librarian',
            },
            'password' => Hash::make('RepositoryTest2026!'),
            'auth_provider' => 'demo',
            'role_source' => 'manual',
            'is_active' => true,
            'email_verified_at' => now(),
        ]);
        $user->syncRoles([$role]);

        return $user->refresh();
    }

    private function signInAs(User $user): static
    {
        $canonical = (string) $user->getRoleNames()->first();
        $legacy = match ($canonical) {
            'admin' => 'admin',
            'member' => 'reader',
            default => 'librarian',
        };

        return $this->actingAs($user)->withSession([
            'library.user' => [
                'id' => (string) $user->getKey(),
                'local_id' => $user->getKey(),
                'name' => $user->name,
                'email' => $user->email,
                'role' => $legacy,
                'canonical_role' => $canonical,
            ],
            'library.authenticated_at' => now()->toIso8601String(),
            'locale' => 'ru',
        ]);
    }

    /** @param array<string, mixed> $overrides */
    private function readyItem(User $uploader, array $overrides = []): RepositoryItem
    {
        $token = str()->uuid()->toString();
        $path = "repository/tests/{$token}.pdf";
        Storage::disk('local')->put($path, "%PDF-1.4\n% repository test\n%%EOF\n");

        $item = RepositoryItem::query()->create(array_merge([
            'title' => 'Тестовая работа '.$token,
            'authors' => ['Автор Тестов'],
            'work_type' => 'scientific_article',
            'language' => 'ru',
            'status' => 'draft',
            'uploaded_by' => $uploader->getKey(),
            'source' => 'Архив университета',
            'rights_holder' => 'KazUTB',
            'copyright_status' => 'university_owned',
            'access_policy' => 'full_public',
            'file_path' => $path,
            'file_name' => 'work.pdf',
            'file_size' => Storage::disk('local')->size($path),
        ], $overrides));

        $activePath = trim((string) $item->file_path);
        if ($activePath !== '' && Storage::disk('local')->exists($activePath)) {
            $contents = Storage::disk('local')->get($activePath);
            $checksum = hash('sha256', $contents);
            $item->forceFill([
                'file_size' => strlen($contents),
                'checksum_sha256' => $checksum,
                'version_number' => 1,
            ])->save();
            $version = RepositoryItemVersion::query()->create([
                'repository_item_id' => $item->getKey(),
                'version_number' => 1,
                'storage_disk' => 'local',
                'file_path' => $activePath,
                'file_name' => $item->file_name ?: basename($activePath),
                'file_size' => strlen($contents),
                'mime_type' => 'application/pdf',
                'checksum_sha256' => $checksum,
                'change_reason' => 'Repository feature-test fixture.',
                'created_by' => $uploader->getKey(),
                'is_active' => true,
            ]);

            $approver = $item->approved_by ? User::query()->find($item->approved_by) : null;
            if ($approver?->hasRole('director')
                && (int) $approver->getKey() !== (int) $item->uploaded_by
                && in_array($item->status, ['approved', 'scheduled', 'published', 'embargoed', 'withdrawn'], true)) {
                $approval = RepositoryApproval::query()->create([
                    'repository_item_id' => $item->getKey(),
                    'repository_item_version_id' => $version->getKey(),
                    'approver_id' => $approver->getKey(),
                    'approver_role_snapshot' => 'director',
                    'checksum_sha256' => $checksum,
                    'metadata_fingerprint' => $item->approvalFingerprint($version),
                    'approved_at' => now('UTC'),
                ]);
                $item->forceFill(['active_approval_id' => $approval->getKey()])->save();
            }
        }

        return $item->refresh();
    }

    private function assertTransitionError(callable $operation, string $field): void
    {
        try {
            $operation();
            $this->fail("Expected a validation error for {$field}.");
        } catch (ValidationException $exception) {
            $this->assertArrayHasKey($field, $exception->errors());
        }
    }

    /** @param array<string, mixed> $overrides */
    private function editPayload(RepositoryItem $item, array $overrides = []): array
    {
        return array_merge([
            'title' => $item->title,
            'authors' => implode("\n", $item->authors),
            'work_type' => $item->work_type,
            'language' => $item->language,
            'source' => $item->source,
            'rights_holder' => $item->rights_holder,
            'copyright_status' => $item->copyright_status,
            'licence_type' => $item->licence_type,
            'licence_text' => $item->licence_text,
            'permission_date' => $item->permission_date?->toDateString(),
            'access_policy' => $item->access_policy,
            'embargo_until' => $item->embargo_until?->toDateString(),
            'post_embargo_access_policy' => $item->post_embargo_access_policy,
        ], $overrides);
    }
}
