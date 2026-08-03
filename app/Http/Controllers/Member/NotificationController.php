<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Catalog\ReaderNotification;
use App\Models\Setting;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

/**
 * In-app reader notification centre (Master.md §15.6).
 *
 * The feed is strictly scoped to the authenticated reader: every read and
 * every mutation is filtered by user_id in this controller, never by hiding
 * markup in the view. Notification rows are written by the circulation and
 * reservation services through LibraryNotificationService.
 */
class NotificationController extends Controller
{
    /**
     * Filter tabs map onto families of the canonical event dictionary
     * (NotificationSetting::EVENT_TYPES). Anything outside a family is still
     * reachable through the unfiltered "all" tab.
     *
     * @var array<string, list<string>>
     */
    public const FAMILIES = [
        'reservations' => [
            'reservation_created',
            'reservation_queued',
            'reservation_confirmed',
            'reservation_in_transit',
            'reservation_ready',
            'reservation_expiry_reminder',
            'reservation_expired',
            'reservation_cancelled',
        ],
        'loans' => [
            'loan_due_soon',
            'loan_overdue',
            'loan_renewed',
            'fine_charged',
            'incident_opened',
        ],
        'digital' => [
            'digital_access_granted',
        ],
        'library' => [
            'news_published',
            'message_received',
            'message_status_changed',
        ],
    ];

    public function index(Request $request): View
    {
        $userId = $request->user()->getKey();

        $type = (string) $request->query('type', '');
        if (! array_key_exists($type, self::FAMILIES)) {
            $type = '';
        }

        $query = ReaderNotification::query()
            ->where('user_id', $userId)
            ->whereNotIn('event_type', ['repository_status_changed', 'repository_published']);

        if ($type !== '') {
            $query->whereIn('event_type', self::FAMILIES[$type]);
        }

        $notifications = $query
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(Setting::resultsPerPage())
            ->withQueryString();

        // Group the current page by calendar day so the feed reads as a diary.
        $groups = collect($notifications->items())
            ->groupBy(fn (ReaderNotification $notification): string => ($notification->created_at ?? $notification->updated_at ?? now())->format('d.m.Y'));

        $countsByEvent = ReaderNotification::query()
            ->where('user_id', $userId)
            ->whereNotIn('event_type', ['repository_status_changed', 'repository_published'])
            ->selectRaw('event_type, count(*) as total')
            ->groupBy('event_type')
            ->pluck('total', 'event_type');

        $familyCounts = [];
        foreach (self::FAMILIES as $family => $events) {
            $familyCounts[$family] = array_sum(array_map(
                static fn (string $event): int => (int) ($countsByEvent[$event] ?? 0),
                $events,
            ));
        }

        return view('member.notifications', [
            'notifications' => $notifications,
            'notificationGroups' => $groups,
            'unreadCount' => ReaderNotification::query()->where('user_id', $userId)->unread()->count(),
            'totalCount' => (int) $countsByEvent->sum(),
            'familyCounts' => $familyCounts,
            'activeType' => $type,
            'today' => Carbon::today()->format('d.m.Y'),
            'yesterday' => Carbon::yesterday()->format('d.m.Y'),
        ]);
    }

    public function markRead(Request $request, ReaderNotification $notification, AuditLogger $audit): RedirectResponse
    {
        abort_unless((int) $notification->user_id === (int) $request->user()->getKey(), 403);

        if ($notification->read_at === null) {
            $notification->forceFill(['read_at' => now()])->save();
            $audit->logRequired('notification.read', 'reader_notification', $notification->getKey(), scope: 'personal', actor: $request->user());
        }

        return back()->with('success', __('librarian.member.notifications.marked_read'));
    }

    public function markAllRead(Request $request, AuditLogger $audit): RedirectResponse
    {
        $updated = ReaderNotification::query()
            ->where('user_id', $request->user()->getKey())
            ->unread()
            ->update(['read_at' => now(), 'updated_at' => now()]);
        if ($updated > 0) {
            $audit->logRequired('notification.read', 'reader_notification', 'bulk:'.$request->user()->getKey(), newValues: ['count' => $updated], scope: 'personal', actor: $request->user());
        }

        return back()->with('success', $updated > 0
            ? __('librarian.member.notifications.marked_all_read')
            : __('librarian.member.notifications.nothing_to_mark'));
    }
}
