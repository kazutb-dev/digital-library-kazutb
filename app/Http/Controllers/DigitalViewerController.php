<?php

namespace App\Http\Controllers;

use App\Services\Library\DigitalAccessService;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The reading room: the page that renders library-held material inside the
 * controlled viewer.
 *
 * State is resolved here rather than in the browser. The previous viewer probed
 * the stream endpoint with a HEAD request and then decided what to draw, which
 * meant a denied reader still got a page shell and a round trip before being
 * told no — and the page could not be rendered at all without JavaScript.
 */
class DigitalViewerController extends Controller
{
    public function __construct(
        private readonly DigitalAccessService $access
    ) {}

    public function show(Request $request, string $materialId): View
    {
        $material = $this->access->resolve($materialId);

        if ($material === null) {
            return view('digital-viewer', [
                'materialId' => $materialId,
                'state' => 'not_found',
                'material' => null,
                'progress' => null,
                'deniedReason' => null,
            ]);
        }

        if (! $this->access->canAccess($material, $request)) {
            return view('digital-viewer', [
                'materialId' => $materialId,
                'state' => 'denied',
                'material' => null,
                'progress' => null,
                'deniedReason' => $this->access->accessDeniedReason($material, $request),
            ]);
        }

        $state = match (true) {
            ! $material->hasLocalFile() => 'external',
            ! $this->access->fileExists($material) => 'not_found',
            ! $material->isReadableInViewer() => 'unsupported',
            default => 'ready',
        };

        return view('digital-viewer', [
            'materialId' => $materialId,
            'state' => $state,
            'material' => [
                'id' => $material->id,
                'title' => $material->title,
                'fileType' => $material->fileType,
                'fileSize' => $material->humanFileSize(),
                'licenseTerms' => $material->licenseTerms,
                'externalUrl' => $material->externalUrl,
                'streamUrl' => "/api/v1/digital-materials/{$material->id}/stream",
                'progressUrl' => "/api/v1/digital-materials/{$material->id}/progress",
                'downloadUrl' => $this->access->canDownload($material, $request)
                    ? "/api/v1/digital-materials/{$material->id}/download"
                    : null,
            ],
            'progress' => $this->access->readingProgress($material, $request),
            'deniedReason' => null,
        ]);
    }
}
