<?php

namespace Tests\Feature;

use App\Models\Catalog\ReaderNotification;
use App\Models\ExternalResource;
use App\Models\ExternalResourceEvent;
use App\Models\ExternalResourceNotificationOutbox;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Client\Request as ClientRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class ExternalResourceFullStackTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_seeded_catalogue_keeps_unverified_partners_and_licences_as_private_drafts(): void
    {
        $this->assertEqualsCanonicalizing(
            ['licensed', 'open_access', 'partner', 'internal'],
            ExternalResource::query()->distinct()->pluck('resource_type')->all(),
        );

        $slugs = ExternalResource::query()->pluck('slug')->all();
        foreach ([
            'rmeb', 'ipr-smart', 'atu-library', 'rntb-kazakhstan', 'kozybayev-library',
            'astana-cbs', 'arnaiy-kitaphana', 'cyberleninka', 'doaj', 'oapen',
            'kaznu-repository', 'kazutb-catalogue',
        ] as $slug) {
            $this->assertContains($slug, $slugs);
        }

        ExternalResource::query()->where('resource_type', 'partner')->each(function (ExternalResource $resource): void {
            $this->assertSame('draft', $resource->publication_status);
            $this->assertFalse($resource->is_active);
            $this->assertNull($resource->url);
        });
        ExternalResource::query()->where('resource_type', 'licensed')->each(function (ExternalResource $resource): void {
            $this->assertSame('draft', $resource->publication_status);
            $this->assertFalse($resource->is_active);
            $this->assertContains('agreement_end_date', $resource->publicationReadinessIssues());
        });

        $accessible = ExternalResource::query()->where('slug', 'arnaiy-kitaphana')->firstOrFail();
        $this->assertContains('braille_books', $accessible->content_types);
        $this->assertContains('audiobooks', $accessible->content_types);

        $internal = ExternalResource::query()->where('slug', 'kazutb-catalogue')->firstOrFail();
        $this->assertSame('/catalog', $internal->url);
        $this->assertTrue($internal->readyForPublication());
    }

    public function test_public_directory_has_four_categories_complete_cards_and_client_filters_without_draft_leaks(): void
    {
        $this->assertDatabaseHas('external_resources', ['slug' => 'doaj', 'publication_status' => 'published', 'is_active' => 1]);
        $response = $this->get('/resources?lang=ru')->assertOk();
        foreach ([
            'Лицензионные ресурсы', 'Открытый доступ', 'Партнёрские ресурсы',
            'Внутренние ресурсы библиотеки', 'Directory of Open Access Journals',
            'Электронный каталог библиотеки КазУТБ', 'Кому доступно', 'Как пользоваться',
            'Сотрудник библиотеки', 'Тип контента', 'Только из кампуса', 'Подробнее',
        ] as $copy) {
            $response->assertSee($copy, false);
        }
        $response
            ->assertSee('data-resource-facet="audience"', false)
            ->assertSee('data-resource-facet="accessScope"', false)
            ->assertSee('data-resource-facet="content"', false)
            ->assertSee('data-resource-facet="status"', false)
            ->assertDontSee('Научная библиотека АТУ', false)
            ->assertDontSee('IPR SMART', false)
            ->assertDontSee('internal_notes', false)
            ->assertDontSee('licence_file_path', false)
            ->assertDontSee('contract_number', false)
            ->assertDontSee('health_check_url', false);

        $this->get('/resources?lang=kk')->assertOk()->assertSee('Кітапхана қызметкері', false);
        $this->get('/resources?lang=en')->assertOk()->assertSee('Library staff', false)->assertSee('View details', false);
    }

    public function test_all_four_resource_types_can_be_created_as_private_drafts(): void
    {
        $this->signInToLibraryAs($this->adminUser);

        foreach (ExternalResource::TYPES as $type) {
            $payload = $this->validPayload([
                'title' => 'Created '.$type,
                'resource_type' => $type,
                'url' => $type === 'internal' ? '/catalog' : 'https://example.org/'.$type,
                'available_roles' => in_array($type, ['open_access', 'internal'], true)
                    ? ExternalResource::AUDIENCES
                    : ['student', 'teacher', 'library_staff'],
                'access_type' => $type === 'open_access' || $type === 'internal' ? 'open' : 'remote_auth',
                'access_method' => $type === 'internal' || $type === 'open_access' ? 'public_url' : 'personal_account',
                'login_required' => in_array($type, ['licensed', 'partner'], true) ? '1' : '0',
                'contract_ends_at' => in_array($type, ['licensed', 'partner'], true)
                    ? today('UTC')->addYear()->toDateString()
                    : null,
            ]);
            $this->post(route('admin.external-resources.store'), $payload)->assertRedirect();
            $this->assertDatabaseHas('external_resources', [
                'title' => 'Created '.$type,
                'resource_type' => $type,
                'publication_status' => 'draft',
                'is_active' => false,
            ]);
        }
    }

    public function test_guest_student_and_teacher_audience_access_rules(): void
    {
        $guestResource = $this->createPublishedResource([
            'slug' => 'audience-guests',
            'available_roles' => ['guest'],
            'guest_access' => true,
        ]);
        $studentResource = $this->createPublishedResource([
            'slug' => 'audience-students',
            'available_roles' => ['student'],
            'guest_access' => false,
            'login_required' => true,
        ]);
        $teacherResource = $this->createPublishedResource([
            'slug' => 'audience-teachers',
            'available_roles' => ['teacher'],
            'guest_access' => false,
            'login_required' => true,
        ]);

        $this->assertTrue($guestResource->canOpen(null));
        $this->assertFalse($studentResource->canOpen(null));
        $this->assertFalse($teacherResource->canOpen(null));

        $student = $this->makeControlPlaneUser('member');
        $student->readerProfile()->create([
            'ticket_number' => 'AUD-STUDENT-'.$student->id,
            'category' => 'student',
            'status' => 'active',
        ]);
        $this->assertTrue($studentResource->canOpen($student->refresh()));
        $this->assertFalse($teacherResource->canOpen($student));

        $teacher = $this->makeControlPlaneUser('member');
        $teacher->readerProfile()->create([
            'ticket_number' => 'AUD-TEACHER-'.$teacher->id,
            'category' => 'teacher',
            'status' => 'active',
        ]);
        $this->assertTrue($teacherResource->canOpen($teacher->refresh()));
        $this->assertFalse($studentResource->canOpen($teacher));
    }

    public function test_localized_instructions_are_served_in_each_language(): void
    {
        $resource = $this->createPublishedResource([
            'slug' => 'localized-instructions',
            'instructions_translations' => [
                'ru' => 'Инструкция RU',
                'kk' => 'Нұсқаулық KK',
                'en' => 'Instructions EN',
            ],
        ]);

        $this->get(route('resources.show', ['slug' => $resource->slug, 'lang' => 'ru']))->assertSee('Инструкция RU', false);
        $this->get(route('resources.show', ['slug' => $resource->slug, 'lang' => 'kk']))->assertSee('Нұсқаулық KK', false);
        $this->get(route('resources.show', ['slug' => $resource->slug, 'lang' => 'en']))->assertSee('Instructions EN', false);
    }

    public function test_detail_page_is_public_localized_and_records_card_view_without_secrets(): void
    {
        $resource = ExternalResource::query()->where('slug', 'doaj')->firstOrFail();

        $this->get(route('resources.show', ['slug' => $resource->slug, 'lang' => 'en']))
            ->assertOk()
            ->assertSee('Directory of Open Access Journals', false)
            ->assertSee('Available to', false)
            ->assertSee('How to use', false)
            ->assertSee(route('external-resources.open', $resource), false)
            ->assertDontSee('internal_notes', false)
            ->assertDontSee('licence_file_path', false)
            ->assertDontSee('contract_number', false)
            ->assertDontSee('health_check_url', false);

        $this->assertDatabaseHas('external_resource_events', [
            'external_resource_id' => $resource->getKey(),
            'event_type' => 'card_view',
            'role_name' => 'guest',
        ]);
        $this->get('/resources/ipr-smart')->assertNotFound();
    }

    public function test_api_supports_audience_content_access_and_status_filters_without_private_fields(): void
    {
        $response = $this->getJson('/api/v1/external-resources?audience=guest&content_type=scientific_articles&access_scope=guest&status=active&lang=ru')
            ->assertOk()
            ->assertJsonPath('meta.audiences.guest', 'Гость')
            ->assertJsonPath('meta.access_scopes.campus', 'Только из кампуса');

        $this->assertGreaterThan(0, $response->json('meta.total'));
        foreach ($response->json('data') as $resource) {
            $this->assertContains('guest', $resource['available_roles']);
            $this->assertContains('scientific_articles', $resource['content_types']);
            $this->assertTrue($resource['guest_access']);
            $this->assertSame('active', $resource['status']);
            $this->assertArrayNotHasKey('internal_notes', $resource);
            $this->assertArrayNotHasKey('licence_file_path', $resource);
            $this->assertArrayNotHasKey('contract_number', $resource);
            $this->assertArrayNotHasKey('health_check_url', $resource);
        }

        $this->getJson('/api/v1/external-resources?resource_type=partner')->assertJsonPath('meta.total', 0);
    }

    public function test_librarian_prepares_and_director_approves_only_complete_resource(): void
    {
        $librarian = $this->makeControlPlaneUser('librarian');
        $this->signInToLibraryAs($librarian);
        $this->get('/admin/external-resources')->assertOk();

        $this->post('/admin/external-resources', $this->validPayload([
            'title' => 'Проверяемая лицензионная база',
            'publication_status' => 'published',
        ]))->assertRedirect();
        $resource = ExternalResource::query()->where('title', 'Проверяемая лицензионная база')->firstOrFail();
        $this->assertSame('draft', $resource->publication_status);
        $this->assertFalse($resource->is_active);

        $this->post(route('admin.external-resources.workflow', $resource), ['action' => 'submit_review'])
            ->assertRedirect();
        $this->assertSame('review', $resource->refresh()->publication_status);

        $director = $this->makeControlPlaneUser('director');
        $this->signInToLibraryAs($director)
            ->get('/librarian/external-resources/review')
            ->assertOk()
            ->assertSee('Проверяемая лицензионная база', false);

        $this->post(route('librarian.external-resources.workflow', $resource), ['action' => 'publish'])
            ->assertSessionHasErrors('publication_status');
        $this->assertSame('review', $resource->refresh()->publication_status);

        // This simulates a verified contract date entered by an authorised
        // contract manager; no date is fabricated by seeding or migration.
        $resource->update(['contract_ends_at' => today('UTC')->addYear()->toDateString()]);
        $this->post(route('librarian.external-resources.workflow', $resource), ['action' => 'publish'])
            ->assertRedirect();
        $this->assertSame('published', $resource->refresh()->publication_status);
        $this->assertTrue($resource->is_active);

        $this->post(route('librarian.external-resources.workflow', $resource), [
            'action' => 'suspend', 'reason' => 'Временная техническая приостановка',
        ])->assertRedirect();
        $this->assertFalse($resource->refresh()->is_active);
        $this->get(route('resources.show', $resource->slug))->assertNotFound();

        $this->post(route('librarian.external-resources.workflow', $resource), ['action' => 'resume'])->assertRedirect();
        $this->assertTrue($resource->refresh()->is_active);
    }

    public function test_librarian_navigation_is_real_and_senior_has_no_dead_admin_permission(): void
    {
        $librarian = $this->makeControlPlaneUser('librarian');
        $this->signInToLibraryAs($librarian)
            ->get('/librarian')
            ->assertOk()
            ->assertSee('/admin/external-resources', false);

        $senior = $this->makeControlPlaneUser('senior_librarian');
        $this->assertFalse($senior->can('external_resources.manage'));
        $this->signInToLibraryAs($senior)->get('/admin/external-resources')->assertForbidden();
    }

    public function test_safe_redirect_records_success_denial_and_internal_navigation(): void
    {
        $open = ExternalResource::query()->where('slug', 'doaj')->firstOrFail();
        $this->get(route('external-resources.open', $open))->assertRedirect('https://doaj.org/');

        $licensed = $this->createPublishedResource([
            'slug' => 'published-restricted-redirect',
            'resource_type' => 'licensed',
            'guest_access' => false,
            'available_roles' => ['student', 'teacher', 'library_staff'],
            'access_type' => 'remote_auth',
            'access_method' => 'personal_account',
            'login_required' => true,
            'contract_ends_at' => today('UTC')->addYear()->toDateString(),
        ]);
        $this->get(route('external-resources.open', $licensed))->assertForbidden();

        $draft = ExternalResource::query()->where('slug', 'ipr-smart')->firstOrFail();
        $this->get(route('external-resources.open', $draft))->assertNotFound();

        $internal = ExternalResource::query()->where('slug', 'kazutb-catalogue')->firstOrFail();
        $this->get(route('external-resources.open', $internal))->assertRedirect('/catalog');

        $this->actingAs($this->adminUser)
            ->get(route('external-resources.open', $open))->assertRedirect('https://doaj.org/');

        $this->assertDatabaseHas('external_resource_events', ['external_resource_id' => $open->id, 'event_type' => 'outbound_click']);
        $this->assertSame(0, ExternalResourceEvent::query()
            ->where('external_resource_id', $open->id)
            ->where('event_type', 'outbound_click')
            ->whereNotNull('user_id')->count());
        $this->assertDatabaseHas('external_resource_events', ['external_resource_id' => $licensed->id, 'event_type' => 'access_denied']);
        $this->assertDatabaseMissing('external_resource_events', ['external_resource_id' => $draft->id]);
    }

    public function test_public_title_is_escaped_and_strict_destination_validation_rejects_private_aliases(): void
    {
        $payload = '</title><script>window.externalResourceXss=1</script>';
        $resource = $this->createPublishedResource([
            'slug' => 'escaped-resource-title',
            'title' => $payload,
        ]);

        $response = $this->get(route('resources.show', $resource->slug))->assertOk();
        $response->assertDontSee($payload, false)
            ->assertSee('&lt;/title&gt;&lt;script&gt;window.externalResourceXss=1&lt;/script&gt;', false);

        foreach ([
            'http://example.org/resource',
            'https://127.1/resource',
            'https://0177.0.0.1/resource',
            'https://127.0.0.1./resource',
            'https://example.org:444/resource',
            'https://user:secret@example.org/resource',
            'https://example.org/resource?access_token=secret',
        ] as $destination) {
            $this->assertFalse(ExternalResource::isSafeDestination($destination, 'licensed'), $destination);
        }
        $this->assertTrue(ExternalResource::isSafeDestination('https://example.org/resource?subject=law', 'licensed'));
        $this->assertFalse(ExternalResource::isSafeHealthDestination('https://example.org/resource?subject=law', 'licensed'));
        $this->assertTrue(ExternalResource::isSafeHealthDestination('https://example.org/health', 'licensed'));
    }

    public function test_draft_open_route_is_404_before_analytics_and_expired_resource_stays_discoverable(): void
    {
        $draft = $this->createPublishedResource([
            'slug' => 'private-draft-open',
            'publication_status' => 'draft',
            'is_active' => false,
            'published_at' => null,
        ]);
        $this->get(route('external-resources.open', $draft))->assertNotFound();
        $this->assertDatabaseMissing('external_resource_events', [
            'external_resource_id' => $draft->getKey(),
        ]);

        $expired = $this->createPublishedResource([
            'slug' => 'expired-public-card',
            'title' => 'Expired transparent resource',
            'resource_type' => 'licensed',
            'guest_access' => false,
            'available_roles' => ['student', 'teacher', 'library_staff'],
            'access_type' => 'remote_auth',
            'access_method' => 'personal_account',
            'login_required' => true,
            'contract_ends_at' => today('UTC')->subDay()->toDateString(),
        ]);

        $this->assertSame('expired', $expired->accessStatus());
        $this->get('/resources?lang=en')
            ->assertOk()
            ->assertSee('Expired transparent resource', false)
            ->assertSee('Expired', false);
        $this->get(route('resources.show', $expired->slug))->assertOk();
        $this->get(route('external-resources.open', $expired))->assertForbidden();
        $this->assertDatabaseHas('external_resource_events', [
            'external_resource_id' => $expired->getKey(),
            'event_type' => 'expired_click',
        ]);
    }

    public function test_public_detail_and_open_routes_have_a_named_rate_limiter(): void
    {
        $this->assertNotNull(RateLimiter::limiter('external-resources'));
        foreach (['resources.show', 'external-resources.open'] as $routeName) {
            $route = Route::getRoutes()->getByName($routeName);
            $this->assertNotNull($route);
            $this->assertContains('throttle:external-resources', $route->gatherMiddleware());
        }
    }

    public function test_admin_content_update_cannot_keep_resource_published_and_director_cannot_publish_draft(): void
    {
        $resource = $this->createPublishedResource(['slug' => 'workflow-bypass-guard']);
        $this->signInToLibraryAs($this->adminUser);
        $this->patch(route('admin.external-resources.update', $resource), $this->validPayload([
            'title' => 'Changed published resource',
            'resource_type' => 'open_access',
            'available_roles' => ExternalResource::AUDIENCES,
            'access_type' => 'open',
            'access_method' => 'public_url',
            'login_required' => '0',
            'publication_status' => 'published',
            'is_active' => '1',
        ]))->assertRedirect();
        $this->assertSame('review', $resource->refresh()->publication_status);
        $this->assertFalse($resource->is_active);

        $resource->forceFill(['publication_status' => 'draft'])->save();
        $director = $this->makeControlPlaneUser('director');
        $this->signInToLibraryAs($director);
        $this->post(route('librarian.external-resources.workflow', $resource), ['action' => 'publish'])
            ->assertSessionHasErrors('action');
        $this->assertSame('draft', $resource->refresh()->publication_status);
    }

    public function test_contract_fields_are_server_side_permission_gated(): void
    {
        $resource = ExternalResource::query()->where('slug', 'doaj')->firstOrFail();
        $resource->forceFill([
            'contract_number' => 'PRIVATE-CONTRACT',
            'internal_notes' => 'PRIVATE INTERNAL NOTE',
        ])->save();

        $librarian = $this->makeControlPlaneUser('librarian');
        $this->signInToLibraryAs($librarian)
            ->get(route('admin.external-resources.edit', $resource))
            ->assertOk()
            ->assertDontSee('PRIVATE-CONTRACT', false)
            ->assertDontSee('PRIVATE INTERNAL NOTE', false)
            ->assertDontSee('name="contract_number"', false)
            ->assertDontSee('name="internal_notes"', false);

        $this->patch(route('admin.external-resources.update', $resource), $this->validPayload([
            'title' => $resource->title,
            'resource_type' => 'open_access',
            'available_roles' => ExternalResource::AUDIENCES,
            'access_type' => 'open',
            'access_method' => 'public_url',
            'login_required' => '0',
            'contract_number' => 'ATTACKER-REPLACEMENT',
            'internal_notes' => 'ATTACKER NOTE',
            'vendor_contact' => 'attacker@example.test',
            'statistics_url' => 'https://attacker.example.test/stats',
        ]))->assertRedirect();

        $resource->refresh();
        $this->assertSame('PRIVATE-CONTRACT', $resource->contract_number);
        $this->assertSame('PRIVATE INTERNAL NOTE', $resource->internal_notes);
        $this->assertNull($resource->vendor_contact);
        $this->assertNull($resource->statistics_url);
    }

    public function test_campus_only_rule_and_click_analytics(): void
    {
        config(['digital_access.campus_ranges' => ['203.0.113.0/24']]);
        $resource = $this->createPublishedResource([
            'slug' => 'campus-only-resource',
            'resource_type' => 'licensed',
            'guest_access' => true,
            'available_roles' => ExternalResource::AUDIENCES,
            'access_type' => 'campus',
            'access_method' => 'campus_only',
            'campus_only' => true,
            'contract_ends_at' => today('UTC')->addYear()->toDateString(),
        ]);

        $this->withServerVariables(['REMOTE_ADDR' => '198.51.100.10'])
            ->get(route('external-resources.open', $resource))->assertForbidden();
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.42'])
            ->get(route('external-resources.open', $resource))->assertRedirect($resource->url);
        $this->assertSame(1, ExternalResourceEvent::query()
            ->where('external_resource_id', $resource->id)
            ->where('event_type', 'access_denied')->count());
        $this->assertSame(1, ExternalResourceEvent::query()
            ->where('external_resource_id', $resource->id)
            ->where('event_type', 'outbound_click')->count());
    }

    public function test_logo_upload_accepts_real_images_and_rejects_disguised_scripts(): void
    {
        Storage::fake('public');
        $this->signInToLibraryAs($this->adminUser);
        $base = $this->validPayload([
            'resource_type' => 'open_access',
            'available_roles' => ExternalResource::AUDIENCES,
            'access_type' => 'open',
            'access_method' => 'public_url',
            'login_required' => '0',
        ]);

        $this->post(route('admin.external-resources.store'), [
            ...$base,
            'title' => 'Malicious logo resource',
            'logo' => UploadedFile::fake()->createWithContent('payload.png', '<script>alert(1)</script>'),
        ])->assertSessionHasErrors('logo');

        $this->post(route('admin.external-resources.store'), [
            ...$base,
            'title' => 'Valid logo resource',
            'logo' => UploadedFile::fake()->createWithContent(
                'logo.png',
                base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mNk+A8AAQUBAScY42YAAAAASUVORK5CYII=', true),
            ),
        ])->assertRedirect();
        $resource = ExternalResource::query()->where('title', 'Valid logo resource')->firstOrFail();
        $this->assertNotNull($resource->logo_path);
        Storage::disk('public')->assertExists($resource->logo_path);
    }

    public function test_health_check_is_anonymous_updates_fields_and_notifies_admin_on_outage_only(): void
    {
        $healthy = $this->createPublishedResource([
            'slug' => 'health-public',
            'url' => 'https://93.184.216.34/health',
            'resource_type' => 'open_access',
            'guest_access' => true,
            'available_roles' => ExternalResource::AUDIENCES,
        ]);
        Http::fake(['https://93.184.216.34/*' => Http::response('', 204)]);
        $this->artisan('library:external-resources:health-check', ['--resource' => $healthy->slug])->assertSuccessful();
        Http::assertSent(function (ClientRequest $request): bool {
            return $request->method() === 'HEAD'
                && ! $request->hasHeader('Authorization')
                && ! $request->hasHeader('Cookie');
        });
        $this->assertSame('healthy', $healthy->refresh()->health_status);
        $this->assertNotNull($healthy->last_checked_at);

        $broken = $this->createPublishedResource([
            'slug' => 'health-broken-internal',
            'url' => '/definitely-not-a-route',
            'resource_type' => 'internal',
        ]);
        $this->artisan('library:external-resources:health-check', ['--resource' => $broken->slug])->assertSuccessful();
        $this->artisan('library:external-resources:health-check', ['--resource' => $broken->slug])->assertSuccessful();
        $this->assertSame('unavailable', $broken->refresh()->health_status);
        $this->assertSame(
            User::role('admin')->count(),
            ReaderNotification::query()->where('event_type', 'external_resource_health')->count(),
        );
        $this->assertDatabaseHas('external_resource_events', ['external_resource_id' => $broken->id, 'event_type' => 'health_check']);
        $this->assertSame(1, ExternalResourceEvent::query()
            ->where('external_resource_id', $broken->id)
            ->where('event_type', 'health_outage')->count());
        $this->assertSame(1, ExternalResourceNotificationOutbox::query()
            ->where('external_resource_id', $broken->id)
            ->where('notification_type', 'health_outage')
            ->where('status', 'delivered')->count());

        $firstIncident = $broken->refresh()->health_incident_id;
        $this->assertNotNull($firstIncident);
        $broken->update(['url' => '/catalog']);
        $this->artisan('library:external-resources:health-check', ['--resource' => $broken->slug])->assertSuccessful();
        $this->assertSame('healthy', $broken->refresh()->health_status);
        $this->assertNull($broken->health_incident_id);

        $broken->update(['url' => '/definitely-not-a-route']);
        $this->artisan('library:external-resources:health-check', ['--resource' => $broken->slug])->assertSuccessful();
        $this->assertNotSame($firstIncident, $broken->refresh()->health_incident_id);
        $this->assertSame(2, ExternalResourceNotificationOutbox::query()
            ->where('external_resource_id', $broken->id)
            ->where('notification_type', 'health_outage')
            ->where('status', 'delivered')->count());
    }

    public function test_health_check_never_requests_a_url_with_query_credentials(): void
    {
        $resource = $this->createPublishedResource([
            'slug' => 'health-credential-free-required',
            'url' => 'https://93.184.216.34/resource?subject=law',
            'health_check_url' => null,
        ]);
        Http::fake();

        $this->artisan('library:external-resources:health-check', ['--resource' => $resource->slug])
            ->assertSuccessful();

        Http::assertNothingSent();
        $this->assertSame('degraded', $resource->refresh()->health_status);
        $this->assertDatabaseHas('external_resource_events', [
            'external_resource_id' => $resource->getKey(),
            'event_type' => 'health_check',
        ]);
    }

    public function test_expiry_reminders_are_configurable_and_deduplicated_per_contract_date(): void
    {
        config(['digital_library.external_resource_expiry_notice_days' => [30, 7, 7, -1, 99999]]);
        $director = $this->makeControlPlaneUser('director');
        $resource = $this->createPublishedResource([
            'slug' => 'partner-reminder',
            'resource_type' => 'partner',
            'contract_ends_at' => today('UTC')->addDays(6)->toDateString(),
            'available_roles' => ['student', 'teacher', 'library_staff'],
            'responsible_user_id' => $director->getKey(),
        ]);

        $this->artisan('library:digital-services-sweep')->assertSuccessful();
        $this->artisan('library:digital-services-sweep')->assertSuccessful();
        $this->assertSame(1, ExternalResourceEvent::query()
            ->where('external_resource_id', $resource->id)
            ->where('event_type', 'licence_notice_7')->count());
        $this->assertSame(1, ReaderNotification::query()
            ->where('user_id', $director->id)
            ->where('event_type', 'external_resource_licence')->count());
        $this->assertSame(1, ExternalResourceNotificationOutbox::query()
            ->where('external_resource_id', $resource->id)
            ->where('notification_type', 'licence_expiry')
            ->where('status', 'delivered')->count());

        $outbox = ExternalResourceNotificationOutbox::query()
            ->where('external_resource_id', $resource->id)
            ->where('notification_type', 'licence_expiry')->firstOrFail();
        $outbox->forceFill([
            'status' => 'failed',
            'processed_at' => null,
            'available_at' => now('UTC')->subMinute(),
        ])->save();
        $this->artisan('library:external-resources:notifications')->assertSuccessful();
        $this->assertSame('delivered', $outbox->refresh()->status);
        $this->assertSame(1, ReaderNotification::query()
            ->where('user_id', $director->id)
            ->where('event_type', 'external_resource_licence')->count());

        $resource->update(['contract_ends_at' => today('UTC')->addDays(5)->toDateString()]);
        $this->artisan('library:digital-services-sweep')->assertSuccessful();
        $this->assertSame(2, ExternalResourceEvent::query()
            ->where('external_resource_id', $resource->id)
            ->where('event_type', 'licence_notice_7')->count());
        $this->assertSame(2, ReaderNotification::query()
            ->where('user_id', $director->id)
            ->where('event_type', 'external_resource_licence')->count());
    }

    public function test_card_views_are_daily_deduplicated_and_expired_analytics_are_pruned(): void
    {
        $resource = $this->createPublishedResource(['slug' => 'analytics-retention']);
        $this->get(route('resources.show', $resource->slug))->assertOk();
        $this->get(route('resources.show', $resource->slug))->assertOk();

        $this->assertSame(1, ExternalResourceEvent::query()
            ->where('external_resource_id', $resource->id)
            ->where('event_type', 'card_view')->count());

        $this->actingAs($this->adminUser)->get(route('resources.show', $resource->slug))->assertOk();
        $this->actingAs($this->adminUser)->get(route('resources.show', $resource->slug))->assertOk();
        $this->assertSame(2, ExternalResourceEvent::query()
            ->where('external_resource_id', $resource->id)
            ->where('event_type', 'card_view')->count());
        $this->assertSame(0, ExternalResourceEvent::query()
            ->where('external_resource_id', $resource->id)
            ->where('event_type', 'card_view')
            ->whereNotNull('user_id')->count());

        $event = ExternalResourceEvent::query()
            ->where('external_resource_id', $resource->id)
            ->where('event_type', 'card_view')->firstOrFail();
        $this->assertNotNull($event->dedupe_key);
        $this->assertNotNull($event->retention_until);

        $event->forceFill(['retention_until' => now('UTC')->subSecond()])->save();
        $this->artisan('library:external-resources:prune-events')->assertSuccessful();
        $this->assertDatabaseMissing('external_resource_events', ['id' => $event->id]);
    }

    public function test_public_filter_metadata_and_searchable_card_copy_are_present(): void
    {
        $response = $this->get('/resources?lang=en')->assertOk();
        $response
            ->assertSee('data-resource-search', false)
            ->assertSee('data-resource-facet="audience"', false)
            ->assertSee('data-resource-facet="content"', false)
            ->assertSee('data-resource-facet="status"', false)
            ->assertSee('data-resource-type="open_access"', false)
            ->assertSee('Directory of Open Access Journals', false)
            ->assertSee('international directory', false);
    }

    public function test_additive_hardening_migration_backfills_legacy_reminder_dedupe_and_retention(): void
    {
        $resource = ExternalResource::query()->where('slug', 'doaj')->firstOrFail();
        $legacyMetadata = json_encode([
            'expiry_date' => '2027-06-30',
            'threshold_days' => 30,
            'days_remaining' => 20,
        ]);
        foreach ([1, 2] as $offset) {
            DB::table('external_resource_events')->insert([
                'external_resource_id' => $resource->id,
                'event_type' => 'licence_notice_30',
                'role_name' => 'system',
                'metadata' => $legacyMetadata,
                'created_at' => now('UTC')->subMinutes($offset),
            ]);
        }
        DB::table('external_resource_events')->insert([
            'external_resource_id' => $resource->id,
            'event_type' => 'card_view',
            'role_name' => 'guest',
            'metadata' => json_encode(['source' => 'legacy']),
            'created_at' => now('UTC')->subDay(),
        ]);

        $migration = require base_path('database/migrations/2026_08_12_220000_harden_external_resource_operations.php');
        $migration->down();
        $migration->up();

        $this->assertSame(1, DB::table('external_resource_events')
            ->where('event_type', 'licence_notice_30')
            ->whereNotNull('dedupe_key')->count());
        $this->assertSame(1, DB::table('external_resource_notification_outboxes')
            ->where('notification_type', 'licence_expiry')
            ->where('status', 'delivered')->count());
        $this->assertNotNull(DB::table('external_resource_events')
            ->where('event_type', 'card_view')->value('retention_until'));
    }

    /** @param array<string, mixed> $overrides */
    private function createPublishedResource(array $overrides = []): ExternalResource
    {
        return ExternalResource::query()->create(array_replace([
            'slug' => 'published-'.str()->lower(str()->random(8)),
            'title' => 'Published resource',
            'resource_type' => 'open_access',
            'description' => 'Complete public description.',
            'available_roles' => ExternalResource::AUDIENCES,
            'content_types' => ['scientific_articles'],
            'access_instructions' => 'Search and open the requested material.',
            'url' => 'https://example.org/resource',
            'access_type' => 'open',
            'access_method' => 'public_url',
            'guest_access' => true,
            'campus_only' => false,
            'login_required' => false,
            'is_active' => true,
            'publication_status' => 'published',
            'published_at' => now(),
        ], $overrides));
    }

    /** @param array<string, mixed> $overrides @return array<string, mixed> */
    private function validPayload(array $overrides = []): array
    {
        return array_replace([
            'title' => 'Новый внешний ресурс',
            'provider' => 'Test provider',
            'resource_type' => 'licensed',
            'description' => 'Полное описание содержимого ресурса.',
            'url' => 'https://example.org/resource',
            'available_roles' => ['student', 'teacher', 'library_staff'],
            'content_types' => ['electronic_books', 'catalogues'],
            'access_type' => 'remote_auth',
            'access_method' => 'personal_account',
            'access_instructions' => 'Войдите с библиотечной учётной записью и выполните поиск.',
            'is_active' => '1',
            'campus_only' => '0',
            'login_required' => '1',
            'publication_status' => 'review',
            'sort_order' => 500,
        ], $overrides);
    }
}
