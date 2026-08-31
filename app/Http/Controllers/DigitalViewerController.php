<?php

namespace App\Http\Controllers;

use App\Models\Catalog\ElectronicMaterial;
use App\Services\Library\DigitalAccessService;
use App\Services\Library\ResolvedDigitalMaterial;
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
        $locale = app()->getLocale();
        $catalogUrl = $locale === 'kk' ? '/catalog' : '/catalog?lang='.$locale;
        $material = $this->access->resolve($materialId);

        if ($material === null) {
            return view('digital-viewer', [
                'materialId' => $materialId,
                'state' => 'not_found',
                'material' => null,
                'progress' => null,
                'deniedReason' => null,
                'backUrl' => $catalogUrl,
            ]);
        }

        $backUrl = $this->bookUrl($material, $locale) ?? $catalogUrl;

        if (! $this->access->canAccess($material, $request)) {
            return view('digital-viewer', [
                'materialId' => $materialId,
                'state' => 'denied',
                'material' => null,
                'progress' => null,
                'deniedReason' => $this->access->accessDeniedReason($material, $request),
                'backUrl' => $backUrl,
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
            'backUrl' => $backUrl,
        ]);
    }

    private function bookUrl(ResolvedDigitalMaterial $material, string $locale): ?string
    {
        if (! str_starts_with($material->ref, 'em:')) {
            return null;
        }

        $electronic = ElectronicMaterial::query()
            ->with('bibliographicRecord:id,isbn')
            ->find((int) substr($material->ref, 3));
        $record = $electronic?->bibliographicRecord;
        if ($record === null) {
            return null;
        }

        $identifier = filled($record->isbn) ? (string) $record->isbn : (string) $record->getKey();
        $url = '/book/'.rawurlencode($identifier);

        return $locale === 'kk' ? $url : $url.'?lang='.rawurlencode($locale);
    }
}
