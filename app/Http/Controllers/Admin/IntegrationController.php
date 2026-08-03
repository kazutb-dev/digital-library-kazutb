<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\User;
use App\Services\AuditLogger;
use App\Support\DatabaseSchema;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class IntegrationController extends Controller
{
    public function index(): View
    {
        $crmUrl = trim((string) config('services.external_auth.login_url', ''));
        $crmConfigured = $crmUrl !== '' && ! str_contains($crmUrl, 'crm.local');
        $integrationLogAvailable = DatabaseSchema::hasTable('app.integration_api_log');

        return view('admin.integrations.index', [
            'integrations' => [
                [
                    'key' => 'crm',
                    'name' => 'CRM / LDAP',
                    'configured' => $crmConfigured,
                    'endpoint' => $crmConfigured ? $this->safeEndpoint($crmUrl) : null,
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
            'readinessChecklist' => $this->readinessChecklist($crmConfigured),
        ]);
    }

    /**
     * Honest go-live gate: each item is a hard deployment-level fact the
     * admin cannot change from the UI. `blocker` items must be green before
     * production; `warning` items are strongly recommended.
     *
     * @return list<array{key: string, ok: bool, severity: string}>
     */
    private function readinessChecklist(bool $crmConfigured): array
    {
        $demoLoginEnabled = (bool) config('demo_users.enabled') || (bool) config('demo_auth.enabled');

        return [
            ['key' => 'https', 'ok' => request()->isSecure(), 'severity' => 'blocker'],
            ['key' => 'demo_login_disabled', 'ok' => ! $demoLoginEnabled, 'severity' => 'blocker'],
            ['key' => 'debug_disabled', 'ok' => ! (bool) config('app.debug'), 'severity' => 'blocker'],
            ['key' => 'backup_provider', 'ok' => trim((string) config('admin.backup.provider')) !== '', 'severity' => 'blocker'],
            ['key' => 'ldap_configured', 'ok' => $crmConfigured, 'severity' => 'warning'],
            ['key' => 'env_production', 'ok' => config('app.env') === 'production', 'severity' => 'warning'],
        ];
    }

    public function check(Request $request, AuditLogger $audit): RedirectResponse
    {
        $validated = $request->validate([
            'integration' => ['required', Rule::in(['crm', 'database', 'storage'])],
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
                $url = trim((string) config('services.external_auth.login_url', ''));
                if ($url !== '' && ! str_contains($url, 'crm.local')) {
                    $response = Http::timeout(5)->acceptJson()->get($url);
                    // 401/405/422 still proves that the configured service is reachable.
                    $ok = $response->status() < 500;
                    $detail = 'HTTP '.$response->status();
                } else {
                    $detail = __('common.not_configured');
                }
            }
        } catch (\Throwable $exception) {
            $detail = class_basename($exception);
        }

        $elapsed = round((microtime(true) - $startedAt) * 1000, 1);

        $audit->logRequired(
            actionType: 'check',
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
