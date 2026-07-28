<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class EnsureIntegrationBoundary
{
    /**
     * @var array<int, string>
     */
    private array $requiredHeaders = [
        'X-Request-Id',
        'X-Correlation-Id',
        'X-Source-System',
        'X-Operator-Id',
        'X-Operator-Roles',
        'X-Operator-Org-Context',
    ];

    public function handle(Request $request, Closure $next): Response
    {
        $token = (string) $request->bearerToken();
        if (trim($token) === '') {
            return $this->errorResponse(
                request: $request,
                status: 401,
                errorCode: 'auth_failed',
                reasonCode: 'missing_bearer_token',
                message: 'Missing bearer token in Authorization header.'
            );
        }

        if (! $this->isTokenAllowed($token)) {
            Log::warning('Integration boundary: rejected invalid bearer token', [
                'ip' => $request->ip(),
                'token_prefix' => substr(hash('sha256', $token), 0, 16),
            ]);

            return $this->errorResponse(
                request: $request,
                status: 401,
                errorCode: 'auth_failed',
                reasonCode: 'invalid_bearer_token',
                message: 'Bearer token is not recognized.'
            );
        }

        foreach ($this->requiredHeaders as $header) {
            $value = (string) $request->header($header, '');
            if (trim($value) === '') {
                return $this->errorResponse(
                    request: $request,
                    status: 400,
                    errorCode: 'invalid_request',
                    reasonCode: 'missing_required_header',
                    message: 'Missing required header: ' . $header,
                );
            }
        }

        $sourceSystem = mb_strtolower(trim((string) $request->header('X-Source-System')));
        if ($sourceSystem !== 'crm') {
            return $this->errorResponse(
                request: $request,
                status: 400,
                errorCode: 'invalid_request',
                reasonCode: 'invalid_source_system',
                message: 'X-Source-System must be crm.',
            );
        }

        $requestId = trim((string) $request->header('X-Request-Id'));
        $correlationId = trim((string) $request->header('X-Correlation-Id'));

        $request->attributes->set('integration.authenticated_client_ref', 'token:' . substr(hash('sha256', $token), 0, 16));
        $request->attributes->set('integration.source_system', $sourceSystem);
        $request->attributes->set('integration.request_id', $requestId);
        $request->attributes->set('integration.correlation_id', $correlationId);

        $operatorRoles = $this->parseOperatorRoles((string) $request->header('X-Operator-Roles', ''));
        if ($operatorRoles === []) {
            return $this->errorResponse(
                request: $request,
                status: 400,
                errorCode: 'invalid_request',
                reasonCode: 'missing_operator_roles',
                message: 'X-Operator-Roles must contain at least one role value.',
            );
        }
        $request->attributes->set('integration.operator_roles', $operatorRoles);

        $response = $next($request);

        $response->headers->set('X-Request-Id', $requestId);
        $response->headers->set('X-Correlation-Id', $correlationId);
        $response->headers->set('X-API-Version', 'v1');
        $response->headers->set('X-API-Scope', 'frozen');

        return $response;
    }

    private function errorResponse(
        Request $request,
        int $status,
        string $errorCode,
        string $reasonCode,
        string $message
    ): JsonResponse {
        $requestId = trim((string) $request->header('X-Request-Id', ''));
        if ($requestId === '') {
            $requestId = (string) str()->uuid();
        }

        $correlationId = trim((string) $request->header('X-Correlation-Id', ''));
        if ($correlationId === '') {
            $correlationId = $requestId;
        }

        return response()->json([
            'error' => [
                'error_code' => $errorCode,
                'reason_code' => $reasonCode,
                'message' => $message,
            ],
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
            'timestamp' => now()->toISOString(),
        ], $status, [
            'X-Request-Id' => $requestId,
            'X-Correlation-Id' => $correlationId,
        ]);
    }

    /**
     * @return list<string>
     */
    private function parseOperatorRoles(string $rawRoles): array
    {
        $parts = array_map(
            static fn (string $part): string => mb_strtolower(trim($part)),
            explode(',', $rawRoles)
        );

        $roles = array_values(array_unique(array_filter($parts, static fn (string $role): bool => $role !== '')));

        return $roles;
    }

    /**
     * Validate bearer token against configured allowlist.
     * If INTEGRATION_ALLOWED_TOKENS is empty, any non-empty token is accepted (legacy/development mode).
     * In production, set the env var to a comma-separated list of valid tokens.
     */
    private function isTokenAllowed(string $token): bool
    {
        $raw = (string) config('services.integration.allowed_tokens', '');

        if (trim($raw) === '') {
            return true; // allowlist not configured — accept any token (legacy mode)
        }

        $allowed = array_filter(array_map('trim', explode(',', $raw)));

        return in_array($token, $allowed, true);
    }
}
