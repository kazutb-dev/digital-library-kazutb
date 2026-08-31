<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\AnnualContentPlan;
use App\Models\AnnualContentPlanItem;
use App\Models\News;
use App\Models\NewsCategory;
use App\Services\News\NewsAnalyticsService;
use App\Services\News\NewsContentSanitizer;
use App\Services\News\NewsWorkflowService;
use Database\Seeders\NewsCategorySeeder;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class NewsEditorialWorkflowTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        app(NewsCategorySeeder::class)->run();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_librarian_submits_and_director_approves_but_author_cannot_self_approve(): void
    {
        $librarian = $this->makeControlPlaneUser('librarian');
        $director = $this->makeControlPlaneUser('director');
        $news = $this->readyPublication($librarian->getKey());
        $workflow = app(NewsWorkflowService::class);
        $workflow->transition($news, 'pending_review', $librarian);
        $this->assertSame('pending_review', $news->refresh()->status);
        $workflow->transition($news, 'approved', $director);
        $this->assertSame('approved', $news->refresh()->status);
        $this->assertSame($director->getKey(), $news->approved_by);

        $own = $this->readyPublication($director->getKey());
        $workflow->transition($own, 'pending_review', $director);
        $this->expectException(ValidationException::class);
        $workflow->transition($own, 'approved', $director);
    }

    public function test_editorial_schema_is_available_in_the_canonical_control_plane(): void
    {
        $this->assertTrue(Schema::hasTable('news_categories'));
        $this->assertTrue(Schema::hasColumns('news', [
            'public_id', 'type', 'title_ru', 'content_ru', 'slug_ru',
        ]));
    }

    public function test_librarian_has_no_direct_publication_permission(): void
    {
        $librarian = $this->makeControlPlaneUser('librarian');
        $this->assertFalse($librarian->can('news.publish'));
        $news = $this->readyPublication($librarian->getKey(), ['status' => 'approved', 'approved_by' => $this->adminUser->getKey(), 'approved_at' => now()]);
        $this->expectException(AuthorizationException::class);
        app(NewsWorkflowService::class)->transition($news, 'published', $librarian);
    }

    public function test_incomplete_event_cannot_be_submitted(): void
    {
        $librarian = $this->makeControlPlaneUser('librarian');
        $news = $this->readyPublication($librarian->getKey(), ['starts_at' => null, 'venue' => null, 'online_url' => null]);
        $this->expectException(ValidationException::class);
        app(NewsWorkflowService::class)->transition($news, 'pending_review', $librarian);
    }

    public function test_changes_requested_requires_comment(): void
    {
        $librarian = $this->makeControlPlaneUser('librarian');
        $director = $this->makeControlPlaneUser('director');
        $news = $this->readyPublication($librarian->getKey());
        app(NewsWorkflowService::class)->transition($news, 'pending_review', $librarian);
        $this->expectException(ValidationException::class);
        app(NewsWorkflowService::class)->transition($news, 'changes_requested', $director);
    }

    public function test_scheduled_publication_is_idempotent_and_requires_approval(): void
    {
        $librarian = $this->makeControlPlaneUser('librarian');
        $director = $this->makeControlPlaneUser('director');
        $workflow = app(NewsWorkflowService::class);
        $news = $this->readyPublication($librarian->getKey());
        $workflow->transition($news, 'pending_review', $librarian);
        $workflow->transition($news, 'approved', $director);
        $workflow->transition($news, 'scheduled', $director, ['scheduled_publish_at' => now()->addMinute()]);
        $news->update(['scheduled_publish_at' => now()->subMinute(), 'publish_at' => now()->subMinute()]);
        $this->assertTrue($workflow->publishDue($news));
        $this->assertFalse($workflow->publishDue($news));
        $this->assertSame('published', $news->refresh()->status);
    }

    public function test_public_routes_hide_drafts_and_fallback_to_kazakh(): void
    {
        $author = $this->makeControlPlaneUser('librarian');
        $draft = $this->readyPublication($author->getKey(), ['slug' => 'private-draft', 'slug_kk' => 'private-draft']);
        $this->get('/news/private-draft?lang=ru')->assertNotFound();
        $published = $this->readyPublication($author->getKey(), ['slug' => 'fallback-material', 'slug_kk' => 'fallback-material', 'status' => 'published', 'published_at' => now(), 'publish_at' => now(), 'approved_by' => $this->adminUser->getKey(), 'published_by' => $this->adminUser->getKey(), 'title_ru' => null, 'content_ru' => null, 'type' => 'announcement', 'category' => 'announcement', 'category_id' => NewsCategory::query()->where('slug', 'technical')->value('id'), 'starts_at' => null, 'ends_at' => null, 'venue' => null]);
        $this->get('/news/fallback-material?lang=ru')->assertOk()->assertSee($published->title_kk);
    }

    public function test_news_surface_excludes_events_and_redirects_their_news_urls(): void
    {
        $author = $this->makeControlPlaneUser('librarian');
        $published = [
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'publish_at' => now()->subMinute(),
            'approved_by' => $this->adminUser->getKey(),
            'published_by' => $this->adminUser->getKey(),
        ];
        $announcement = $this->readyPublication($author->getKey(), array_merge($published, [
            'slug' => 'editorial-announcement',
            'slug_kk' => 'editorial-announcement',
            'slug_en' => 'editorial-announcement',
            'title' => 'Editorial announcement',
            'title_kk' => 'Editorial announcement',
            'title_en' => 'Editorial announcement',
            'type' => 'announcement',
            'category' => 'announcement',
            'category_id' => NewsCategory::query()->where('slug', 'technical')->value('id'),
            'starts_at' => null,
            'ends_at' => null,
            'venue' => null,
        ]));
        $event = $this->readyPublication($author->getKey(), array_merge($published, [
            'slug' => 'calendar-event',
            'slug_kk' => 'calendar-event',
            'slug_en' => 'calendar-event',
            'title' => 'Calendar event',
            'title_kk' => 'Calendar event',
            'title_en' => 'Calendar event',
        ]));
        $schedule = $this->readyPublication($author->getKey(), array_merge($published, [
            'slug' => 'calendar-schedule',
            'slug_kk' => 'calendar-schedule',
            'slug_en' => 'calendar-schedule',
            'title' => 'Calendar schedule',
            'title_kk' => 'Calendar schedule',
            'title_en' => 'Calendar schedule',
            'type' => 'schedule',
            'category' => 'schedule',
        ]));

        $this->get('/news?lang=en')
            ->assertOk()
            ->assertSee($announcement->title_en)
            ->assertDontSee($event->title_en)
            ->assertDontSee($schedule->title_en)
            ->assertDontSee('value="event"', false)
            ->assertDontSee('value="schedule"', false);
        $this->get('/news/editorial-announcement?lang=en')
            ->assertOk()
            ->assertDontSee($event->title_en);
        $this->get('/news/calendar-event?lang=en')
            ->assertStatus(301)
            ->assertRedirect('/events/calendar-event?lang=en');
        $this->get('/news/calendar-schedule?lang=en')
            ->assertStatus(301)
            ->assertRedirect('/events/calendar-schedule?lang=en');
    }

    public function test_event_online_location_requires_a_valid_http_url_or_venue(): void
    {
        $author = $this->makeControlPlaneUser('librarian');
        $event = $this->readyPublication($author->getKey(), [
            'slug' => 'online-location-contract',
            'slug_kk' => 'online-location-contract',
            'slug_en' => 'online-location-contract',
            'title' => 'Location contract event',
            'title_kk' => 'Location contract event',
            'title_en' => 'Location contract event',
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'publish_at' => now()->subMinute(),
            'approved_by' => $this->adminUser->getKey(),
            'published_by' => $this->adminUser->getKey(),
            'venue' => null,
            'venue_kk' => null,
            'venue_en' => null,
            'online_url' => 'javascript:alert(1)',
        ]);

        $this->get('/events?lang=en')
            ->assertOk()
            ->assertSee($event->title_en)
            ->assertDontSee('Online')
            ->assertDontSee('javascript:');
        $this->get('/events/online-location-contract?lang=en')
            ->assertOk()
            ->assertDontSee('VirtualLocation')
            ->assertDontSee('javascript:');

        $event->update(['online_url' => 'https://events.example.test/session']);

        $this->get('/events?lang=en')->assertOk()->assertSee('Online');
        $this->get('/events/online-location-contract?lang=en')
            ->assertOk()
            ->assertSee('https://events.example.test/session', false)
            ->assertSee('VirtualLocation');
    }

    public function test_registration_click_reapplies_publication_type_visibility_and_url_rules(): void
    {
        $author = $this->makeControlPlaneUser('librarian');
        $published = [
            'status' => 'published',
            'published_at' => now()->subMinute(),
            'publish_at' => now()->subMinute(),
            'approved_by' => $this->adminUser->getKey(),
            'published_by' => $this->adminUser->getKey(),
            'registration_url' => 'https://events.example.test/register',
        ];
        $event = $this->readyPublication($author->getKey(), $published);
        $announcement = $this->readyPublication($author->getKey(), array_merge($published, [
            'type' => 'announcement',
            'category' => 'announcement',
            'category_id' => NewsCategory::query()->where('slug', 'technical')->value('id'),
            'starts_at' => null,
            'ends_at' => null,
            'venue' => null,
        ]));
        $membersEvent = $this->readyPublication($author->getKey(), array_merge($published, [
            'visibility' => 'members',
        ]));
        $unsafeEvent = $this->readyPublication($author->getKey(), array_merge($published, [
            'registration_url' => 'javascript:alert(1)',
        ]));

        $this->post(route('news.registration-click', $event))
            ->assertRedirect('https://events.example.test/register');
        $this->assertSame(1, $event->refresh()->registration_click_count);

        $this->post(route('news.registration-click', $announcement))->assertNotFound();
        $this->post(route('news.registration-click', $membersEvent))->assertNotFound();
        $this->post(route('news.registration-click', $unsafeEvent))->assertNotFound();
        $this->assertSame(0, $announcement->refresh()->registration_click_count);
        $this->assertSame(0, $membersEvent->refresh()->registration_click_count);
        $this->assertSame(0, $unsafeEvent->refresh()->registration_click_count);
    }

    public function test_html_sanitizer_removes_scripts_and_unsafe_urls(): void
    {
        $kazakh = 'Ә ә Ғ ғ Қ қ Ң ң Ө ө Ұ ұ Ү ү Һ һ І і';
        $clean = app(NewsContentSanitizer::class)->sanitize('<p>Hello '.$kazakh.'</p><script>alert(1)</script><a href="javascript:alert(2)" onclick="bad()">link</a>');
        $this->assertStringNotContainsString('script', $clean);
        $this->assertStringNotContainsString('javascript:', $clean);
        $this->assertStringNotContainsString('onclick', $clean);
        $this->assertStringContainsString('<p>Hello '.$kazakh.'</p>', $clean);
    }

    public function test_staff_and_public_news_surfaces_render_without_raw_translation_keys(): void
    {
        $librarian = $this->makeControlPlaneUser('librarian');
        $this->signInToLibraryAs($librarian)->get('/librarian/news')->assertOk()->assertDontSee('news.editor.');
        $this->get('/librarian/news/create')->assertOk()->assertSee('Редакциялық үдеріс')->assertDontSee('news.fields.');
        $this->get('/librarian/news-calendar')->assertOk();
        $this->signInToLibraryAs($this->adminUser)->get('/admin/news')->assertOk();
        $this->get('/admin/news/create')->assertOk()->assertDontSee('news.validation.');
        $this->get('/events?lang=kk')->assertOk();
        $this->get('/news?lang=en')->assertOk();
    }

    public function test_announcing_plan_item_does_not_mark_event_completed(): void
    {
        $author = $this->makeControlPlaneUser('librarian');
        $director = $this->makeControlPlaneUser('director');
        $plan = AnnualContentPlan::query()->create(['year' => 2030, 'title' => '2030', 'status' => 'active', 'created_by' => $director->getKey(), 'approved_by' => $director->getKey(), 'approved_at' => now()]);
        $item = AnnualContentPlanItem::query()->create(['plan_id' => $plan->getKey(), 'item_number' => 1, 'type' => 'event', 'title_kk' => 'Көрме', 'planned_date' => '2030-04-10', 'status' => 'planned']);
        $news = $this->readyPublication($author->getKey(), ['annual_plan_item_id' => $item->getKey()]);
        $workflow = app(NewsWorkflowService::class);
        $workflow->transition($news, 'pending_review', $author);
        $workflow->transition($news, 'approved', $director);
        $workflow->transition($news, 'published', $director);
        $this->assertSame('announced', $item->refresh()->status);
        $this->assertNull($item->actual_date);
    }

    public function test_category_must_support_publication_type(): void
    {
        $librarian = $this->makeControlPlaneUser('librarian');
        $news = $this->readyPublication($librarian->getKey());
        $news->update(['category_id' => NewsCategory::query()->where('slug', 'new-arrivals')->value('id')]);

        $this->expectException(ValidationException::class);
        app(NewsWorkflowService::class)->transition($news, 'pending_review', $librarian);
    }

    public function test_server_autosave_records_a_revision_without_changing_workflow_status(): void
    {
        $librarian = $this->makeControlPlaneUser('librarian');
        $news = $this->readyPublication($librarian->getKey());
        $before = $news->revisions()->count();
        $payload = $this->editorPayload($news);
        $payload['title_kk'] = 'Серверде сақталған нобай';

        $this->signInToLibraryAs($librarian)
            ->postJson(route('librarian.news.autosave', $news), $payload)
            ->assertOk()
            ->assertJsonStructure(['saved_at']);

        $this->assertSame('draft', $news->refresh()->status);
        $this->assertSame('Серверде сақталған нобай', $news->title_kk);
        $this->assertSame($before + 1, $news->revisions()->count());
    }

    public function test_analytics_aggregate_daily_views_without_raw_visitor_identity(): void
    {
        $author = $this->makeControlPlaneUser('librarian');
        $news = $this->readyPublication($author->getKey(), ['status' => 'published', 'published_at' => now(), 'publish_at' => now()]);
        $request = Request::create('/news/'.$news->slug_kk, 'GET', server: [
            'REMOTE_ADDR' => '192.0.2.15',
            'HTTP_USER_AGENT' => 'NewsModuleTest/1.0',
            'HTTP_REFERER' => config('app.url').'/',
        ]);
        $analytics = app(NewsAnalyticsService::class);

        $analytics->recordView($news, $request);
        $analytics->recordView($news, $request);

        $this->assertSame(2, $news->refresh()->view_count);
        $this->assertSame(2, $news->homepage_click_count);
        $this->assertDatabaseCount('news_views', 1);
        $this->assertDatabaseHas('news_views', ['news_id' => $news->getKey(), 'views' => 2]);
    }

    public function test_scheduler_archives_expired_announcement_once_and_audits_it(): void
    {
        $author = $this->makeControlPlaneUser('librarian');
        $news = $this->readyPublication($author->getKey(), [
            'type' => 'announcement',
            'category' => 'announcement',
            'category_id' => NewsCategory::query()->where('slug', 'technical')->value('id'),
            'status' => 'published',
            'published_at' => now()->subDay(),
            'publish_at' => now()->subDay(),
            'expires_at' => now()->subMinute(),
        ]);
        $workflow = app(NewsWorkflowService::class);

        $this->assertTrue($workflow->archiveExpired($news));
        $this->assertFalse($workflow->archiveExpired($news));
        $this->assertSame('archived', $news->refresh()->status);
        $this->assertSame(1, ActivityLog::query()->where('action_type', 'news.archived')->where('entity_id', (string) $news->getKey())->count());
    }

    /** @param array<string,mixed> $overrides */
    private function readyPublication(int $authorId, array $overrides = []): News
    {
        $category = NewsCategory::query()->where('slug', 'events')->firstOrFail();

        return News::query()->create(array_merge(['slug' => 'item-'.str()->random(8), 'slug_kk' => 'item-'.str()->random(8), 'title' => 'Кітапхана іс-шарасы', 'title_kk' => 'Кітапхана іс-шарасы', 'excerpt' => 'Қысқаша сипаттама', 'excerpt_kk' => 'Қысқаша сипаттама', 'body' => 'Толық және пайдалы мәтін.', 'content_kk' => 'Толық және пайдалы мәтін.', 'category' => 'event', 'category_id' => $category->getKey(), 'type' => 'event', 'language' => 'kk', 'status' => 'draft', 'cover_image' => 'news-covers/test.webp', 'image_alt_kk' => 'Кітапханадағы іс-шара', 'audience' => 'Барлық оқырмандар', 'starts_at' => now()->addWeek(), 'ends_at' => now()->addWeek()->addHour(), 'timezone' => 'Asia/Almaty', 'venue' => 'Оқу залы', 'organizer' => 'Ғылыми кітапхана', 'contact_name' => 'Ақпарат бөлімі', 'visibility' => 'public', 'show_on_homepage' => true, 'created_by' => $authorId], $overrides));
    }

    /** @return array<string, mixed> */
    private function editorPayload(News $news): array
    {
        return [
            'type' => $news->type,
            'category_id' => $news->category_id,
            'title_kk' => $news->title_kk,
            'excerpt_kk' => $news->excerpt_kk,
            'content_kk' => $news->content_kk,
            'image_alt_kk' => $news->image_alt_kk,
            'audience' => $news->audience,
            'starts_at' => $news->starts_at?->toIso8601String(),
            'ends_at' => $news->ends_at?->toIso8601String(),
            'timezone' => $news->timezone,
            'venue' => $news->venue,
            'organizer' => $news->organizer,
            'contact_name' => $news->contact_name,
            'importance' => $news->importance ?? 'normal',
            'visibility' => $news->visibility ?? 'public',
            'show_on_homepage' => false,
            'is_featured' => false,
            'is_pinned' => false,
            'homepage_priority' => 0,
        ];
    }
}
