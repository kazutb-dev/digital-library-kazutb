<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\ContactMessage;
use App\Models\Fund;
use App\Models\News;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\Csv;
use App\Support\DatabaseSchema;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Spatie\Permission\Models\Role;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    public function index(Request $request): View
    {
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);

        return view('admin.reports.index', $this->reportData($filters));
    }

    public function show(Request $request, string $type): View
    {
        abort_unless(in_array($type, $this->types(), true), 404);
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $data = $this->reportData($filters);

        return view('admin.reports.show', array_merge(
            $data,
            [
                'reportType' => $type,
                'reportTitle' => $this->typeLabel($type),
                'reportRows' => $this->rowsFor($type, $data),
            ],
        ));
    }

    public function export(
        Request $request,
        string $type,
        string $format,
        AuditLogger $audit,
    ): Response|StreamedResponse {
        abort_unless(in_array($type, $this->types(), true), 404);
        abort_unless(in_array($format, ['csv', 'pdf'], true), 404);
        $filters = $request->validate([
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
        ]);
        $data = $this->reportData($filters);
        $rows = $this->rowsFor($type, $data);

        $audit->logRequired(
            actionType: 'export',
            entityType: 'report',
            entityId: $type,
            newValues: ['format' => $format, 'filters' => $filters],
            scope: 'system',
        );

        if ($format === 'pdf') {
            return Pdf::loadView('admin.reports.pdf', [
                'reportType' => $type,
                'reportTitle' => $this->typeLabel($type),
                'rows' => $rows,
                'generatedAt' => now('UTC'),
                'filters' => $filters,
            ])->setPaper('a4', 'landscape')->download(
                "report-{$type}-".now('UTC')->format('Ymd-His').'.pdf'
            );
        }

        return response()->streamDownload(function () use ($rows): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            $headers = $rows->first() !== null
                ? array_map(
                    fn (string $column): string => $this->columnLabel($column),
                    array_keys($rows->first()),
                )
                : [$this->columnLabel('message')];
            Csv::writeRow($output, $headers);

            if ($rows->isEmpty()) {
                Csv::writeRow($output, [__('reports.no_data')]);
            } else {
                foreach ($rows as $row) {
                    Csv::writeRow($output, array_values($row));
                }
            }
            fclose($output);
        }, "report-{$type}-".now('UTC')->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @param  array<string, mixed>  $filters
     * @return array<string, mixed>
     */
    private function reportData(array $filters): array
    {
        $users = $this->dateFiltered(User::query(), $filters, 'created_at');
        $totalUsers = (clone $users)->count();
        $activeUsers = (clone $users)->where('is_active', true)->count();
        $roleDistribution = Role::query()
            ->where('guard_name', 'web')
            ->orderBy('name')
            ->get()
            ->map(function (Role $role) use ($filters): array {
                $roleUsers = $this->dateFiltered(User::query()->role($role), $filters, 'created_at');

                return [
                    'role' => $role->name,
                    'total' => (clone $roleUsers)->count(),
                    'active' => (clone $roleUsers)->where('is_active', true)->count(),
                ];
            });
        $providerDistribution = (clone $users)
            ->selectRaw('auth_provider, count(*) as aggregate')
            ->groupBy('auth_provider')
            ->orderBy('auth_provider')
            ->get()
            ->map(fn ($row): array => ['provider' => $row->auth_provider, 'total' => (int) $row->aggregate]);

        $newsQuery = $this->dateFiltered(News::query(), $filters, 'created_at');
        $newsByStatus = (clone $newsQuery)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn ($row): array => ['status' => $row->status, 'total' => (int) $row->aggregate]);
        $messageQuery = $this->dateFiltered(ContactMessage::query(), $filters, 'created_at');
        $messagesByStatus = (clone $messageQuery)
            ->selectRaw('status, count(*) as aggregate')
            ->groupBy('status')
            ->orderBy('status')
            ->get()
            ->map(fn ($row): array => ['status' => $row->status, 'total' => (int) $row->aggregate]);
        $messageTotal = (clone $messageQuery)->count();
        $resolvedMessages = (clone $messageQuery)->whereNotNull('resolved_at')->get(['created_at', 'resolved_at']);
        $resolutionRate = $messageTotal > 0 ? round($resolvedMessages->count() / $messageTotal * 100, 1) : 0.0;
        $averageResolutionHours = $resolvedMessages->isNotEmpty()
            ? round($resolvedMessages->avg(
                fn (ContactMessage $message): float => $message->created_at->diffInMinutes($message->resolved_at) / 60
            ), 1)
            : null;

        $activityQuery = $this->dateFiltered(ActivityLog::query(), $filters, 'occurred_at');
        $activityByRole = $activityQuery
            ->selectRaw('actor_role, count(*) as aggregate')
            ->groupBy('actor_role')
            ->orderByDesc('aggregate')
            ->get()
            ->map(fn ($row): array => ['role' => $row->actor_role, 'events' => (int) $row->aggregate]);

        $crmUrl = trim((string) config('services.external_auth.login_url', ''));
        $integrationStatuses = collect([
            [
                'integration' => __('admin.integrations.ldap'),
                'status' => $crmUrl !== '' && ! str_contains($crmUrl, 'crm.local')
                    ? 'configured'
                    : 'not_configured',
            ],
            [
                'integration' => __('admin.integrations.audit_endpoint'),
                'status' => DatabaseSchema::hasTable('app.integration_api_log') ? 'available' : 'not_configured',
            ],
        ]);

        return [
            'filters' => $filters,
            'totalUsers' => $totalUsers,
            'activeUsers' => $activeUsers,
            'roleDistribution' => $roleDistribution,
            'providerDistribution' => $providerDistribution,
            'activityByRole' => $activityByRole,
            'newsByStatus' => $newsByStatus,
            'messagesByStatus' => $messagesByStatus,
            'messageTotal' => $messageTotal,
            'resolutionRate' => $resolutionRate,
            'averageResolutionHours' => $averageResolutionHours,
            'integrationStatuses' => $integrationStatuses,
            'branchCount' => Branch::query()->count(),
            'fundCount' => Fund::query()->count(),
            'circulationAvailable' => DatabaseSchema::hasTable('app.circulation_loans'),
            'catalogAvailable' => DatabaseSchema::hasTable('app.documents'),
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     * @return Collection<int, array<string, int|float|string|null>>
     */
    private function rowsFor(string $type, array $data): Collection
    {
        return match ($type) {
            'user-activity' => collect($data['activityByRole'])->map(fn (array $row): array => [
                'role' => $this->roleLabel($row['role']),
                'events' => $row['events'],
            ]),
            'roles' => collect($data['roleDistribution'])->map(fn (array $row): array => [
                'role' => $this->roleLabel($row['role']),
                'total' => $row['total'],
                'active' => $row['active'],
            ]),
            'news' => collect($data['newsByStatus'])->map(fn (array $row): array => [
                'status' => __('news.statuses.'.$row['status']),
                'total' => $row['total'],
            ]),
            'messages' => collect($data['messagesByStatus'])->map(
                fn (array $row): array => [
                    'status' => __('messages.statuses.'.$row['status']),
                    'total' => $row['total'],
                    'resolution_rate_percent' => $data['resolutionRate'],
                    'average_resolution_hours' => $data['averageResolutionHours'],
                ]
            ),
            'integrations' => collect($data['integrationStatuses'])->map(fn (array $row): array => [
                'integration' => $row['integration'],
                'status' => __('common.status.'.$row['status']),
            ]),
            'branches-funds' => collect([
                ['entity' => __('admin.branches.title'), 'total' => $data['branchCount']],
                ['entity' => __('admin.funds.title'), 'total' => $data['fundCount']],
            ]),
            'circulation' => $data['circulationAvailable']
                ? collect([[
                    'metric' => __('admin.dashboard.metrics.active_loans'),
                    'total' => DB::table('app.circulation_loans')->whereNull('returned_at')->count(),
                ]])
                : collect(),
            'catalog' => $data['catalogAvailable']
                ? collect([[
                    'metric' => __('reports.metrics.catalog_records'),
                    'total' => DB::table('app.documents')->count(),
                ]])
                : collect(),
            default => collect(),
        };
    }

    /**
     * @return list<string>
     */
    private function types(): array
    {
        return ['user-activity', 'roles', 'news', 'messages', 'integrations', 'branches-funds', 'circulation', 'catalog'];
    }

    private function typeLabel(string $type): string
    {
        return match ($type) {
            'user-activity' => __('reports.sections.user_activity'),
            'roles' => __('reports.sections.role_distribution'),
            'news' => __('reports.sections.news_statistics'),
            'messages' => __('reports.sections.message_volume'),
            'integrations' => __('reports.sections.integration_status'),
            'branches-funds' => __('reports.sections.funds'),
            'circulation' => __('reports.sections.circulation'),
            'catalog' => __('reports.sections.catalog'),
            default => __('reports.title'),
        };
    }

    private function columnLabel(string $column): string
    {
        $key = 'reports.columns.'.$column;

        return trans()->has($key) ? __($key) : $column;
    }

    private function roleLabel(?string $role): string
    {
        if ($role === null || $role === '') {
            return __('common.time.not_available');
        }

        $key = 'roles.names.'.$role;

        return trans()->has($key) ? __($key) : $role;
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function dateFiltered(
        Builder $query,
        array $filters,
        string $column,
    ): Builder {
        if (! empty($filters['date_from'])) {
            $query->where($column, '>=', Carbon::parse($filters['date_from'], 'UTC')->startOfDay());
        }
        if (! empty($filters['date_to'])) {
            $query->where($column, '<=', Carbon::parse($filters['date_to'], 'UTC')->endOfDay());
        }

        return $query;
    }
}
