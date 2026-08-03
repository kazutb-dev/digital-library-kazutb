<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Catalog\BibliographicRecord;
use App\Models\LiteratureCollection;
use App\Models\LiteratureCollectionItem;
use App\Services\Member\LiteratureCollectionService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CollectionController extends Controller
{
    public function __construct(private readonly LiteratureCollectionService $collections) {}

    public function index(Request $request): View
    {
        return view('member.collections', [
            'ownCollections' => LiteratureCollection::query()->where('user_id', (string) $request->user()->getKey())->withCount('items')->latest()->get(),
            'publicCollections' => LiteratureCollection::query()->where('visibility', 'public')->where('status', 'published')->withCount('items')->latest('published_at')->limit(12)->get(),
        ]);
    }

    public function show(Request $request, LiteratureCollection $collection): View
    {
        $own = (string) $collection->user_id === (string) $request->user()->getKey();
        abort_unless($own || ($collection->status === 'published' && in_array($collection->visibility, ['public', 'unlisted'], true)), 404);

        return view('member.collection-show', [
            'collection' => $collection->load(['items' => fn ($query) => $query->with('bibliographicRecord')->orderBy('sort_order'), 'creator']),
            'canEdit' => $own && $collection->owner_type === 'reader',
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:3000'], 'collection_type' => ['nullable', Rule::in(['personal', 'favourites', 'read_later'])]]);
        $collection = $this->collections->create($request->user(), $data);

        return redirect()->route('member.collections.show', $collection)->with('success', __('librarian.member_portal.collections.created'));
    }

    public function update(Request $request, LiteratureCollection $collection): RedirectResponse
    {
        $data = $request->validate(['title' => ['required', 'string', 'max:180'], 'description' => ['nullable', 'string', 'max:3000']]);
        $this->collections->update($collection, $request->user(), $data);

        return back()->with('success', __('librarian.member_portal.collections.saved'));
    }

    public function destroy(Request $request, LiteratureCollection $collection): RedirectResponse
    {
        $this->collections->delete($collection, $request->user());

        return redirect()->route('member.collections.index')->with('success', __('librarian.member_portal.collections.deleted'));
    }

    public function add(Request $request, LiteratureCollection $collection): RedirectResponse
    {
        $data = $request->validate(['bibliographic_record_id' => ['required', 'integer', 'exists:bibliographic_records,id'], 'reason' => ['nullable', 'string', 'max:1000']]);
        $record = BibliographicRecord::query()->where('is_draft', false)->findOrFail($data['bibliographic_record_id']);
        $this->collections->add($collection, $request->user(), $record, $data['reason'] ?? null);

        return back()->with('success', __('librarian.member_portal.collections.item_added'));
    }

    public function remove(Request $request, LiteratureCollection $collection, LiteratureCollectionItem $item): RedirectResponse
    {
        $this->collections->remove($collection, $item, $request->user());

        return back()->with('success', __('librarian.member_portal.collections.item_removed'));
    }

    public function reorder(Request $request, LiteratureCollection $collection): RedirectResponse
    {
        $data = $request->validate(['items' => ['required', 'array', 'max:500'], 'items.*' => ['integer']]);
        $this->collections->reorder($collection, $request->user(), $data['items']);

        return back()->with('success', __('librarian.member_portal.collections.saved'));
    }

    public function follow(Request $request, LiteratureCollection $collection): RedirectResponse
    {
        $this->collections->follow($collection, $request->user());

        return back()->with('success', __('librarian.member_portal.collections.followed'));
    }

    public function copy(Request $request, LiteratureCollection $collection): RedirectResponse
    {
        $copy = $this->collections->copy($collection, $request->user());

        return redirect()->route('member.collections.show', $copy)->with('success', __('librarian.member_portal.collections.copied'));
    }
}
