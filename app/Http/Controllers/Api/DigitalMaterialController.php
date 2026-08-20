<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Library\DigitalAccessService;
use App\Services\Library\ResolvedDigitalMaterial;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Reader-facing API for digital materials. All access decisions live in
 * DigitalAccessService; this controller only turns them into HTTP.
 */
class DigitalMaterialController extends Controller
{
    public function __construct(
        private readonly DigitalAccessService $access
    ) {}

    /**
     * List active digital materials for a document.
     * GET /api/v1/documents/{documentId}/digital-materials
     */
    public function forDocument(Request $request, string $documentId): JsonResponse
    {
        $items = array_map(
            fn (ResolvedDigitalMaterial $material) => $this->access->toReaderPayload($material, $request),
            $this->access->forDocument($documentId),
        );

        return response()->json([
            'data' => $items,
            'meta' => [
                'total' => count($items),
                'documentId' => $documentId,
            ],
            'success' => true,
        ]);
    }

    /**
     * Serve file bytes to the controlled viewer.
     * GET /api/v1/digital-materials/{id}/stream
     */
    public function stream(Request $request, string $id): Response
    {
        $material = $this->access->resolve($id);

        if ($material === null) {
            return $this->notFound();
        }

        if (! $this->access->canAccess($material, $request)) {
            $this->access->recordAccess($material, $request, 'stream', false, 'access_policy');

            return response()->json([
                'error' => $this->access->accessDeniedReason($material, $request),
                'success' => false,
            ], 403);
        }

        if (! $this->access->fileExists($material)) {
            return $this->notFound();
        }

        $this->access->recordAccess($material, $request, 'stream', true);

        return $this->fileResponse($material, asAttachment: false);
    }

    /**
     * Hand the reader a copy of the file — only where the licence allows it.
     * GET /api/v1/digital-materials/{id}/download
     */
    public function download(Request $request, string $id): Response
    {
        $material = $this->access->resolve($id);

        if ($material === null) {
            return $this->notFound();
        }

        // Distinguish "you may not read this" from "you may read it but not keep
        // a copy" — otherwise a reader with legitimate access sees a bare 403
        // and reports it as a broken button.
        if (! $this->access->canAccess($material, $request)) {
            $this->access->recordAccess($material, $request, 'download', false, 'access_policy');

            return response()->json([
                'error' => $this->access->accessDeniedReason($material, $request),
                'success' => false,
            ], 403);
        }

        if (! $this->access->canDownload($material, $request)) {
            $this->access->recordAccess($material, $request, 'download', false, 'download_disabled');

            return response()->json([
                'error' => (string) __('ui.digital.denied_download'),
                'success' => false,
            ], 403);
        }

        if (! $this->access->fileExists($material)) {
            return $this->notFound();
        }

        $this->access->recordAccess($material, $request, 'download', true);

        return $this->fileResponse($material, asAttachment: true);
    }

    /**
     * The reader's saved position in this material.
     * GET /api/v1/digital-materials/{id}/progress
     */
    public function progress(Request $request, string $id): JsonResponse
    {
        $material = $this->access->resolve($id);

        if ($material === null) {
            return $this->notFound();
        }

        if (! $this->access->canAccess($material, $request)) {
            return response()->json([
                'error' => $this->access->accessDeniedReason($material, $request),
                'success' => false,
            ], 403);
        }

        return response()->json([
            'data' => $this->access->readingProgress($material, $request),
            'success' => true,
        ]);
    }

    /**
     * Record the page the reader is on, so the viewer can reopen there.
     * PUT /api/v1/digital-materials/{id}/progress
     */
    public function saveProgress(Request $request, string $id): JsonResponse
    {
        $material = $this->access->resolve($id);

        if ($material === null) {
            return $this->notFound();
        }

        if (! $this->access->canAccess($material, $request)) {
            return response()->json([
                'error' => $this->access->accessDeniedReason($material, $request),
                'success' => false,
            ], 403);
        }

        $validated = $request->validate([
            'page' => ['required', 'integer', 'min:1', 'max:100000'],
            'totalPages' => ['nullable', 'integer', 'min:1', 'max:100000'],
            'zoom' => ['nullable', 'string', 'max:16'],
        ]);

        $stored = $this->access->saveReadingProgress(
            $material,
            $request,
            (int) $validated['page'],
            isset($validated['totalPages']) ? (int) $validated['totalPages'] : null,
            $validated['zoom'] ?? null,
        );

        // A guest reading public material has no identity to store progress
        // against. That is not an error — report it so the viewer can stop
        // sending updates instead of retrying on every page turn.
        return response()->json([
            'data' => ['stored' => $stored],
            'success' => true,
        ]);
    }

    /**
     * Local files go out as a BinaryFileResponse so the reader gets HTTP Range
     * support; pdf.js pulls a large scan in chunks and would otherwise refetch
     * the whole file on each page. Non-local disks have no filesystem path, so
     * they fall back to a plain stream.
     */
    private function fileResponse(ResolvedDigitalMaterial $material, bool $asAttachment): Response
    {
        $disposition = $asAttachment
            ? ResponseHeaderBag::DISPOSITION_ATTACHMENT
            : ResponseHeaderBag::DISPOSITION_INLINE;

        $headers = [
            'Content-Type' => $this->access->mimeTypeFor($material),
            'Cache-Control' => 'private, no-store',
            'X-Content-Type-Options' => 'nosniff',
        ];

        $path = $this->access->localFilePath($material);

        if ($path !== null) {
            // BinaryFileResponse marks itself public in its constructor, which
            // strips the `private` directive we set above. Re-assert it: licensed
            // material must not be held by a shared cache or proxy.
            return response()
                ->file($path, $headers)
                ->setPrivate()
                ->setContentDisposition($disposition, $this->safeFilename($material));
        }

        $disk = Storage::disk($material->storageDisk ?? 'local');

        return new StreamedResponse(
            function () use ($disk, $material): void {
                $stream = $disk->readStream($material->storagePath);

                if ($stream === false || $stream === null) {
                    return;
                }

                fpassthru($stream);

                if (is_resource($stream)) {
                    fclose($stream);
                }
            },
            200,
            $headers + [
                'Content-Length' => (string) $disk->size($material->storagePath),
                'Content-Disposition' => sprintf(
                    '%s; filename="%s"',
                    $asAttachment ? 'attachment' : 'inline',
                    $this->safeFilename($material),
                ),
            ],
        );
    }

    /**
     * Titles come from cataloguing and may hold quotes, slashes or newlines that
     * would break the Content-Disposition header, so build the name from safe
     * characters only and keep the extension the file type implies.
     */
    private function safeFilename(ResolvedDigitalMaterial $material): string
    {
        $name = pathinfo($material->originalFilename, PATHINFO_FILENAME);
        $name = preg_replace('/[^\p{L}\p{N}\-_. ]+/u', '', (string) $name) ?? '';
        $name = trim(preg_replace('/\s+/u', ' ', $name) ?? '');

        if ($name === '') {
            $name = 'material-'.$material->id;
        }

        $extension = pathinfo($material->originalFilename, PATHINFO_EXTENSION);

        if ($extension === '') {
            $extension = $material->fileType === 'pdf' ? 'pdf' : '';
        }

        return $extension === '' ? $name : $name.'.'.$extension;
    }

    private function notFound(): JsonResponse
    {
        return response()->json([
            'error' => (string) __('ui.digital.not_found_title'),
            'success' => false,
        ], 404);
    }
}
