<?php

namespace App\Http\Controllers\Librarian;

use App\Http\Controllers\Controller;
use App\Services\AuditLogger;
use App\Support\LocaleResolver;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

final class ProfileController extends Controller
{
    public function show(Request $request): View
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $role = $user->effectiveRole();
        $directions = collect([
            'catalog' => ['catalog.search', 'catalog.view_full_metadata', 'catalog.create_record', 'catalog.edit_record'],
            'copies' => ['copies.create', 'copies.edit', 'copies.delete'],
            'quality' => ['data_quality.view', 'data_quality.correct', 'data_quality.view_reports'],
            'circulation' => ['circulation.issue', 'circulation.return'],
            'readers' => ['circulation.issue', 'circulation.return'],
            'reports' => ['reports.view_ops', 'reports.view_full', 'reports.view_acquisitions'],
            'repository' => ['repository.upload', 'repository.approve', 'repository.publish'],
            'messages' => ['messages.view_all', 'messages.view_assigned'],
        ])->filter(fn (array $permissions): bool => $user->canAny($permissions))->keys()->values();

        return view('librarian.profile', [
            'profileUser' => $user,
            'profileRole' => $role,
            'workDirections' => $directions,
        ]);
    }

    public function updatePreferences(Request $request, AuditLogger $audit): RedirectResponse
    {
        $user = $request->user();
        abort_if($user === null, 401);

        $validated = $request->validate([
            'locale' => ['required', Rule::in(LocaleResolver::SUPPORTED)],
        ]);
        $oldLocale = (string) $user->locale;
        $newLocale = (string) $validated['locale'];

        if ($oldLocale !== $newLocale) {
            $user->forceFill(['locale' => $newLocale])->save();
            $request->session()->put('locale', $newLocale);
            $request->session()->put('library.user.locale', $newLocale);
            $audit->logRequired(
                actionType: 'update',
                entityType: 'user',
                entityId: $user->getKey(),
                oldValues: ['locale' => $oldLocale],
                newValues: ['locale' => $newLocale],
                reason: 'Staff interface language preference',
                scope: 'personal',
                actor: $user,
                request: $request,
            );
        }

        return redirect()->route('librarian.profile.show', ['lang' => $newLocale])
            ->with('success', __('librarian.staff_profile.saved', locale: $newLocale));
    }
}
