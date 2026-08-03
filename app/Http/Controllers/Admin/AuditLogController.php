<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Setting;
use App\Services\AuditLogger;
use App\Support\Csv;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AuditLogController extends Controller
{
    public function index(Request $request, AuditLogger $audit): View
    {
        $filters = $this->validatedFilters($request);
        $logs = $this->filteredQuery($audit, $request, $filters)
            ->orderBy($filters['sort'] ?? 'occurred_at', $filters['direction'] ?? 'desc')
            ->paginate(Setting::resultsPerPage())
            ->withQueryString();
        $visibleLogs = $audit->visibleQuery($request->user() ?? session('library.user'));

        return view('admin.logs.index', [
            'logs' => $logs,
            'filters' => $filters,
            'actionTypes' => (clone $visibleLogs)->distinct()->orderBy('action_type')->pluck('action_type'),
            'entityTypes' => (clone $visibleLogs)->distinct()->orderBy('entity_type')->pluck('entity_type'),
            'demoLoginIdentifiers' => self::demoLoginIdentifiers(),
        ]);
    }

    public function show(Request $request, ActivityLog $activityLog, AuditLogger $audit): View
    {
        $visible = $audit->visibleQuery($request->user() ?? session('library.user'))
            ->whereKey($activityLog->getKey())
            ->firstOrFail();

        return view('admin.logs.show', ['activityLog' => $visible]);
    }

    public function export(Request $request, AuditLogger $audit): StreamedResponse
    {
        $filters = $this->validatedFilters($request);
        $logs = $this->filteredQuery($audit, $request, $filters)
            ->orderByDesc('occurred_at')
            ->cursor();

        $audit->logRequired(
            actionType: 'export',
            entityType: 'report',
            entityId: 'activity_log',
            newValues: ['format' => 'csv', 'filters' => $filters],
            scope: 'security',
        );

        return response()->streamDownload(function () use ($logs): void {
            $output = fopen('php://output', 'wb');
            fwrite($output, "\xEF\xBB\xBF");
            Csv::writeRow($output, [
                __('reports.columns.timestamp_utc'),
                __('reports.columns.actor_id'),
                __('reports.columns.actor'),
                __('reports.columns.actor_role'),
                __('reports.columns.action'),
                __('reports.columns.entity_type'),
                __('reports.columns.entity_id'),
                __('reports.columns.ip_address'),
                __('reports.columns.reason'),
                __('reports.columns.old_values'),
                __('reports.columns.new_values'),
            ]);

            foreach ($logs as $log) {
                Csv::writeRow($output, [
                    $log->occurred_at?->utc()->toIso8601String(),
                    $log->actor_id,
                    $log->actor_name,
                    $log->actor_role,
                    $log->action_type,
                    $log->entity_type,
                    $log->entity_id,
                    $log->ip_address,
                    $log->reason,
                    json_encode($log->old_values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                    json_encode($log->new_values, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES),
                ]);
            }

            fclose($output);
        }, 'audit-log-'.now('UTC')->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv; charset=UTF-8']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validatedFilters(Request $request): array
    {
        return $request->validate([
            'actor' => ['nullable', 'string', 'max:120'],
            'action_type' => ['nullable', 'string', 'max:80'],
            'entity_type' => ['nullable', 'string', 'max:120'],
            'login_watch' => ['nullable', 'boolean'],
            'date_from' => ['nullable', 'date'],
            'date_to' => ['nullable', 'date', 'after_or_equal:date_from'],
            'sort' => ['nullable', Rule::in(['occurred_at', 'actor_name', 'action_type', 'entity_type'])],
            'direction' => ['nullable', Rule::in(['asc', 'desc'])],
        ]);
    }

    /**
     * @param  array<string, mixed>  $filters
     */
    private function filteredQuery(
        AuditLogger $audit,
        Request $request,
        array $filters,
    ): Builder {
        $query = $audit->visibleQuery($request->user() ?? session('library.user'));

        if ($actor = trim((string) ($filters['actor'] ?? ''))) {
            $needle = '%'.mb_strtolower($actor).'%';
            $query->where(function (Builder $builder) use ($actor, $needle): void {
                $builder->whereRaw('LOWER(actor_name) LIKE ?', [$needle]);
                if (is_numeric($actor)) {
                    $builder->orWhere('actor_id', (int) $actor);
                }
            });
        }

        if ($action = ($filters['action_type'] ?? null)) {
            $query->where('action_type', $action);
        }

        if ($entity = ($filters['entity_type'] ?? null)) {
            $query->where('entity_type', $entity);
        }

        if ($from = ($filters['date_from'] ?? null)) {
            $query->where('occurred_at', '>=', Carbon::parse($from, 'UTC')->startOfDay());
        }

        if ($to = ($filters['date_to'] ?? null)) {
            $query->where('occurred_at', '<=', Carbon::parse($to, 'UTC')->endOfDay());
        }

        if ($filters['login_watch'] ?? false) {
            $demoIdentifiers = self::demoLoginIdentifiers();
            $query
                ->whereIn('action_type', ['login.fail', 'login.throttled'])
                ->whereNotIn(DB::raw('LOWER(entity_id)'), $demoIdentifiers)
                ->whereNotIn(DB::raw('LOWER(actor_name)'), $demoIdentifiers);
        }

        return $query;
    }

    /**
     * Every login/email/name a demo quick-login identity can authenticate
     * under. Failed attempts outside this set signal real people trying to
     * sign in (e.g. staff without LDAP access yet) — surfaced via the
     * "login watch" filter so admins spot them without reading the full log.
     *
     * @return list<string>
     */
    public static function demoLoginIdentifiers(): array
    {
        $identifiers = [];

        foreach ((array) config('demo_auth.identities', []) as $identity) {
            foreach (['login', 'ad_login', 'quick_fill_login', 'email', 'name'] as $key) {
                $identifiers[] = (string) ($identity[$key] ?? '');
            }
        }

        foreach ((array) config('demo_users.identities', []) as $identity) {
            foreach (['email', 'ad_login', 'name'] as $key) {
                $identifiers[] = (string) ($identity[$key] ?? '');
            }
        }

        return array_values(array_unique(array_filter(array_map(
            static fn (string $value): string => mb_strtolower(trim($value)),
            $identifiers,
        ))));
    }
}
