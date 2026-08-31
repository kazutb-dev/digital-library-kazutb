<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use App\Services\Library\CatalogReadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Throwable;

class CatalogController extends Controller
{
    public function dbIndex(Request $request, CatalogReadService $service): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'title' => ['nullable', 'string', 'max:255'],
            'author' => ['nullable', 'string', 'max:255'],
            'publisher' => ['nullable', 'string', 'max:255'],
            'isbn' => ['nullable', 'string', 'max:64'],
            'subject' => ['nullable', 'string', 'max:255'],
            'udc' => ['nullable', 'string', 'max:128'],
            'language' => ['nullable', 'string', 'max:10'],
            'material_type' => ['nullable', 'string', 'in:all,digital,archive,physical'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', 'string', 'in:popular,newest,oldest,title,author'],
            'year_from' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'year_to' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'available_only' => ['nullable', 'string', 'in:0,1,true,false'],
            'physical_only' => ['nullable', 'string', 'in:0,1,true,false'],
            // Subject identifiers stay UUID-shaped: the canonical catalogue
            // classifies by UDC and category, which the `udc` parameter serves.
            'subject_id' => ['nullable', 'string', 'uuid'],
            'institution' => ['nullable', 'string', 'in:college_library,economic_library,technology_library,ktslib'],
            // Canonical axes (Master.md 8.2). Multi-value axes take a
            // comma-separated list so several boxes can be ticked at once.
            'resource_type' => ['nullable', 'string', 'max:255'],
            'fund' => ['nullable', 'string', 'max:255'],
            'branch' => ['nullable', 'string', 'max:255'],
            'category' => ['nullable', 'string', 'max:255'],
            'availability' => ['nullable', 'string', 'in:available,issued,electronic_only,processing,repair,no_holdings'],
            'format' => ['nullable', 'string', 'in:print,electronic,hybrid'],
        ]);

        $result = $service->search(
            query: (string) ($validated['q'] ?? ''),
            title: isset($validated['title']) ? (string) $validated['title'] : null,
            author: isset($validated['author']) ? (string) $validated['author'] : null,
            publisher: isset($validated['publisher']) ? (string) $validated['publisher'] : null,
            isbn: isset($validated['isbn']) ? (string) $validated['isbn'] : null,
            subject: isset($validated['subject']) ? (string) $validated['subject'] : null,
            udc: isset($validated['udc']) ? (string) $validated['udc'] : null,
            language: isset($validated['language']) ? (string) $validated['language'] : null,
            page: (int) ($validated['page'] ?? 1),
            limit: (int) ($validated['limit'] ?? Setting::catalogPageSize()),
            sort: (string) ($validated['sort'] ?? 'popular'),
            yearFrom: isset($validated['year_from']) ? (int) $validated['year_from'] : null,
            yearTo: isset($validated['year_to']) ? (int) $validated['year_to'] : null,
            availableOnly: in_array($validated['available_only'] ?? '0', ['1', 'true'], true),
            physicalOnly: in_array($validated['physical_only'] ?? '0', ['1', 'true'], true),
            materialType: isset($validated['material_type']) ? (string) $validated['material_type'] : null,
            subjectId: isset($validated['subject_id']) ? (string) $validated['subject_id'] : null,
            institution: isset($validated['institution']) ? (string) $validated['institution'] : null,
            resourceType: isset($validated['resource_type']) ? (string) $validated['resource_type'] : null,
            fund: isset($validated['fund']) ? (string) $validated['fund'] : null,
            branch: isset($validated['branch']) ? (string) $validated['branch'] : null,
            category: isset($validated['category']) ? (string) $validated['category'] : null,
            availability: isset($validated['availability']) ? (string) $validated['availability'] : null,
            format: isset($validated['format']) ? (string) $validated['format'] : null,
            includeUdcCode: $request->user() !== null,
        );

        return response()->json($result);
    }

    /**
     * Live filter axes for the catalogue sidebar — every value and count is
     * read from the collection, so the UI can never offer a filter that
     * matches nothing that exists.
     */
    public function facets(CatalogReadService $service): JsonResponse
    {
        return response()->json(['data' => $service->facets()]);
    }

    /**
     * Transitional external proxy — reader fallback only.
     * Canonical public catalog usage must use /api/v1/catalog-db and /api/v1/book-db/{isbn}.
     * Remove this method once reader fallback is no longer needed.
     */
    public function proxy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:255'],
            'language' => ['nullable', 'string', 'max:10'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ]);

        try {
            $externalApiUrl = (string) config('services.public_catalog_proxy.url', 'http://localhost:5173/api/v1/catalog');

            $response = Http::get($externalApiUrl, array_filter([
                'q' => $validated['q'] ?? null,
                'language' => $validated['language'] ?? null,
                'limit' => $validated['limit'] ?? 6,
                'page' => $validated['page'] ?? 1,
            ]));

            if ($response->successful()) {
                return response()->json($response->json());
            }

            return response()->json([
                'error' => (string) __('errors.pages.503.title'),
                'success' => false,
            ], 503);
        } catch (Throwable $e) {
            report($e);

            return response()->json([
                // Never return DNS names, internal proxy URLs or transport
                // details from a public fallback endpoint.
                'error' => (string) __('errors.pages.503.title'),
                'success' => false,
            ], 503);
        }
    }
}
