<?php

namespace App\Http\Controllers\Admin;

use App\Directory\ActiveDirectoryService;
use App\Exceptions\ActiveDirectoryException;
use App\Http\Controllers\Controller;
use App\Integrations\IntegrationHubService;
use App\Models\ActivityLog;
use App\Models\Integration;
use App\Models\IntegrationConflict;
use App\Models\IntegrationMapping;
use App\Models\IntegrationOutboxMessage;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\DatabaseSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IntegrationController extends Controller
{
    public function index(Request $request, ActiveDirectoryService $directory): View
    {
        $adConfigured = (bool) config('active_directory.enabled')
            && trim((string) config('active_directory.host')) !== ''
            && trim((string) config('active_directory.base_dn')) !== '';
        $integrationLogAvailable = DatabaseSchema::hasTable('app.integration_api_log');

        $directoryUsers = [];
        $directoryError = null;
        $directoryQuery = trim((string) $request->query('directory_q', ''));
        if ($directoryQuery !== '') {
            abort_unless(mb_strlen($directoryQuery) >= 2 && mb_strlen($directoryQuery) <= 100, 422);
            try {
                $directoryUsers = $directory->search($directoryQuery);
            } catch (ActiveDirectoryException $exception) {
                $directoryError = $exception->category;
            }
        }

        return view('admin.integrations.index', [
            'hubIntegrations' => Integration::query()
                ->with('responsible:id,name')
                ->withCount([
                    'outboxMessages as pending_queue_count' => fn ($query) => $query->whereIn('status', ['pending', 'processing', 'failed']),
                    'outboxMessages as dlq_count' => fn ($query) => $query->where('status', 'dead_letter'),
                    'conflicts as conflicts_count' => fn ($query) => $query->where('status', 'open'),
                ])->orderBy('type')->orderBy('name')->get(),
            'hubMetrics' => [
                'healthy' => Integration::query()->where('health_status', 'healthy')->count(),
                'degraded' => Integration::query()->where('health_status', 'degraded')->count(),
                'unavailable' => Integration::query()->where('health_status', 'unavailable')->count(),
                'processed' => IntegrationOutboxMessage::query()->whereIn('status', ['sent', 'acknowledged'])->count(),
                'failed' => IntegrationOutboxMessage::query()->where('status', 'failed')->count(),
                'dead_letter' => IntegrationOutboxMessage::query()->where('status', 'dead_letter')->count(),
                'open_conflicts' => IntegrationConflict::query()->where('status', 'open')->count(),
                'average_latency_ms' => (int) round((float) Integration::query()->whereNotNull('last_latency_ms')->avg('last_latency_ms')),
            ],
            'integrations' => [
                [
                    'key' => 'active_directory',
                    'name' => 'Active Directory',
                    'configured' => (bool) config('active_directory.enabled') && trim((string) config('active_directory.host')) !== '',
                    'endpoint' => config('active_directory.enabled') ? ((config('active_directory.use_ssl') ? 'LDAPS' : 'LDAP').' · '.(int) config('active_directory.port')) : null,
                    'mode' => __('admin.integrations.read_only_env'),
                ],
                [
                    'key' => 'database',
                    'name' => 'PostgreSQL',
                    'configured' => config('database.default') === 'pgsql',
                    'endpoint' => config('database.default'),
                    'mode' => __('admin.integrations.application_connection'),
                ],
                [
                    'key' => 'storage',
                    'name' => __('admin.integrations.file_storage'),
                    'configured' => is_writable(storage_path('app')),
                    'endpoint' => config('filesystems.default'),
                    'mode' => __('admin.integrations.application_storage'),
                ],
            ],
            'integrationLogAvailable' => $integrationLogAvailable,
            'integrationLogCount' => $integrationLogAvailable
                ? DB::table('app.integration_api_log')->count()
                : 0,
            'security' => [
                'demo_login_enabled' => (bool) config('demo_users.enabled'),
                'legacy_demo_login_enabled' => (bool) config('demo_auth.enabled'),
                'debug_enabled' => (bool) config('app.debug'),
                'environment' => (string) config('app.env'),
                'https' => request()->isSecure(),
                'active_admins' => User::query()->role('admin')->where('is_active', true)->count(),
                'failed_logins_24h' => DatabaseSchema::hasTable('activity_logs')
                    ? ActivityLog::query()
                        ->where('action_type', 'login.fail')
                        ->where('occurred_at', '>=', now('UTC')->subDay())
                        ->count()
                    : 0,
            ],
            'backup' => [
                'provider' => config('admin.backup.provider'),
                'schedule' => config('admin.backup.schedule'),
                'last_success_at' => config('admin.backup.last_success_at'),
                'recovery_runbook' => config('admin.backup.recovery_runbook'),
            ],
            'activeDirectory' => [
                'enabled' => (bool) config('active_directory.enabled'),
                'ssl' => (bool) config('active_directory.use_ssl'),
                'certificate_verification' => (bool) config('active_directory.require_cert'),
                'base_dn_configured' => trim((string) config('active_directory.base_dn')) !== '',
                'login_field' => (string) config('active_directory.login_field'),
                'last_health' => Cache::get('integration.active_directory.last_health'),
            ],
            'directoryUsers' => $directoryUsers,
            'directoryError' => $directoryError,
            'directoryQuery' => $directoryQuery,
            'readinessChecklist' => $this->readinessChecklist($adConfigured),
        ]);
    }

    public function show(Integration $integration): View
    {
        $integration->load('responsible:id,name');
        $canViewLogs = request()->user()->can('integrations.view_logs');
        $canViewConflicts = request()->user()->can('integrations.view_conflicts');
        $canManageMappings = request()->user()->can('integrations.manage_mapping');

        return view('admin.integrations.show', [
            'integration' => $integration,
            'syncRuns' => $canViewLogs ? $integration->syncRuns()->latest('started_at')->limit(20)->get() : collect(),
            'mappings' => $canManageMappings ? $integration->mappings()->orderByDesc('version')->orderBy('external_field')->get() : collect(),
            'conflicts' => $canViewConflicts ? $integration->conflicts()->latest()->limit(30)->get() : collect(),
            'inbox' => $canViewLogs ? $integration->inboxMessages()->select(['id', 'external_message_id', 'event_type', 'payload_hash', 'schema_version', 'received_at', 'status', 'attempts', 'processed_at', 'error_code', 'correlation_id'])->latest('received_at')->limit(30)->get() : collect(),
            'outbox' => $canViewLogs ? $integration->outboxMessages()->select(['id', 'aggregate_type', 'aggregate_id', 'event_type', 'idempotency_key', 'destination', 'status', 'attempts', 'next_attempt_at', 'sent_at', 'acknowledged_at', 'error_code', 'correlation_id'])->latest()->limit(30)->get() : collect(),
        ]);
    }

    public function health(Integration $integration, IntegrationHubService $hub): RedirectResponse
    {
        $result = $hub->healthCheck($integration, request()->user());

        return back()->with('integration_hub_status', $result['healthy'] ? 'healthy' : ($result['error_code'] ?? 'unavailable'));
    }

    public function toggle(Integration $integration, Request $request, IntegrationHubService $hub): RedirectResponse
    {
        $validated = $request->validate(['enabled' => ['required', 'boolean'], 'confirmation' => ['required', Rule::in(['CONFIRM'])]]);
        $hub->setEnabled($integration, (bool) $validated['enabled'], $request->user());

        return back()->with('success', __('common.updated_successfully'));
    }

    public function dryRun(Integration $integration, Request $request, IntegrationHubService $hub): RedirectResponse
    {
        $hub->dryRun($integration, $request->user());

        return back()->with('success', __('common.updated_successfully'));
    }

    public function startSync(Integration $integration, Request $request, IntegrationHubService $hub): RedirectResponse
    {
        $request->validate(['confirmation' => ['required', Rule::in(['CONFIRM'])]]);
        $hub->startSync($integration, $request->user());

        return back()->with('success', __('common.updated_successfully'));
    }

    public function reconcile(Integration $integration, Request $request, IntegrationHubService $hub): RedirectResponse
    {
        abort_unless($integration->direction === 'bidirectional', 422);
        $hub->reconcile($integration, $request->user());

        return back()->with('success', __('common.updated_successfully'));
    }

    public function storeMapping(Integration $integration, Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'external_field' => ['required', 'regex:/^[A-Za-z][A-Za-z0-9_.-]{0,127}$/'],
            'local_field' => ['required', Rule::in(['university_id', 'email', 'name', 'phone', 'faculty', 'department', 'academic_group', 'status', 'course', 'educational_programme'])],
            'required' => ['nullable', 'boolean'],
        ]);
        $version = ((int) $integration->mappings()->max('version')) + 1;
        IntegrationMapping::query()->create([...$validated, 'required' => (bool) ($validated['required'] ?? false), 'integration_id' => $integration->id, 'version' => $version, 'created_by' => $request->user()->id]);
        $integration->increment('config_version');
        $audit->logRequired(
            actionType: 'integration.mapping_updated',
            entityType: 'integration',
            entityId: (string) $integration->id,
            newValues: ['external_field' => $validated['external_field'], 'local_field' => $validated['local_field'], 'version' => $version],
            scope: 'system',
            actor: $request->user(),
        );

        return back()->with('success', __('common.created_successfully'));
    }

    public function retry(Integration $integration, IntegrationOutboxMessage $message, IntegrationHubService $hub): RedirectResponse
    {
        abort_unless($message->integration_id === $integration->id, 404);
        $hub->retry($message, request()->user());

        return back()->with('success', __('common.updated_successfully'));
    }

    public function resolveConflict(Integration $integration, IntegrationConflict $conflict, Request $request, IntegrationHubService $hub): RedirectResponse
    {
        abort_unless($conflict->integration_id === $integration->id, 404);
        $validated = $request->validate(['resolution' => ['required', Rule::in(['accept_local', 'accept_external', 'manual_fix', 'ignore'])], 'reason' => ['required', 'string', 'min:5', 'max:1000']]);
        $hub->resolve($conflict, $validated['resolution'], $validated['reason'], $request->user());

        return back()->with('success', __('common.updated_successfully'));
    }

    /**
     * Honest go-live gate: each item is a hard deployment-level fact the
     * admin cannot change from the UI. `blocker` items must be green before
     * production; `warning` items are strongly recommended.
     *
     * @return list<array{key: string, ok: bool, severity: string}>
     */
    private function readinessChecklist(bool $adConfigured): array
    {
        $demoLoginEnabled = (bool) config('demo_users.enabled') || (bool) config('demo_auth.enabled');

        return [
            ['key' => 'https', 'ok' => request()->isSecure(), 'severity' => 'blocker'],
            ['key' => 'demo_login_disabled', 'ok' => ! $demoLoginEnabled, 'severity' => 'blocker'],
            ['key' => 'debug_disabled', 'ok' => ! (bool) config('app.debug'), 'severity' => 'blocker'],
            ['key' => 'backup_provider', 'ok' => trim((string) config('admin.backup.provider')) !== '', 'severity' => 'blocker'],
            ['key' => 'ldap_configured', 'ok' => $adConfigured, 'severity' => 'warning'],
            ['key' => 'env_production', 'ok' => config('app.env') === 'production', 'severity' => 'warning'],
        ];
    }

    public function check(Request $request, AuditLogger $audit, ActiveDirectoryService $directory): RedirectResponse
    {
        $validated = $request->validate([
            'integration' => ['required', Rule::in(['active_directory', 'database', 'storage'])],
        ]);
        $startedAt = microtime(true);
        $ok = false;
        $detail = null;

        try {
            if ($validated['integration'] === 'database') {
                DB::select('select 1');
                $ok = true;
            } elseif ($validated['integration'] === 'storage') {
                $ok = is_writable(storage_path('app'));
                $detail = $ok ? null : __('admin.integrations.storage_not_writable');
            } else {
                $health = $directory->health();
                $ok = $health->connected;
                $detail = $health->errorCategory;
                Cache::put('integration.active_directory.last_health', [...$health->toArray(), 'checked_at' => now('UTC')->toIso8601String()], now()->addDays(7));
            }
        } catch (\Throwable $exception) {
            $detail = class_basename($exception);
        }

        $elapsed = round((microtime(true) - $startedAt) * 1000, 1);

        $audit->logRequired(
            actionType: $validated['integration'] === 'active_directory' ? 'integration.ad.health_checked' : 'check',
            entityType: 'integration',
            entityId: $validated['integration'],
            newValues: ['reachable' => $ok, 'duration_ms' => $elapsed, 'detail' => $detail],
            scope: 'system',
        );

        return back()->with('integration_check', [
            'key' => $validated['integration'],
            'ok' => $ok,
            'duration_ms' => $elapsed,
            'detail' => $detail,
        ]);
    }

    private function safeEndpoint(string $url): string
    {
        $parts = parse_url($url);
        if (! is_array($parts)) {
            return __('common.configured');
        }

        return ($parts['scheme'] ?? 'https').'://'.($parts['host'] ?? 'configured').($parts['path'] ?? '');
    }
}
