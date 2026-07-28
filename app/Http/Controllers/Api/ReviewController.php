<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Library\ReviewIssuesReadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class ReviewController extends Controller
{
    public function issues(Request $request, ReviewIssuesReadService $service): JsonResponse
    {
        $validated = $request->validate([
            'severity' => ['nullable', 'string', 'max:32'],
            'status' => ['nullable', 'string', 'max:32'],
            'issue_code' => ['nullable', 'string', 'max:100'],
            'page' => ['nullable', 'integer', 'min:1'],
            'limit' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        return response()->json($service->list(
            severity: isset($validated['severity']) ? (string) $validated['severity'] : null,
            status: isset($validated['status']) ? (string) $validated['status'] : null,
            issueCode: isset($validated['issue_code']) ? (string) $validated['issue_code'] : null,
            page: (int) ($validated['page'] ?? 1),
            limit: (int) ($validated['limit'] ?? 20),
        ));
    }

    public function issuesSummary(Request $request, ReviewIssuesReadService $service): JsonResponse
    {
        $validated = $request->validate([
            'top_limit' => ['nullable', 'integer', 'min:1', 'max:20'],
        ]);

        return response()->json($service->summary(
            topIssueCodesLimit: (int) ($validated['top_limit'] ?? 5),
        ));
    }
}
