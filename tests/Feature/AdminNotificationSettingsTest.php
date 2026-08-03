<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\NotificationSetting;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

class AdminNotificationSettingsTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    public function test_settings_page_lists_the_full_event_matrix(): void
    {
        $response = $this->signInToLibraryAs($this->adminUser)->get('/admin/settings');

        $response->assertOk();
        foreach (NotificationSetting::EVENT_TYPES as $eventType) {
            $response->assertSee($eventType);
        }
    }

    public function test_matrix_update_persists_channels_and_audits_changes(): void
    {
        $this
            ->signInToLibraryAs($this->adminUser)
            ->patch(route('admin.settings.notifications'), [
                'events' => [
                    'loan_overdue' => ['in_app' => '1'],
                    'news_published' => ['in_app' => '1', 'email' => '1'],
                ],
            ])
            ->assertRedirect();

        $overdue = NotificationSetting::query()->where('event_type', 'loan_overdue')->firstOrFail();
        $this->assertTrue($overdue->in_app_enabled);
        $this->assertFalse($overdue->email_enabled);

        $renewed = NotificationSetting::query()->where('event_type', 'loan_renewed')->firstOrFail();
        $this->assertFalse($renewed->in_app_enabled);
        $this->assertFalse($renewed->email_enabled);

        $news = NotificationSetting::query()->where('event_type', 'news_published')->firstOrFail();
        $this->assertTrue($news->in_app_enabled && $news->email_enabled);

        ActivityLog::query()
            ->where('action_type', 'update')
            ->where('entity_id', 'notification:loan_overdue')
            ->firstOrFail();
    }

    public function test_channel_helper_falls_back_to_enabled_for_unknown_events(): void
    {
        $this->assertTrue(NotificationSetting::channelEnabled('unknown_event', 'email'));

        NotificationSetting::query()
            ->where('event_type', 'loan_overdue')
            ->update(['email_enabled' => false]);
        $this->assertFalse(NotificationSetting::channelEnabled('loan_overdue', 'email'));
        $this->assertTrue(NotificationSetting::channelEnabled('loan_overdue', 'in_app'));
    }

    public function test_matrix_requires_system_settings_permission(): void
    {
        $editor = $this->makeControlPlaneUser('member');
        $editor->givePermissionTo('news.edit_any');

        $this->signInToLibraryAs($editor)
            ->patch(route('admin.settings.notifications'), ['events' => []])
            ->assertForbidden();
    }
}
