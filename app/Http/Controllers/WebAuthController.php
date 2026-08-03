<?php

namespace App\Http\Controllers;

use App\Exceptions\LibraryAuthenticationException;
use App\Models\User;
use App\Services\AuditLogger;
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

        return redirect()->intended($result['landing']);
    }

    public function logout(Request $request, AuthSessionManager $sessions): RedirectResponse
    {
        $sessions->logout($request);

        return redirect('/login');
    }

    public function demo(
        Request $request,
        string $role,
        AuthSessionManager $sessions,
        AuditLogger $audit,
    ): RedirectResponse {
        if (! (bool) config('demo_users.enabled')) {
            $this->auditDemoFailure($audit, $request, $role, 'demo_login_disabled');
            abort(404);
        }

        $identity = config("demo_users.identities.{$role}");
        if (! is_array($identity)) {
            $this->auditDemoFailure($audit, $request, $role, 'unknown_demo_identity');
            abort(404);
        }

        $user = User::query()->where('email', $identity['email'])->first();
        if ($user === null) {
            $this->auditDemoFailure($audit, $request, $role, 'demo_user_missing');

            return back()->withErrors([
                'login' => __('auth.demo_user_missing', ['email' => $identity['email']]),
            ]);
        }

        try {
            $sessions->login($request, $user, $identity['profile_type'] ?? null);
        } catch (LibraryAuthenticationException $exception) {
            return back()->withErrors(['login' => $exception->getMessage()]);
        }

        $request->session()->put('library.crm_token', 'demo-rbac-'.$role);
        $request->session()->put('library.demo_identity', $role);

        return redirect()->intended($sessions->landing($user));
    }

    private function auditDemoFailure(
        AuditLogger $audit,
        Request $request,
        string $role,
        string $reason,
    ): void {
        $audit->logRequired(
            actionType: 'login.fail',
            entityType: 'authentication',
            entityId: 'demo:'.$role,
            newValues: ['reason' => $reason],
            scope: 'security',
            actor: ['name' => 'demo:'.$role, 'role' => 'guest'],
            request: $request,
        );
    }
}
