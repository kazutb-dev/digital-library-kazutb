<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Library\CatalogReadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

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
            'udc' => ['nullable', 'string', 'max:128'],
            'language' => ['nullable', 'string', 'max:10'],
            'material_type' => ['nullable', 'string', 'in:all,digital,archive,physical'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
            'sort' => ['nullable', 'string', 'in:popular,newest,title,author'],
            'year_from' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'year_to' => ['nullable', 'integer', 'min:1900', 'max:2100'],
            'available_only' => ['nullable', 'string', 'in:0,1,true,false'],
            'physical_only' => ['nullable', 'string', 'in:0,1,true,false'],
            'subject_id' => ['nullable', 'string', 'uuid'],
            'institution' => ['nullable', 'string', 'in:college_library,economic_library,technology_library,ktslib'],
        ]);

        $result = $service->search(
            query: (string) ($validated['q'] ?? ''),
            title: isset($validated['title']) ? (string) $validated['title'] : null,
            author: isset($validated['author']) ? (string) $validated['author'] : null,
            publisher: isset($validated['publisher']) ? (string) $validated['publisher'] : null,
            isbn: isset($validated['isbn']) ? (string) $validated['isbn'] : null,
            udc: isset($validated['udc']) ? (string) $validated['udc'] : null,
            language: isset($validated['language']) ? (string) $validated['language'] : null,
            page: (int) ($validated['page'] ?? 1),
            limit: (int) ($validated['limit'] ?? 10),
            sort: (string) ($validated['sort'] ?? 'popular'),
            yearFrom: isset($validated['year_from']) ? (int) $validated['year_from'] : null,
            yearTo: isset($validated['year_to']) ? (int) $validated['year_to'] : null,
            availableOnly: in_array($validated['available_only'] ?? '0', ['1', 'true'], true),
            physicalOnly: in_array($validated['physical_only'] ?? '0', ['1', 'true'], true),
            materialType: isset($validated['material_type']) ? (string) $validated['material_type'] : null,
            subjectId: isset($validated['subject_id']) ? (string) $validated['subject_id'] : null,
            institution: isset($validated['institution']) ? (string) $validated['institution'] : null,
        );

        return response()->json($result);
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
                'error' => 'Failed to fetch from external API',
                'status' => $response->status(),
            ], $response->status());
        } catch (\Exception $e) {
            return response()->json([
                'error' => 'Error connecting to external API',
                'message' => $e->getMessage(),
            ], 503);
        }
    }
}
