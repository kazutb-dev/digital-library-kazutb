<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\ExternalResourceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ExternalResourceController extends Controller
{
    public function __construct(
        private readonly ExternalResourceService $service
    ) {}

    /**
     * List external licensed resources with optional filters.
     *
     * GET /api/v1/external-resources
     *   ?category=electronic_library|research_database|open_access|analytics
     *   &access_type=campus|remote_auth|open
     *   &status=active|expiring_soon|inactive
     */
    public function index(Request $request): JsonResponse
    {
        $filters = $request->only(['category', 'access_type', 'status']);

        $resources = $this->service->list($filters);

        return response()->json([
            'data' => $resources,
            'meta' => [
                'total' => $resources->count(),
                'categories' => $this->service->categories(),
                'access_types' => $this->service->accessTypes(),
            ],
        ]);
    }

    /**
     * Get a single external resource by slug.
     *
     * GET /api/v1/external-resources/{slug}
     */
    public function show(string $slug): JsonResponse
    {
        $resource = $this->service->findBySlug($slug);

        if (! $resource) {
            return response()->json([
                'message' => 'Ресурс не найден.',
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
