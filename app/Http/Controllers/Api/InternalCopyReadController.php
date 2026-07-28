<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Library\Document;
use App\Services\Library\InternalCopyReadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class InternalCopyReadController extends Controller
{
    public function show(string $copyId, InternalCopyReadService $service): JsonResponse
    {
        if (! Str::isUuid($copyId)) {
            return response()->json([
                'error' => 'invalid_copy_id',
                'message' => 'Copy id must be a valid UUID.',
                'success' => false,
            ], 400);
        }

        $copy = $service->findCopyDetail($copyId);

        if ($copy === null) {
            return response()->json([
                'error' => 'copy_not_found',
                'message' => 'Copy not found.',
                'success' => false,
            ], 404);
        }

        return response()->json([
            'data' => $copy,
            'success' => true,
        ]);
    }

    public function listByDocument(string $documentId, InternalCopyReadService $service): JsonResponse
    {
        if (! Str::isUuid($documentId)) {
            return response()->json([
                'error' => 'invalid_document_id',
                'message' => 'Document id must be a valid UUID.',
                'success' => false,
            ], 400);
        }

        if (! Document::query()->whereKey($documentId)->exists()) {
            return response()->json([
                'error' => 'document_not_found',
                'message' => 'Document not found.',
                'success' => false,
            ], 404);
        }

        $copies = $service->listCopiesByDocument($documentId);

        return response()->json([
            'data' => $copies,
            'meta' => [
                'documentId' => $documentId,
                'total' => count($copies),
            ],
            'success' => true,
        ]);
    }
}