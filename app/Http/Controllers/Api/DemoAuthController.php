<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class DemoAuthController extends Controller
{
    /**
     * Quick-login as a predefined demo identity.
     *
     * Gated by config('demo_auth.enabled'). Returns 403 when disabled.
     */
    public function login(Request $request): JsonResponse
    {
        if (! config('demo_auth.enabled')) {
            return response()->json([
                'message' => 'Demo login is not available.',
            ], 403);
        }

        $validated = $request->validate([
            'role' => ['required', 'string', 'max:50'],
        ]);

        $slug = $validated['role'];
        $identities = config('demo_auth.identities', []);

        if (! isset($identities[$slug]) || ! is_array($identities[$slug])) {
            return response()->json([
                'message' => 'Unknown demo identity.',
            ], 422);
        }

        $identity = $identities[$slug];

        $user = [
            'id' => (string) ($identity['id'] ?? ''),
            'name' => (string) ($identity['name'] ?? ''),
            'email' => (string) ($identity['email'] ?? ''),
            'login' => (string) ($identity['login'] ?? ''),
            'ad_login' => (string) ($identity['ad_login'] ?? ''),
            'role' => (string) ($identity['role'] ?? 'reader'),
            'title' => (string) ($identity['title'] ?? ''),
            'phone_extension' => (string) ($identity['phone_extension'] ?? ''),
        ];

        $request->session()->regenerate();
        $request->session()->put('library.crm_token', 'demo-token-' . $slug);
        $request->session()->put('library.user', $user);
        $request->session()->put('library.authenticated_at', now()->toIso8601String());
        $request->session()->put('library.demo_identity', $slug);

        Log::info('Demo quick-login', [
            'ip' => $request->ip(),
            'slug' => $slug,
            'role' => $user['role'],
        ]);

        return response()->json([
            'success' => true,
            'user' => $user,
        ]);
    }

    /**
     * List available demo identities (public metadata only).
     */
    public function identities(): JsonResponse
    {
        if (! config('demo_auth.enabled')) {
            return response()->json([
                'enabled' => false,
                'identities' => [],
            ]);
        }

        $identities = config('demo_auth.identities', []);
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
