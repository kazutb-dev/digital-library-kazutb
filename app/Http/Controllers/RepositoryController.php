<?php

namespace App\Http\Controllers;

use App\Models\Catalog\RepositoryItem;
use App\Models\Setting;
use App\Services\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Public scholarly repository (Master.md §20.3).
 *
 * Metadata of published works is open to everyone, guests included. The stored
 * file itself is never exposed through a public storage URL: it is streamed by
 * download() only for viewers holding `repository.read_full`, and only for a
 * record whose status is `published`.
 *
 * Data source is the canonical repository_items table via
 * App\Models\Catalog\RepositoryItem — the former config-backed
 * ScholarlyRepositoryService no longer serves these routes.
 */
class RepositoryController extends Controller
{
    public function index(Request $request): View
    {
        // Public GET surface: query strings are sanitised rather than validated
        // so that a hand-edited URL renders the unfiltered page instead of
        // bouncing the visitor with a 302 + validation errors.
        $search = trim((string) $request->query('q', ''));
        $search = $search === '' ? null : mb_substr($search, 0, 200);

        $workType = (string) $request->query('work_type', '');
        $workType = in_array($workType, RepositoryItem::WORK_TYPES, true) ? $workType : null;

        $years = $this->publishedYears();
        $year = (int) $request->query('year', 0);
        $year = $years->contains($year) ? $year : null;

        $query = RepositoryItem::query()->published();

        if ($search !== null) {
            $query->search($search);
        }
        if ($workType !== null) {
            $query->where('work_type', $workType);
        }
        if ($year !== null) {
            $query->where('year', $year);
        }

        $items = $query
            ->orderByDesc('published_at')
            ->orderByDesc('year')
            ->orderBy('title')
            ->paginate(Setting::resultsPerPage())
            ->withQueryString();

        $typeCounts = RepositoryItem::query()
            ->published()
            ->selectRaw('work_type, count(*) as total')
            ->groupBy('work_type')
            ->pluck('total', 'work_type');

        return view('repository.index', [
            'activePage' => 'repository',
            'items' => $items,
            'typeCounts' => $typeCounts,
            'years' => $years,
            'publishedTotal' => (int) $typeCounts->sum(),
            'activeType' => $workType,
            'activeYear' => $year,
            'search' => $search,
        ]);
    }

    public function show(Request $request, RepositoryItem $item): View
    {
        abort_unless($item->status === 'published', 404);

        $user = $request->user();

        // Full-text authorisation is decided here, not in the template: the
        // download endpoint repeats the same check, so hiding or showing the
        // button never grants access on its own.
        $canReadFull = $user !== null && $user->can('repository.read_full');
        $hasFile = is_string($item->file_path) && trim($item->file_path) !== '';

        $related = RepositoryItem::query()
            ->published()
            ->where('work_type', $item->work_type)
            ->whereKeyNot($item->getKey())
            ->orderByDesc('published_at')
            ->limit(2)
            ->get();

        return view('repository.show', [
            'activePage' => 'repository',
            'item' => $item,
            'related' => $related,
            'canReadFull' => $canReadFull,
            'hasFile' => $hasFile,
            'isAuthenticated' => $user !== null,
        ]);
    }

    /**
     * Stream the stored full text from the private `local` disk.
     *
     * Never returns a storage URL: unpublished records and viewers without
     * `repository.read_full` are refused outright.
     */
    public function download(Request $request, RepositoryItem $item, AuditLogger $audit): StreamedResponse
    {
        abort_unless($item->status === 'published', 403);

        $user = $request->user();
        abort_unless($user !== null && $user->can('repository.read_full'), 403);

        $path = trim((string) $item->file_path);
        abort_if($path === '' || ! Storage::disk('local')->exists($path), 404);

        $audit->log(
            actionType: 'repository.read_full',
            entityType: 'repository_item',
            entityId: $item->getKey(),
            scope: 'operational',
            metadata: ['title' => $item->title],
            actor: $user,
            request: $request,
        );

        return Storage::disk('local')->download($path, $item->file_name ?: basename($path));
    }

    /**
     * Distinct publication years of the published set, newest first.
     *
     * @return Collection<int, int>
     */
    private function publishedYears(): Collection
    {
        return RepositoryItem::query()
            ->published()
            ->whereNotNull('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(static fn ($year): int => (int) $year)
            ->values();
    }
}
