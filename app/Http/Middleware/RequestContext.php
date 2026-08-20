<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class RequestContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $requestId = $this->safeId($request->header('X-Request-Id')) ?: (string) Str::uuid();
        $correlationId = $this->safeId($request->header('X-Correlation-Id')) ?: $requestId;
        $actor = $request->user();
        $sessionRole = $request->hasSession() ? data_get($request->session()->get('library.user'), 'canonical_role') : null;

        $request->attributes->set('request_id', $requestId);
        $request->attributes->set('correlation_id', $correlationId);
        Log::withContext([
            'request_id' => $requestId,
            'correlation_id' => $correlationId,
            'route' => $request->route()?->getName(),
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'),
            'actor_id' => $actor?->getKey(),
            'actor_role' => $actor?->effectiveRole() ?: $sessionRole,
        ]);

        $response = $next($request);
        $response->headers->set('X-Request-Id', $requestId);
        $response->headers->set('X-Correlation-Id', $correlationId);
        Log::withoutContext(['request_id', 'correlation_id', 'route', 'method', 'path', 'actor_id', 'actor_role']);

        return $response;
    }

    private function safeId(?string $value): ?string
    {
        $value = trim((string) $value);

        return $value !== '' && preg_match('/^[A-Za-z0-9._:-]{1,128}$/', $value) ? $value : null;
    }
}
