<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Self-service profile for staff working in the admin panel: own name,
 * email, interface locale, and password (with current-password proof).
 */
class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        return view('admin.profile.edit', ['profileUser' => $request->user()]);
    }

    public function update(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255', Rule::unique('users', 'email')->ignore($user)],
            'locale' => ['required', Rule::in(['ru', 'kk', 'en'])],
        ]);

        $old = [
            'name' => $user->name,
            'email' => $user->email,
            'locale' => $user->locale,
        ];

        $user->update([
            'name' => $validated['name'],
            'email' => mb_strtolower($validated['email']),
            'locale' => $validated['locale'],
        ]);

        session(['locale' => $validated['locale']]);

        if ($old !== ['name' => $user->name, 'email' => $user->email, 'locale' => $user->locale]) {
            $audit->logRequired(
                actionType: 'update',
                entityType: 'user',
                entityId: $user->getKey(),
                oldValues: $old,
                newValues: ['name' => $user->name, 'email' => $user->email, 'locale' => $user->locale],
                reason: 'Self-service profile update',
                scope: 'security',
            );
        }

        return back()->with('success', __('common.updated_successfully'));
    }

    public function updatePassword(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'string', 'min:12', 'max:255', 'confirmed', 'different:current_password'],
        ]);

        $user->update([
            'password' => Hash::make($request->string('password')->value()),
            'must_change_password' => false,
        ]);

        $audit->logRequired(
            actionType: 'password.change',
            entityType: 'user',
            entityId: $user->getKey(),
            newValues: ['self_service' => true],
            scope: 'security',
        );

        return back()->with('success', __('admin.profile.password_changed'));
    }
}
