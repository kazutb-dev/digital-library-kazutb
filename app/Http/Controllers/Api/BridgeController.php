<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Library\BridgeBooksDocumentsDiagnosticsReadService;
use App\Services\Library\BridgeCopiesDiagnosticsReadService;
use App\Services\Library\BridgeUsersDiagnosticsReadService;
use App\Services\Library\BridgeSummaryReadService;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;

class BridgeController extends Controller
{
    public function summary(BridgeSummaryReadService $service): JsonResponse
    {
        return response()->json($service->summary());
    }

    public function users(Request $request, BridgeUsersDiagnosticsReadService $service): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($service->list(
            page: (int) ($validated['page'] ?? 1),
            limit: (int) ($validated['limit'] ?? 20),
        ));
    }

    public function copies(Request $request, BridgeCopiesDiagnosticsReadService $service): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($service->list(
            page: (int) ($validated['page'] ?? 1),
            limit: (int) ($validated['limit'] ?? 20),
        ));
    }

    public function books(Request $request, BridgeBooksDocumentsDiagnosticsReadService $service): JsonResponse
    {
        $validated = $request->validate([
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($service->list(
            page: (int) ($validated['page'] ?? 1),
            limit: (int) ($validated['limit'] ?? 20),
        ));
    }
}
