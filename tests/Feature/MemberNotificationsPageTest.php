<?php

namespace Tests\Feature;

use App\Models\Catalog\ReaderNotification;
use App\Models\User;
use Illuminate\Foundation\Http\Middleware\PreventRequestForgery;
use Tests\Concerns\BuildsAdminControlPlane;
use Tests\TestCase;

/**
 * Reader notification centre (Master.md §15.6). The feed is backed by real
 * ReaderNotification rows written by the circulation and reservation
 * services; ownership is enforced in the controller, not in the markup.
 */
class MemberNotificationsPageTest extends TestCase
{
    use BuildsAdminControlPlane;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpAdminControlPlane();
        $this->withoutMiddleware(PreventRequestForgery::class);
    }

    private function seedNotification(User $user, array $attributes = []): ReaderNotification
    {
        return ReaderNotification::query()->create(array_merge([
            'user_id' => $user->getKey(),
            'event_type' => 'reservation_ready',
            'title' => 'Материал готов к выдаче',
            'body' => '«Основы информатики» ожидает вас на кафедре выдачи.',
            'payload' => ['reservation_id' => 1],
        ], $attributes));
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $response = $this->get('/dashboard/notifications');

        $response->assertStatus(302);
        $response->assertRedirectContains('/login');
    }

    public function test_member_sees_own_notifications_from_the_database(): void
    {
        $member = $this->makeControlPlaneUser('member');
        $this->seedNotification($member);

        $response = $this->signInToLibraryAs($member)->get('/dashboard/notifications');

        $response->assertOk();
        $response->assertSee(__('librarian.member.notifications.title'), false);
        $response->assertSee('Материал готов к выдаче', false);
        $response->assertSee('«Основы информатики» ожидает вас на кафедре выдачи.', false);
        // Event-type label and the unread visual state come from real columns.
        $response->assertSee(__('librarian.member.notifications.events.reservation_ready'), false);
        $response->assertSee(__('librarian.member.notifications.unread'), false);
        $response->assertSee(__('librarian.member.notifications.unread_count', ['count' => 1]), false);
    }

    public function test_empty_state_is_shown_when_the_reader_has_no_notifications(): void
    {
        $member = $this->makeControlPlaneUser('member');

        $response = $this->signInToLibraryAs($member)->get('/dashboard/notifications');

        $response->assertOk();
        $response->assertSee(__('librarian.member.notifications.empty_title'), false);
        $response->assertDontSee(__('librarian.member.notifications.mark_all_read'), false);
    }

    public function test_member_does_not_see_another_readers_notification(): void
    {
        $member = $this->makeControlPlaneUser('member');
        $other = $this->makeControlPlaneUser('member');

        $this->seedNotification($member, ['title' => 'Моё уведомление']);
        $this->seedNotification($other, ['title' => 'Чужое уведомление']);

        $response = $this->signInToLibraryAs($member)->get('/dashboard/notifications');

        $response->assertOk();
        $response->assertSee('Моё уведомление', false);
        $response->assertDontSee('Чужое уведомление', false);
    }

    public function test_type_filter_narrows_the_feed_by_event_family(): void
    {
        $member = $this->makeControlPlaneUser('member');
        $this->seedNotification($member, ['event_type' => 'reservation_ready', 'title' => 'Бронь готова']);
        $this->seedNotification($member, ['event_type' => 'loan_overdue', 'title' => 'Просрочен возврат']);

        $this->signInToLibraryAs($member);

        $this->get('/dashboard/notifications?type=loans')
            ->assertOk()
            ->assertSee('Просрочен возврат', false)
            ->assertDontSee('Бронь готова', false);

        $this->get('/dashboard/notifications?type=reservations')
            ->assertOk()
            ->assertSee('Бронь готова', false)
            ->assertDontSee('Просрочен возврат', false);

        // An unknown family falls back to the unfiltered feed rather than 404.
        $this->get('/dashboard/notifications?type=not-a-family')
            ->assertOk()
            ->assertSee('Просрочен возврат', false)
            ->assertSee('Бронь готова', false);
    }

    public function test_member_can_mark_own_notification_as_read(): void
    {
        $member = $this->makeControlPlaneUser('member');
        $notification = $this->seedNotification($member);

        $this->signInToLibraryAs($member)
            ->post(route('member.notifications.read', $notification))
            ->assertRedirect();

        $this->assertNotNull($notification->fresh()->read_at);
    }

    public function test_cross_user_mark_read_is_forbidden(): void
    {
        $member = $this->makeControlPlaneUser('member');
        $other = $this->makeControlPlaneUser('member');
        $foreign = $this->seedNotification($other);

        $this->signInToLibraryAs($member)
            ->post(route('member.notifications.read', $foreign))
            ->assertForbidden();

        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_mark_all_read_only_touches_the_authenticated_reader(): void
    {
        $member = $this->makeControlPlaneUser('member');
        $other = $this->makeControlPlaneUser('member');

        $mine = $this->seedNotification($member);
        $alsoMine = $this->seedNotification($member, ['event_type' => 'loan_due_soon']);
        $foreign = $this->seedNotification($other);

        $this->signInToLibraryAs($member)
            ->post(route('member.notifications.read-all'))
            ->assertRedirect();

        $this->assertNotNull($mine->fresh()->read_at);
        $this->assertNotNull($alsoMine->fresh()->read_at);
        $this->assertNull($foreign->fresh()->read_at);
    }

    public function test_layout_shows_a_real_unread_badge(): void
    {
        $member = $this->makeControlPlaneUser('member');
        $this->seedNotification($member);
        $this->seedNotification($member, ['event_type' => 'loan_due_soon']);
        $this->seedNotification($member, ['event_type' => 'loan_overdue', 'read_at' => now()]);

        $response = $this->signInToLibraryAs($member)->get('/dashboard/notifications');

        $response->assertOk();
        $response->assertSee(__('librarian.member.notifications.unread_count', ['count' => 2]), false);
    }

    /**
     * Staff shells are separate: /dashboard/* is closed to librarians and
     * administrators, who are bounced back to their own consoles.
     */
    public function test_librarian_is_denied_the_member_notification_centre(): void
    {
        $librarian = $this->makeControlPlaneUser('librarian');

        $this->signInToLibraryAs($librarian)
            ->get('/dashboard/notifications')
            ->assertRedirect('/librarian');
    }

    public function test_admin_is_denied_the_member_notification_centre(): void
    {
        $this->signInToLibraryAs($this->adminUser)
            ->get('/dashboard/notifications')
            ->assertRedirect('/admin');
    }

    public function test_staff_cannot_mutate_reader_notifications_through_the_member_routes(): void
    {
        $member = $this->makeControlPlaneUser('member');
        $notification = $this->seedNotification($member);
        $librarian = $this->makeControlPlaneUser('librarian');

        $this->signInToLibraryAs($librarian)
            ->post(route('member.notifications.read', $notification))
            ->assertRedirect('/librarian');

        $this->assertNull($notification->fresh()->read_at);
    }
}
