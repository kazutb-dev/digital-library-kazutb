<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Library\DigitalMaterialService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DigitalMaterialController extends Controller
{
    public function __construct(
        private readonly DigitalMaterialService $service
    ) {}

    /**
     * List active digital materials for a document.
     * GET /api/v1/documents/{documentId}/digital-materials
     */
    public function forDocument(Request $request, string $documentId): JsonResponse
    {
        $materials = $this->service->forDocument($documentId);
        $user = session('library.user');

        $items = $materials->map(function ($m) use ($user) {
            $canAccess = $this->service->canAccess($m, $user);

            return [
                'id' => $m->id,
                'title' => $m->title,
                'fileType' => $m->file_type,
                'fileSize' => $m->humanFileSize(),
                'fileSizeBytes' => $m->file_size_bytes,
                'accessLevel' => $m->access_level,
                'allowDownload' => $m->allow_download,
                'canAccess' => $canAccess,
                'accessDeniedReason' => $canAccess ? null : $this->service->accessDeniedReason($m, $user),
                'viewerUrl' => $canAccess ? "/digital-viewer/{$m->id}" : null,
            ];
        });

        return response()->json([
            'data' => $items->values(),
            'meta' => [
                'total' => $items->count(),
                'documentId' => $documentId,
            ],
            'success' => true,
        ]);
    }

    /**
     * Stream file content for the embedded viewer.
     * GET /api/v1/digital-materials/{id}/stream
     */
    public function stream(Request $request, string $id): StreamedResponse|JsonResponse
    {
        $material = $this->service->findActive($id);

        if (! $material) {
            return response()->json([
                'error' => 'Материал не найден.',
                'success' => false,
            ], 404);
        }

        $user = session('library.user');

        if (! $this->service->canAccess($material, $user)) {
            return response()->json([
                'error' => $this->service->accessDeniedReason($material, $user),
                'success' => false,
            ], 403);
        }

        return $this->service->streamForViewing($material);
    }
}
