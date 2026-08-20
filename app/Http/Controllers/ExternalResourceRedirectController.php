<?php

namespace App\Http\Controllers;

use App\Models\ExternalResource;
use App\Services\ExternalResources\ExternalResourceAnalytics;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\IpUtils;

class ExternalResourceRedirectController extends Controller
{
    public function __invoke(
        Request $request,
        ExternalResource $externalResource,
        ExternalResourceAnalytics $analytics,
    ): RedirectResponse {
        abort_unless($externalResource->publiclyDiscoverable(), 404);

        $onCampus = ($ip = $request->ip()) !== null
            && IpUtils::checkIp($ip, (array) config('digital_access.campus_ranges', []));
        $safeDestination = ExternalResource::isSafeDestination(
            (string) $externalResource->url,
            (string) $externalResource->resource_type,
        );
        $allowed = $safeDestination && $externalResource->canOpen($request->user(), $onCampus);

        $eventType = match (true) {
            ! $safeDestination => 'unsafe_destination',
            $allowed => 'outbound_click',
            $externalResource->licenceExpired() => 'expired_click',
            default => 'access_denied',
        };

        try {
            $analytics->recordAccess($request, $externalResource, $eventType, [
                'on_campus' => $onCampus,
                'resource_type' => $externalResource->resource_type,
                'access_method' => $externalResource->access_method,
                'status' => $externalResource->accessStatus(),
            ]);
        } catch (\Throwable $exception) {
            // Metrics availability must not control reader access.
            report($exception);
        }

        abort_unless($allowed, 403);

        return $externalResource->resource_type === 'internal'
            ? redirect()->to($externalResource->url)
            : redirect()->away($externalResource->url);
    }
}
