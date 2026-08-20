<?php

namespace App\Http\Controllers;

use App\Models\ExternalResource;
use App\Services\ExternalResources\ExternalResourceAnalytics;
use App\Services\ExternalResourceService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PublicExternalResourceController extends Controller
{
    public function __invoke(
        Request $request,
        string $slug,
        ExternalResourceService $resources,
        ExternalResourceAnalytics $analytics,
    ): View {
        $resource = $resources->findPublicBySlug($slug);
        abort_if($resource === null, 404);

        if (! empty($resource['id'])) {
            try {
                $model = ExternalResource::query()->find($resource['id']);
                if ($model?->publiclyDiscoverable()) {
                    $analytics->recordCardView($request, $model);
                }
            } catch (\Throwable $exception) {
                // A metrics outage must not make a public information page fail.
                report($exception);
            }
        }

        return view('resource-show', [
            'activePage' => 'resources',
            'resource' => $resource,
            'categories' => $resources->categories(),
            'accessTypes' => $resources->accessTypes(),
        ]);
    }
}
