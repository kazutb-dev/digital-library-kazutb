<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\News;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\DatabaseSchema;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(Request $request, AuditLogger $audit): View
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $access = [
            'users' => $user->can('users.manage'),
            'roles' => $user->can('roles.manage'),
            'logs' => $user->can('system.logs'),
            'news' => $user->canAny(['news.edit_any', 'news.edit_own']),
            'news_create' => $user->can('news.create'),
            'messages' => $user->can('messages.view_assigned'),
            'reports' => $user->can('reports.view_full'),
            'reports_export' => $user->can('reports.export') && $user->can('reports.view_full'),
            'settings' => $user->can('system.settings'),
            'branches' => $user->can('branches.manage'),
            'external_resources' => $user->can('external_resources.manage'),
        ];

        $newsCounts = $access['news'] && Schema::hasTable('news')
            ? News::query()
                ->when(
                    ! $user->can('news.edit_any'),
                    fn ($query) => $query->where('created_by', $user->getKey()),
                )
                ->selectRaw('status, count(*) as aggregate')
                ->groupBy('status')
                ->pluck('aggregate', 'status')
            : collect();
        $visibleLogs = $access['logs'] && Schema::hasTable('activity_logs')
            ? $audit->visibleQuery($user)
            : null;

        $metrics = [];
        if ($access['users']) {
            $metrics[] = [
                'label' => __('admin.dashboard.metrics.active_users'),
                'value' => Schema::hasTable('users')
                    ? User::query()->where('is_active', true)->count()
                    : 0,
                'note' => __('admin.dashboard.metrics.active_users_note'),
                'icon' => 'group',
                'tone' => 'surface',
                'available' => Schema::hasTable('users'),
            ];
        }

        if ($access['reports']) {
            $activeLoans = DatabaseSchema::hasTable('app.circulation_loans')
                ? DB::table('app.circulation_loans')->whereNull('returned_at')->count()
                : null;
            $metrics[] = [
                'label' => __('admin.dashboard.metrics.active_loans'),
                'value' => $activeLoans ?? 0,
                'note' => $activeLoans === null
                    ? __('reports.data_unavailable_circulation')
                    : __('admin.dashboard.metrics.active_loans_note'),
                'icon' => 'swap_horiz',
                'tone' => 'surface',
                'available' => $activeLoans !== null,
            ];
        }

        if ($access['messages']) {
            $messagesAvailable = Schema::hasTable('contact_messages');
            $metrics[] = [
                'label' => __('admin.dashboard.metrics.pending_messages'),
                'value' => $messagesAvailable
                    ? ContactMessage::query()->whereIn('status', ['open', 'in_review'])->count()
                    : 0,
                'note' => __('admin.dashboard.metrics.pending_messages_note'),
                'icon' => 'mark_email_unread',
                'tone' => 'primary',
                'available' => $messagesAvailable,
            ];
        }

        if ($access['logs']) {
            $logsAvailable = Schema::hasTable('activity_logs');
            $metrics[] = [
                'label' => __('admin.dashboard.metrics.audit_events'),
                'value' => $logsAvailable && $visibleLogs !== null
                    ? (clone $visibleLogs)->count()
                    : 0,
                'note' => __('admin.dashboard.metrics.audit_events_hint'),
                'icon' => 'fact_check',
                'tone' => 'surface',
                'available' => $logsAvailable,
            ];
        }

        if ($access['news']) {
            $metrics[] = [
                'label' => __('admin.dashboard.metrics.published_news'),
                'value' => (int) ($newsCounts['published'] ?? 0),
                'note' => __('admin.dashboard.metrics.published_news_hint'),
                'icon' => 'newspaper',
                'tone' => 'surface',
                'available' => Schema::hasTable('news'),
            ];
        }

        return view('admin.overview', [
            'dashboardAccess' => $access,
            'metrics' => $metrics,
            'healthItems' => $access['settings'] ? $this->healthItems() : [],
            'recentLogs' => $visibleLogs !== null
                ? (clone $visibleLogs)->latest('occurred_at')->limit(6)->get()
                : collect(),
            'messageQueue' => $access['messages'] && Schema::hasTable('contact_messages')
                ? ContactMessage::query()->whereIn('status', ['open', 'in_review'])->latest()->limit(5)->get()
                : collect(),
            'newsCounts' => $newsCounts,
        ]);
    }

    /**
     * System-health details are administrative configuration data and must
     * never be calculated or exposed to a delegated role without
     * `system.settings`.
     *
     * @return list<array{title: string, subtitle: string, status: string, ok: bool, icon: string}>
     */
    private function healthItems(): array
    {
        $startedAt = microtime(true);
        $databaseOk = false;

        try {
            DB::select('select 1');
            $databaseOk = true;
        } catch (\Throwable) {
            // Report the real failure rather than inventing health.
        }

        $databaseLatency = $databaseOk ? round((microtime(true) - $startedAt) * 1000, 1) : null;
        $crmUrl = trim((string) config('services.external_auth.login_url', ''));
        $crmConfigured = $crmUrl !== '' && ! str_contains($crmUrl, 'crm.local');

        return [
            [
                'title' => __('admin.dashboard.health.database'),
                'subtitle' => __('admin.dashboard.health.database_note'),
                'status' => $databaseOk
                    ? __('admin.dashboard.health.response_ms', ['value' => $databaseLatency])
                    : __('common.unavailable'),
                'ok' => $databaseOk,
                'icon' => 'database',
            ],
            [
                'title' => __('admin.dashboard.health.storage'),
                'subtitle' => __('admin.dashboard.health.storage_note'),
                'status' => is_writable(storage_path('app'))
                    ? __('common.available')
                    : __('common.unavailable'),
                'ok' => is_writable(storage_path('app')),
                'icon' => 'hard_drive',
            ],
            [
                'title' => __('admin.dashboard.health.authentication'),
                'subtitle' => __('admin.dashboard.health.authentication_note'),
                'status' => $crmConfigured
                    ? __('common.configured')
                    : __('common.not_configured'),
                'ok' => $crmConfigured,
                'icon' => 'vpn_key',
            ],
        ];
    }
}
