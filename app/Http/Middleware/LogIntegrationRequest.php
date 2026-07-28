<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logs every CRM integration API request with timing, client ref,
 * route, status code, and operator context for governance monitoring.
 */
class LogIntegrationRequest
{
    public function handle(Request $request, Closure $next): Response
    {
        $startTime = microtime(true);

        $response = $next($request);

        $durationMs = round((microtime(true) - $startTime) * 1000, 1);

        $logData = [
            'client_ref' => $request->attributes->get('integration.authenticated_client_ref', 'unknown'),
            'source_system' => $request->attributes->get('integration.source_system', 'unknown'),
            'method' => $request->method(),
            'path' => $request->path(),
            'status' => $response->getStatusCode(),
            'duration_ms' => $durationMs,
            'request_id' => $request->attributes->get('integration.request_id'),
            'correlation_id' => $request->attributes->get('integration.correlation_id'),
            'operator_roles' => $request->attributes->get('integration.operator_roles', []),
            'ip' => $request->ip(),
        ];

        // Log to Laravel log channel
        $level = $response->getStatusCode() >= 500 ? 'error'
            : ($response->getStatusCode() >= 400 ? 'warning' : 'info');

        Log::channel('stack')->{$level}('integration_api_request', $logData);

        // Persist to database for governance dashboard (best-effort, no failure propagation)
        try {
            if ($this->hasApiLogTable()) {
                DB::connection('pgsql')->table('app.integration_api_log')->insert([
                    'id' => (string) Str::uuid(),
                    'client_ref' => $logData['client_ref'],
                    'source_system' => $logData['source_system'],
                    'method' => $logData['method'],
                    'path' => $logData['path'],
                    'status_code' => $logData['status'],
                    'duration_ms' => $logData['duration_ms'],
                    'request_id' => $logData['request_id'],
                    'correlation_id' => $logData['correlation_id'],
                    'ip_address' => $logData['ip'],
                    'logged_at' => now('UTC')->toDateTimeString(),
                ]);
            }
        } catch (\Throwable) {
            // Best-effort logging — don't fail the request
        }

        return $response;
    }

    private function hasApiLogTable(): bool
    {
        static $checked = null;
        if ($checked !== null) {
            return $checked;
        }

        try {
            $checked = DB::connection('pgsql')
                ->getSchemaBuilder()
                ->hasTable('app.integration_api_log');
        } catch (\Throwable) {
            $checked = false;
        }

        return $checked;
    }
}
