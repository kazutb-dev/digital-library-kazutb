<?php

namespace Tests\Feature;

use App\Models\News;
use App\Services\AuditLogger;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Illuminate\Support\Carbon;
use Mockery;
use RuntimeException;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class AdminMutationAtomicityTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_news_creation_rolls_back_when_required_audit_fails(): void
    {
        $audit = Mockery::mock(AuditLogger::class);
        $audit->shouldReceive('logRequired')
            ->once()
            ->andThrow(new RuntimeException('Injected audit sink failure.'));
        $this->app->instance(AuditLogger::class, $audit);
        $this->withoutExceptionHandling();

        try {
            $this->signInToLibraryAs($this->adminUser)->post('/admin/news', [
                'title' => 'Atomic news probe',
                'category' => 'announcement',
                'language' => 'ru',
                'body' => 'The record must not survive a mandatory audit failure.',
                'excerpt' => null,
                'status' => 'draft',
                'publish_at' => null,
                'show_on_homepage' => '0',
            ]);
            $this->fail('The injected audit failure was not propagated.');
        } catch (RuntimeException $exception) {
            $this->assertSame('Injected audit sink failure.', $exception->getMessage());
        }

        $this->assertDatabaseMissing('news', ['title' => 'Atomic news probe']);
    }

    public function test_non_publisher_cannot_reschedule_owned_news_through_content_form(): void
    {
        $editor = $this->makeControlPlaneUser('member');
        $editor->givePermissionTo('news.edit_own');
        $publishAt = Carbon::now('UTC')->addDay()->startOfMinute()->addSeconds(37);
        $news = News::query()->create([
            'title' => 'Owned scheduled item',
            'slug' => 'owned-scheduled-item',
            'category' => 'announcement',
            'language' => 'ru',
            'body' => 'Initial body.',
            'status' => 'scheduled',
            'publish_at' => $publishAt,
            'show_on_homepage' => false,
            'created_by' => $editor->getKey(),
            'published_by' => $this->adminUser->getKey(),
        ]);

        $this->signInToLibraryAs($editor)
            ->patch("/admin/news/{$news->getKey()}", $this->newsPayload([
                'body' => 'Attempted reschedule.',
                'publish_at' => $publishAt->copy()->addHour()->format('Y-m-d H:i:s'),
            ]))
            ->assertRedirect();

        $news->refresh();
        $this->assertSame('Attempted reschedule.', $news->body);
        $this->assertTrue($news->publish_at->equalTo($publishAt));
        $this->assertSame($this->adminUser->getKey(), $news->published_by);
    }

    public function test_non_publisher_can_edit_content_without_changing_publication_controls(): void
    {
        $editor = $this->makeControlPlaneUser('member');
        $editor->givePermissionTo('news.edit_own');
        $publishAt = Carbon::now('UTC')->addDay()->startOfMinute()->addSeconds(37);
        $news = News::query()->create([
            'title' => 'Owned controlled item',
            'slug' => 'owned-controlled-item',
            'category' => 'announcement',
            'language' => 'ru',
            'body' => 'Initial body.',
            'status' => 'scheduled',
            'publish_at' => $publishAt,
            'show_on_homepage' => false,
            'created_by' => $editor->getKey(),
            'published_by' => $this->adminUser->getKey(),
        ]);

        $this->signInToLibraryAs($editor)
            ->patch("/admin/news/{$news->getKey()}", $this->newsPayload([
                'body' => 'Corrected content.',
                // The browser control submits minute precision.
                'publish_at' => $publishAt->format('Y-m-d H:i'),
            ]))
            ->assertRedirect();

        $news->refresh();
        $this->assertSame('Corrected content.', $news->body);
        $this->assertTrue($news->publish_at->equalTo($publishAt));
        $this->assertSame($this->adminUser->getKey(), $news->published_by);
    }

    public function test_creation_form_cannot_bypass_editorial_workflow_with_published_status(): void
    {
        $this->signInToLibraryAs($this->adminUser)
            ->from('/admin/news/create')
            ->post('/admin/news', $this->newsPayload([
                'status' => 'published',
                'publish_at' => Carbon::now('UTC')->addDay()->format('Y-m-d H:i'),
            ]))
            ->assertRedirect('/admin/news/1/edit')
            ->assertSessionDoesntHaveErrors();

        $this->assertDatabaseHas('news', [
            'id' => 1,
            'status' => 'draft',
            'published_at' => null,
            'scheduled_publish_at' => null,
        ]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function newsPayload(array $overrides = []): array
    {
        return array_merge([
            'title' => 'News mutation probe',
            'category' => 'announcement',
            'language' => 'ru',
            'body' => 'Body.',
            'excerpt' => null,
            'status' => 'scheduled',
            'publish_at' => Carbon::now('UTC')->addDay()->format('Y-m-d H:i'),
            'show_on_homepage' => '0',
        ], $overrides);
    }
}
