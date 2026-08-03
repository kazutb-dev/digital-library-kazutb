<?php

namespace App\Http\Controllers;

use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

/**
 * Forced password change after an admin reset: the temporary password is the
 * required current credential, and a successful change clears the
 * must_change_password flag.
 */
class PasswordChangeController extends Controller
{
    public function show(): View
    {
        return view('auth.password-change');
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:12', 'max:255', 'confirmed', 'different:current_password'],
        ]);

        $user = $request->user();
        $user->update([
            'password' => Hash::make($request->string('password')->value()),
            'must_change_password' => false,
        ]);

        $audit->logRequired(
            actionType: 'password.change',
            entityType: 'user',
            entityId: $user->getKey(),
            newValues: ['forced_change_completed' => true],
            scope: 'security',
        );

        $role = (string) ($user->getRoleNames()->first() ?: $user->role);
        $destination = match ($role) {
            'admin' => '/admin',
            'librarian' => '/librarian',
            default => '/dashboard',
        };

        return redirect($destination)->with('success', __('admin.profile.password_changed'));
    }
}
