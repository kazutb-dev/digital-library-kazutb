<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Branch;
use App\Models\Catalog\ElectronicMaterial;
use App\Models\Catalog\ReaderProfile;
use App\Models\ContactMessage;
use App\Models\LiteratureCollection;
use App\Services\AuditLogger;
use App\Services\Library\CatalogReadService;
use App\Services\Library\DigitalAccessService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PortalController extends Controller
{
    public function profile(Request $request): View
    {
        return view('member.profile', [
            'profile' => ReaderProfile::forUser($request->user())->load('preferredBranch'),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(),
        ]);
    }

    public function updateProfile(Request $request, AuditLogger $audit): RedirectResponse
    {
        $profile = ReaderProfile::forUser($request->user());
        $validated = $request->validate([
            'phone' => ['nullable', 'string', 'max:40', 'regex:/^[+0-9()\-\s]*$/'],
            'additional_email' => ['nullable', 'email:rfc', 'max:255'],
            'locale' => ['required', Rule::in(['ru', 'kk', 'en'])],
            'preferred_branch_id' => ['nullable', 'integer', 'exists:branches,id'],
            'notification_preferences' => ['nullable', 'array'],
            'notification_preferences.email' => ['nullable', 'boolean'],
            'notification_preferences.reservations' => ['nullable', 'boolean'],
            'notification_preferences.news' => ['nullable', 'boolean'],
            'notification_preferences.messages' => ['nullable', 'boolean'],
            'notification_preferences.digital' => ['nullable', 'boolean'],
            'accessibility_preferences' => ['nullable', 'array'],
            'accessibility_preferences.high_contrast' => ['nullable', 'boolean'],
            'accessibility_preferences.large_text' => ['nullable', 'boolean'],
        ]);
        $old = $profile->only(['phone', 'additional_email', 'preferred_branch_id', 'notification_preferences', 'accessibility_preferences']);
        $profile->update([
            'phone' => $validated['phone'] ?? null,
            'additional_email' => $validated['additional_email'] ?? null,
            'preferred_branch_id' => $validated['preferred_branch_id'] ?? null,
            'notification_preferences' => $validated['notification_preferences'] ?? [],
            'accessibility_preferences' => $validated['accessibility_preferences'] ?? [],
        ]);
        $request->user()->update(['locale' => $validated['locale']]);
        $audit->logRequired('profile.updated', 'reader_profile', $profile->getKey(), oldValues: $old, newValues: $profile->only(array_keys($old)), scope: 'personal', actor: $request->user());
        $audit->logRequired('notification.preferences_updated', 'reader_profile', $profile->getKey(), newValues: ['preferences' => array_keys(array_filter($profile->notification_preferences ?? []))], scope: 'personal', actor: $request->user());

        return back()->with('success', __('librarian.member_portal.profile.saved'));
    }

    public function digitalMaterials(Request $request, DigitalAccessService $access): View
    {
        $materials = ElectronicMaterial::query()->where('is_active', true)
            ->with('bibliographicRecord')->latest()->paginate(12)->through(function (ElectronicMaterial $material) use ($access, $request): ?array {
                $resolved = $access->resolve((string) $material->getKey());
                if ($resolved === null || ! $access->canAccess($resolved, $request)) {
                    return null;
                }

                return ['model' => $material, 'payload' => $access->toReaderPayload($resolved, $request)];
            });
        $materials->setCollection($materials->getCollection()->filter()->values());

        return view('member.digital-materials', ['materials' => $materials]);
    }

    public function search(Request $request, CatalogReadService $catalog): View
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:200'], 'language' => ['nullable', 'string', 'max:8'],
            'branch' => ['nullable', 'string', 'max:100'], 'available' => ['nullable', 'boolean'],
            'digital' => ['nullable', 'boolean'], 'udc' => ['nullable', 'string', 'max:64'],
        ]);
        $results = $catalog->search(
            query: $validated['q'] ?? '', udc: $validated['udc'] ?? null,
            language: $validated['language'] ?? null, page: max(1, (int) $request->query('page', 1)),
            limit: 12, availableOnly: (bool) ($validated['available'] ?? false),
            physicalOnly: false, branch: $validated['branch'] ?? null,
            format: ! empty($validated['digital']) ? 'electronic' : null,
        );

        // CatalogReadService intentionally exposes the rich public-catalogue
        // payload (nested title/isbn/copy objects). The compact member search
        // card has a smaller view model; flatten it here so Blade never tries
        // to escape an array and optional catalogue fields stay harmless.
        $books = collect($results['data'])->map(static fn (array $book): array => [
            'id' => (string) ($book['id'] ?? ''),
            'title' => (string) data_get($book, 'title.display', data_get($book, 'title.raw', '')),
            'author' => (string) ($book['primaryAuthor'] ?? data_get($book, 'authors.0', '')),
            'isbn' => (string) data_get($book, 'isbn.raw', ''),
            'available_copies' => (int) data_get($book, 'copies.available', 0),
        ])->all();

        return view('member.search', [
            'results' => $books, 'meta' => $results['meta'],
            'collections' => LiteratureCollection::query()->where('user_id', (string) $request->user()->getKey())->orderBy('title')->get(),
        ]);
    }

    public function messages(Request $request): View
    {
        return view('member.messages', [
            'memberMessages' => ContactMessage::query()->where('sender_id', $request->user()->getKey())->latest()->paginate(10),
            'messageCategories' => ContactMessage::CATEGORIES,
        ]);
    }

    public function message(Request $request, ContactMessage $message): View
    {
        abort_unless((int) $message->sender_id === (int) $request->user()->getKey(), 404);

        return view('member.message-show', ['message' => $message]);
    }
}
