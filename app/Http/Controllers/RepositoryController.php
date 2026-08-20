<?php

namespace App\Http\Controllers;

use App\Models\Catalog\RepositoryAuthor;
use App\Models\Catalog\RepositoryItem;
use App\Models\Setting;
use App\Models\User;
use App\Services\Repository\RepositoryUsageRecorder;
use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\IpUtils;
use Symfony\Component\HttpFoundation\Response;

/**
 * Public scholarly repository (Master.md 20.3).
 *
 * Approved metadata is open to everyone, guests included. Full text remains
 * governed independently by the selected access policy. The stored PDF is
 * never exposed through a public storage URL.
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
        $search = $this->cleanQueryValue($request->query('q'), 200);

        $workTypeInput = $this->cleanQueryValue($request->query('work_type'), 64);
        $workType = in_array($workTypeInput, RepositoryItem::acceptedWorkTypes(), true)
            ? RepositoryItem::normaliseWorkType($workTypeInput)
            : null;

        $years = $this->publishedYears();
        $yearInput = $request->query('year');
        $year = (is_string($yearInput) || is_int($yearInput)) && ctype_digit((string) $yearInput)
            ? (int) $yearInput
            : null;
        $year = $year !== null && $years->contains($year) ? $year : null;

        $languageInput = $this->cleanQueryValue($request->query('language'), 8);
        $language = in_array($languageInput, ['kk', 'ru', 'en'], true) ? $languageInput : null;

        $accessInput = $this->cleanQueryValue($request->query('access'), 64);
        $access = in_array($accessInput, RepositoryItem::acceptedAccessPolicies(), true)
            ? RepositoryItem::normaliseAccessPolicy($accessInput)
            : null;

        $faculty = $this->cleanFacetValue($request->query('faculty'));
        $department = $this->cleanFacetValue($request->query('department'));
        $educationalProgramme = $this->cleanFacetValue($request->query('educational_programme'));
        $author = $this->cleanFacetValue($request->query('author'));
        $supervisor = $this->cleanFacetValue($request->query('supervisor'));
        $udc = $this->cleanFacetValue($request->query('udc'), 64);

        $popularAvailable = Schema::hasTable('repository_usage_daily')
            && RepositoryItem::query()
                ->publicMetadata()
                ->whereHas('usageDaily', fn ($usage) => $usage->where('event_count', '>', 0))
                ->exists();
        $sortInput = $this->cleanQueryValue($request->query('sort'), 24);
        $sort = $sortInput === 'popular' && $popularAvailable ? 'popular' : 'latest';

        $query = RepositoryItem::query()->publicMetadata();

        if ($search !== null) {
            $query->search($search);
        }
        if ($workType !== null) {
            $query->whereIn('work_type', RepositoryItem::equivalentWorkTypes($workType));
        }
        if ($year !== null) {
            $query->where('year', $year);
        }
        if ($language !== null) {
            $query->where('language', $language);
        }
        if ($access !== null) {
            if ($access === 'full_public') {
                // "Open access" is an availability promise, not merely the
                // value stored before a possible embargo. Reuse the canonical
                // policy scope so active embargoes/tombstones never appear.
                $query->publiclyAvailable();
            } else {
                $query->whereIn('access_policy', RepositoryItem::equivalentAccessPolicies($access));
            }
        }
        if ($faculty !== null) {
            $query->where('faculty', $faculty);
        }
        if ($department !== null) {
            $query->where('department', $department);
        }
        if ($educationalProgramme !== null) {
            $query->where('educational_programme', $educationalProgramme);
        }
        if ($author !== null) {
            $normalisedAuthor = mb_strtolower($author);
            $query->where(function ($authors) use ($author, $normalisedAuthor): void {
                if (Schema::hasTable('repository_authors')) {
                    $authors->whereHas('authorsList', function ($normalised) use ($normalisedAuthor): void {
                        $normalised
                            ->whereRaw('LOWER(display_name) = ?', [$normalisedAuthor])
                            ->orWhere('normalised_name', $normalisedAuthor);
                    });
                }

                // Older imports only have the JSON authors array. An exact,
                // parameter-bound JSON membership check avoids the false
                // positives and wildcard injection of a CAST(... LIKE ...).
                $authors->orWhereJsonContains('authors', $author);
            });
        }
        if ($supervisor !== null) {
            $query->where('supervisor', $supervisor);
        }
        if ($udc !== null) {
            $query->where('udc_code', $udc);
        }

        if ($sort === 'popular') {
            $query
                ->withSum('usageDaily as popularity_total', 'event_count')
                ->orderByDesc('popularity_total');
        }

        $activeQuery = array_filter([
            'q' => $search,
            'work_type' => $workType,
            'year' => $year,
            'language' => $language,
            'access' => $access,
            'faculty' => $faculty,
            'department' => $department,
            'educational_programme' => $educationalProgramme,
            'author' => $author,
            'supervisor' => $supervisor,
            'udc' => $udc,
            'sort' => $sort === 'popular' ? $sort : null,
        ], static fn (mixed $value): bool => $value !== null && $value !== '');

        $items = $query
            ->orderByDesc('published_at')
            ->orderByDesc('year')
            ->orderBy('title')
            ->orderBy('id')
            ->paginate(Setting::resultsPerPage())
            ->appends($activeQuery);

        // The shared language/navigation shell reads the current request when
        // building alternate-locale links. Replace its query bag only after
        // pagination resolved the requested page, so rejected arrays, markup,
        // and unknown parameters cannot be reflected elsewhere in the layout.
        $localeQuery = $this->cleanQueryValue($request->query('lang'), 8);
        $sanitisedRequestQuery = $activeQuery;
        if (in_array($localeQuery, ['kk', 'ru', 'en'], true)) {
            $sanitisedRequestQuery['lang'] = $localeQuery;
        }
        if ($items->currentPage() > 1) {
            $sanitisedRequestQuery['page'] = $items->currentPage();
        }
        $request->query->replace($sanitisedRequestQuery);
        $request->server->set('QUERY_STRING', http_build_query($sanitisedRequestQuery));

        $typeCounts = RepositoryItem::query()
            ->publicMetadata()
            ->selectRaw('work_type, count(*) as total')
            ->groupBy('work_type')
            ->pluck('total', 'work_type')
            ->reduce(function (Collection $counts, int|string $total, string $type): Collection {
                $canonical = RepositoryItem::normaliseWorkType($type) ?? $type;
                $counts[$canonical] = (int) ($counts[$canonical] ?? 0) + (int) $total;

                return $counts;
            }, collect());

        return view('repository.index', [
            'activePage' => 'repository',
            'items' => $items,
            'typeCounts' => $typeCounts,
            'years' => $years,
            'publishedTotal' => (int) $typeCounts->sum(),
            'activeType' => $workType,
            'activeYear' => $year,
            'activeLanguage' => $language,
            'activeAccess' => $access,
            'activeFaculty' => $faculty,
            'activeDepartment' => $department,
            'activeEducationalProgramme' => $educationalProgramme,
            'activeAuthor' => $author,
            'activeSupervisor' => $supervisor,
            'activeUdc' => $udc,
            'activeSort' => $sort,
            'search' => $search,
            'facultyOptions' => $this->publicFacetValues('faculty'),
            'departmentOptions' => $this->publicFacetValues('department'),
            'educationalProgrammeOptions' => $this->publicFacetValues('educational_programme'),
            'supervisorOptions' => $this->publicFacetValues('supervisor'),
            'udcOptions' => $this->publicFacetValues('udc_code'),
            'authorOptions' => $this->publicAuthorFacetValues(),
            'popularAvailable' => $popularAvailable,
        ]);
    }

    public function show(Request $request, RepositoryItem $item, RepositoryUsageRecorder $usage): Response
    {
        abort_unless($item->isPublicMetadataVisible(), 404);

        $user = $request->user();
        $isWithdrawn = $item->status === 'withdrawn';

        // Full-text authorisation is decided here, not in the template: the
        // download endpoint repeats the same check, so hiding or showing the
        // button never grants access on its own.
        $canReadFull = ! $isWithdrawn && $item->canExposeFullText($user, $this->isOnCampus($request));
        $hasFile = ! $isWithdrawn && $item->hasStoredPublishablePdf();

        $related = RepositoryItem::query()
            ->publicMetadata()
            ->whereIn('work_type', RepositoryItem::equivalentWorkTypes($item->work_type))
            ->whereKeyNot($item->getKey())
            ->orderByDesc('published_at')
            ->limit(2)
            ->get();

        $linkedNews = collect();
        if (Schema::hasTable('news') && Schema::hasColumn('news', 'repository_item_id')) {
            $linkedNews = $item->linkedNews()
                ->published()
                ->when(
                    Schema::hasColumn('news', 'visibility'),
                    fn ($news) => $news->where('visibility', 'public'),
                )
                ->latest(Schema::hasColumn('news', 'published_at') ? 'published_at' : 'publish_at')
                ->limit(5)
                ->get();
        }

        // response()->view renders immediately. A broken template therefore
        // cannot inflate usage totals with a request that never succeeded.
        $response = response()->view('repository.show', [
            'activePage' => 'repository',
            'item' => $item,
            'related' => $related,
            'linkedNews' => $linkedNews,
            'canReadFull' => $canReadFull,
            'hasFile' => $hasFile,
            'isAuthenticated' => $user !== null,
            'isWithdrawn' => $isWithdrawn,
            'citation' => $this->citation($item),
        ]);
        $this->recordUsage($request, $usage, $item, 'metadata_view', $user);

        return $response;
    }

    /**
     * Stream the stored full text from the private `local` disk.
     *
     * Never returns a storage URL: drafts and tombstones return 404, while a
     * visible metadata record with a closed file policy returns 403.
     */
    public function download(Request $request, RepositoryItem $item, RepositoryUsageRecorder $usage): Response
    {
        $path = $this->authorisedFilePath($request, $item);
        $response = Storage::disk('local')->download($path, $item->file_name ?: basename($path), [
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $this->recordUsage($request, $usage, $item, 'download', $request->user());

        return $response;
    }

    /**
     * Inline PDF response. Symfony's BinaryFileResponse implements single
     * byte-range requests, so browser PDF viewers can seek without downloading
     * the complete work first (Accept-Ranges / 206 / Content-Range).
     */
    public function view(Request $request, RepositoryItem $item, RepositoryUsageRecorder $usage): BinaryFileResponse
    {
        $path = $this->authorisedFilePath($request, $item);
        $range = (string) $request->header('Range', '');
        if ($range === '' || str_starts_with($range, 'bytes=0-')) {
            // Recorded only after BinaryFileResponse accepts the range below.
        }

        $response = response()->file(Storage::disk('local')->path($path), [
            'Content-Type' => 'application/pdf',
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ]);
        $response->setContentDisposition('inline', $item->file_name ?: basename($path));
        $response->prepare($request);
        if ($request->isMethod('GET')
            && in_array($response->getStatusCode(), [200, 206], true)
            && ($range === '' || str_starts_with($range, 'bytes=0-'))) {
            $this->recordUsage($request, $usage, $item, 'pdf_view', $request->user());
        }

        return $response;
    }

    /**
     * Distinct publication years of the published set, newest first.
     *
     * @return Collection<int, int>
     */
    private function publishedYears(): Collection
    {
        return RepositoryItem::query()
            ->publicMetadata()
            ->whereNotNull('year')
            ->distinct()
            ->orderByDesc('year')
            ->pluck('year')
            ->map(static fn ($year): int => (int) $year)
            ->values();
    }

    /**
     * Clean a scalar public-query value without turning arrays into the word
     * "Array" or reflecting control/format characters into the page.
     */
    private function cleanQueryValue(mixed $value, int $maxLength): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $clean = preg_replace('/[\p{C}]+/u', ' ', (string) $value);
        if ($clean === null) {
            return null;
        }
        $clean = preg_replace('/\s+/u', ' ', trim($clean));

        return $clean === null || $clean === '' ? null : mb_substr($clean, 0, $maxLength);
    }

    /** Public metadata facets are exact values, never raw SQL fragments. */
    private function cleanFacetValue(mixed $value, int $maxLength = 255): ?string
    {
        if (! is_string($value) && ! is_int($value)) {
            return null;
        }

        $clean = preg_replace('/[\p{C}]+/u', ' ', (string) $value);
        $clean = $clean === null ? null : preg_replace('/\s+/u', ' ', trim($clean));

        // Angle brackets are not meaningful in these bibliographic facets and
        // are rejected rather than merely escaped when reflected into inputs.
        return $clean === null
            || $clean === ''
            || mb_strlen($clean) > $maxLength
            || str_contains($clean, '<')
            || str_contains($clean, '>')
            ? null
            : $clean;
    }

    /**
     * Suggestions are derived exclusively from director-approved public
     * metadata. A hard UI bound keeps a malformed/legacy vocabulary from
     * producing an unbounded datalist; exact typed values remain filterable.
     *
     * @return Collection<int, string>
     */
    private function publicFacetValues(string $column): Collection
    {
        abort_unless(in_array($column, [
            'faculty', 'department', 'educational_programme', 'supervisor', 'udc_code',
        ], true), 500);

        return RepositoryItem::query()
            ->publicMetadata()
            ->whereNotNull($column)
            ->where($column, '!=', '')
            ->select($column)
            ->distinct()
            ->orderBy($column)
            ->limit(300)
            ->pluck($column)
            ->map(fn (mixed $value): ?string => $this->cleanFacetValue(
                $value,
                $column === 'udc_code' ? 64 : 255,
            ))
            ->filter()
            ->unique()
            ->values();
    }

    /**
     * Prefer canonical author rows for suggestions. Legacy JSON-only authors
     * are still supported by the exact filter above without loading every
     * historical JSON document on every public request.
     *
     * @return Collection<int, string>
     */
    private function publicAuthorFacetValues(): Collection
    {
        $normalised = Schema::hasTable('repository_authors')
            ? RepositoryAuthor::query()
                ->whereHas('item', fn ($items) => $items->publicMetadata())
                ->where('display_name', '!=', '')
                ->select('display_name')
                ->distinct()
                ->orderBy('display_name')
                ->limit(300)
                ->pluck('display_name')
            : collect();

        // Transitional records can legitimately predate repository_authors.
        // Read a bounded slice of already-public metadata and merge its JSON
        // names into the same suggestions; no internal record is consulted.
        $legacy = RepositoryItem::query()
            ->publicMetadata()
            ->select(['id', 'authors'])
            ->orderByDesc('published_at')
            ->limit(300)
            ->get()
            ->flatMap(static fn (RepositoryItem $item): array => is_array($item->authors) ? $item->authors : []);

        return $normalised
            ->concat($legacy)
            ->map(fn (mixed $value): ?string => $this->cleanFacetValue($value))
            ->filter()
            ->unique(static fn (string $value): string => mb_strtolower($value))
            ->sortBy(static fn (string $value): string => mb_strtolower($value))
            ->take(300)
            ->values();
    }

    private function authorisedFilePath(Request $request, RepositoryItem $item): string
    {
        abort_unless($item->isPublicMetadataVisible(), 404);
        abort_if($item->status === 'withdrawn', 404);
        abort_unless($item->canExposeFullText($request->user(), $this->isOnCampus($request)), 403);

        $path = trim((string) $item->file_path);
        abort_if($path === '' || ! $item->hasStoredPublishablePdf(), 404);

        return $path;
    }

    private function recordUsage(Request $request, RepositoryUsageRecorder $usage, RepositoryItem $item, string $eventType, ?User $user): void
    {
        // A rolling deployment may briefly serve the new controller before
        // its additive usage-events migration reaches every application pod.
        if (! $request->isMethod('GET') || ! Schema::hasTable('repository_usage_daily')) {
            return;
        }

        try {
            $usage->record($item, $eventType, $user, app()->getLocale());
        } catch (\Throwable) {
            // Analytics is deliberately non-critical: a transient counter
            // failure must never make an otherwise authorised work unavailable.
        }
    }

    private function isOnCampus(Request $request): bool
    {
        $ip = $request->ip();

        return $ip !== null && IpUtils::checkIp($ip, (array) config('digital_access.campus_ranges', []));
    }

    private function citation(RepositoryItem $item): string
    {
        $authors = implode(', ', (array) $item->authors);

        return trim($authors.'. '.$item->title.'. '.$item->university.'. '.($item->year ?: '').'. '.route('repository.show', $item));
    }
}
