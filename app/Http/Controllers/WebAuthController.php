<?php

namespace App\Http\Controllers;

use App\Exceptions\LibraryAuthenticationException;
use App\Services\AuthSessionManager;
use App\Services\LibraryAuthenticator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class WebAuthController extends Controller
{
    public function login(Request $request, LibraryAuthenticator $authenticator): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['nullable', 'string', 'email', 'max:191', 'required_without:login'],
            'login' => ['nullable', 'string', 'max:191', 'required_without:email'],
            'password' => ['required', 'string', 'max:1024'],
            'device_name' => ['nullable', 'string', 'max:255'],
            'redirect' => ['nullable', 'string', 'max:2048'],
        ]);

        try {
            $result = $authenticator->authenticate(
                $request,
                (string) ($validated['login'] ?? $validated['email']),
                $validated['password'],
                $validated['device_name'] ?? 'web',
            );
        } catch (LibraryAuthenticationException $exception) {
            return back()->withErrors(['login' => $exception->getMessage()])->withInput(
                $request->only(['email', 'login'])
            );
        }

        $redirect = (string) ($validated['redirect'] ?? '');
        if ($redirect !== '' && str_starts_with($redirect, '/') && ! str_starts_with($redirect, '//') && ! str_contains($redirect, '\\')) {
            return redirect($redirect);
        }

        return redirect()->intended($result['landing']);
    }

    public function logout(Request $request, AuthSessionManager $sessions): RedirectResponse
    {
        $sessions->logout($request);

        return redirect('/login');
    }

}
