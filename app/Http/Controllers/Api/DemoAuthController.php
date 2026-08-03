<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\LibraryAuthenticationException;
use App\Http\Controllers\Controller;
use App\Models\User;
use App\Services\AuditLogger;
use App\Services\AuthSessionManager;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DemoAuthController extends Controller
{
    /**
     * Quick-login as a predefined demo identity.
     *
     * Gated by config('demo_auth.enabled'). Returns 403 when disabled.
     */
    public function login(
        Request $request,
        AuthSessionManager $sessions,
        AuditLogger $audit,
    ): JsonResponse {
        if (! config('demo_users.enabled')) {
            $this->auditFailure($audit, $request, 'disabled', 'demo_login_disabled');

            return response()->json([
                'message' => 'Demo login is not available.',
            ], 403);
        }

        $validated = $request->validate([
            'role' => ['required', 'string', 'max:50'],
        ]);

        $slug = $validated['role'];
        $identities = config('demo_users.identities', []);

        if (! isset($identities[$slug]) || ! is_array($identities[$slug])) {
            $this->auditFailure($audit, $request, $slug, 'unknown_demo_identity');

            return response()->json([
                'message' => 'Unknown demo identity.',
            ], 422);
        }

        $identity = $identities[$slug];
        $user = User::query()->where('email', $identity['email'])->first();
        if ($user === null) {
            $this->auditFailure($audit, $request, $slug, 'demo_user_missing');

            return response()->json(['message' => 'Demo user is not seeded.'], 503);
        }

        try {
            $sessionUser = $sessions->login($request, $user, $identity['profile_type'] ?? null);
        } catch (LibraryAuthenticationException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status);
        }
        $request->session()->put('library.crm_token', 'demo-rbac-'.$slug);
        $request->session()->put('library.demo_identity', $slug);

        return response()->json([
            'success' => true,
            'user' => $sessionUser,
            'landing' => $sessions->landing($user),
        ]);
    }

    private function auditFailure(
        AuditLogger $audit,
        Request $request,
        string $slug,
        string $reason,
    ): void {
        $audit->logRequired(
            actionType: 'login.fail',
            entityType: 'authentication',
            entityId: 'demo:'.$slug,
            newValues: ['reason' => $reason],
            scope: 'security',
            actor: ['name' => 'demo:'.$slug, 'role' => 'guest'],
            request: $request,
        );
    }

    /**
     * List available demo identities (public metadata only).
     */
    public function identities(): JsonResponse
    {
        if (! config('demo_users.enabled')) {
            return response()->json([
                'enabled' => false,
                'identities' => [],
            ]);
        }

        $identities = config('demo_users.identities', []);
        $result = [];

        foreach ($identities as $slug => $identity) {
            $result[] = [
                'slug' => $slug,
                'label' => $identity['label'] ?? $slug,
                'description' => $identity['description'] ?? '',
                'icon' => $identity['icon'] ?? '👤',
                'role' => $identity['role'] ?? 'reader',
            ];
        }

        return response()->json([
            'enabled' => true,
            'identities' => $result,
        ]);
    }
}
