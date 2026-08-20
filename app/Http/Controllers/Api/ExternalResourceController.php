<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExternalResourceService;
use App\Support\LocaleResolver;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalResourceController extends Controller
{
    public function __construct(
        private readonly ExternalResourceService $service,
        private readonly LocaleResolver $localeResolver,
    ) {}

    /**
     * List external licensed resources with optional filters.
     *
     * GET /api/v1/external-resources
     *   ?category=electronic_library|research_database|open_access|analytics
     *   &access_type=campus|remote_auth|open
     *   &status=active|expiring_soon|expired
     *   &audience=guest|student|teacher|library_staff
     *   &content_type=scientific_articles
     *   &access_scope=guest|authenticated|campus|remote
     */
    public function index(Request $request): JsonResponse
    {
        app()->setLocale($this->localeResolver->resolve($request));
        $filters = $request->only([
            'category', 'resource_type', 'access_type', 'status',
            'audience', 'content_type', 'access_scope',
        ]);

        $resources = $this->service->list($filters);

        return response()->json([
            'data' => $resources,
            'meta' => [
                'total' => $resources->count(),
                'categories' => $this->service->categories(),
                'resource_types' => $this->service->resourceTypes(),
                'access_types' => $this->service->accessTypes(),
                'audiences' => $this->service->audiences(),
                'content_types' => $this->service->contentTypes(),
                'access_scopes' => [
                    'guest' => __('external_resources.filters.access_scopes.guest'),
                    'authenticated' => __('external_resources.filters.access_scopes.authenticated'),
                    'campus' => __('external_resources.filters.access_scopes.campus'),
                    'remote' => __('external_resources.filters.access_scopes.remote'),
                ],
            ],
        ]);
    }

    /**
     * Get a single external resource by slug.
     *
     * GET /api/v1/external-resources/{slug}
     */
    public function show(Request $request, string $slug): JsonResponse
    {
        app()->setLocale($this->localeResolver->resolve($request));
        $resource = $this->service->findBySlug($slug);

        if (! $resource) {
            return response()->json([
                'message' => __('external_resources.api.not_found'),
            ], 404);
        }

        $categories = $this->service->categories();
        $accessTypes = $this->service->accessTypes();

        return response()->json([
            'data' => array_merge($resource, [
                'category_label' => $categories[$resource['category']]['label'] ?? $resource['category'],
                'access_type_label' => $accessTypes[$resource['access_type']]['label'] ?? $resource['access_type'],
            ]),
        ]);
    }
}
