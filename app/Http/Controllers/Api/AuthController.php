<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\LibraryAuthenticationException;
use App\Http\Controllers\Controller;
use App\Services\AuthSessionManager;
use App\Services\LibraryAuthenticator;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class AuthController extends Controller
{
    public function login(Request $request, LibraryAuthenticator $authenticator): JsonResponse
    {
        $validated = $request->validate([
            'email' => ['nullable', 'string', 'email', 'max:191', 'required_without:login'],
            'login' => ['nullable', 'string', 'max:191', 'required_without:email'],
            'password' => ['required', 'string', 'max:1024'],
            'device_name' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $result = $authenticator->authenticate(
                $request,
                (string) ($validated['login'] ?? $validated['email']),
                $validated['password'],
                $validated['device_name'] ?? 'web',
            );

            return response()->json([
                'success' => true,
                'user' => $result['session_user'],
                'landing' => $result['landing'],
            ]);
        } catch (LibraryAuthenticationException $exception) {
            return response()->json(['message' => $exception->getMessage()], $exception->status);
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

    public function logout(Request $request, AuthSessionManager $sessions): JsonResponse
    {
        $sessions->logout($request);

        return response()->json([
            'success' => true,
        ]);
    }
}
