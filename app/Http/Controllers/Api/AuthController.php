<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AuthController extends Controller
{
    public function login(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['nullable', 'string', 'email', 'required_without:login'],
            'login' => ['nullable', 'string', 'required_without:email'],
            'password' => ['required', 'string'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        $loginIdentifier = $validated['login'] ?? $validated['email'] ?? 'unknown';
        $authApiUrl = (string) config('services.external_auth.login_url', 'http://crm.local/api/login');

        try {
            $response = Http::timeout(12)->acceptJson()->post($authApiUrl, [
                'email' => $validated['email'] ?? null,
                'login' => $validated['login'] ?? null,
                'password' => $validated['password'],
                'device_name' => $validated['device_name'] ?? 'web',
            ]);

            if ($response->successful()) {
                $payload = $response->json();
                $token = (string) ($payload['token'] ?? $payload['access_token'] ?? '');

                if ($token === '') {
                    Log::warning('CRM auth returned success but no token', [
                        'ip' => $request->ip(),
                        'login' => $loginIdentifier,
                    ]);

                    return response()->json([
                        'message' => 'Authentication service returned an unexpected response.',
                    ], 502);
                }

                $rawUser = $payload['user'] ?? $payload['data']['user'] ?? [];
                $user = $this->normalizeSessionUser(is_array($rawUser) ? $rawUser : []);

                $request->session()->regenerate();
                $request->session()->put('library.crm_token', $token);
                $request->session()->put('library.user', $user);
                $request->session()->put('library.authenticated_at', now()->toIso8601String());

                Log::info('Library CRM login successful', [
                    'ip' => $request->ip(),
                    'login' => $loginIdentifier,
                    'role' => $user['role'],
                ]);

                return response()->json([
                    'success' => true,
                    'user' => $user,
                ]);
            }

            Log::warning('Library CRM login failed', [
                'ip' => $request->ip(),
                'login' => $loginIdentifier,
                'crm_status' => $response->status(),
            ]);

            return response()->json([
                'message' => 'Неверный логин или пароль.',
            ], 401);
        } catch (\Throwable $exception) {
            Log::error('CRM auth service unavailable', [
                'ip' => $request->ip(),
                'login' => $loginIdentifier,
                'error' => $exception->getMessage(),
            ]);

            return response()->json([
                'message' => 'Сервис авторизации временно недоступен. Попробуйте позже.',
            ], 503);
        }
    }

    public function me(Request $request): JsonResponse
    {
        $user = $request->attributes->get('authenticated_reader');

        return response()->json([
            'authenticated' => true,
            'user' => $user,
            'authenticated_at' => $request->session()->get('library.authenticated_at'),
        ]);
    }

    public function logout(Request $request): JsonResponse
    {
        $crmToken = (string) $request->session()->get('library.crm_token', '');
        $logoutApiUrl = (string) config('services.external_auth.logout_url', '');

        if ($crmToken !== '' && $logoutApiUrl !== '') {
            try {
                Http::timeout(8)
                    ->acceptJson()
                    ->withToken($crmToken)
                    ->post($logoutApiUrl);
            } catch (\Throwable $exception) {
                Log::warning('CRM logout request failed', [
                    'ip' => $request->ip(),
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return response()->json([
            'success' => true,
        ]);
    }

    /**
     * @param  array<string, mixed>  $user
     * @return array<string, string>
     */
    private function normalizeSessionUser(array $user): array
    {
        $role = mb_strtolower(trim((string) ($user['role'] ?? 'reader')));
        $allowedRoles = ['reader', 'librarian', 'admin'];

        if (! in_array($role, $allowedRoles, true)) {
            $role = 'reader';
        }

        return [
            'id' => (string) ($user['id'] ?? ''),
            'name' => (string) ($user['name'] ?? ''),
            'email' => (string) ($user['email'] ?? ''),
            'login' => (string) ($user['login'] ?? ''),
            'ad_login' => (string) ($user['ad_login'] ?? ''),
            'role' => $role,
        ];
    }
}
