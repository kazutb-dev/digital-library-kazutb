<?php

namespace App\Http\Controllers\Api\Integration;

use App\Http\Controllers\Controller;
use App\Services\Library\IntegrationReservationReadService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

/**
 * Read-only external controller for the CRM-facing reservation API.
 *
 * All routes in this namespace are already protected by the EnsureIntegrationBoundary
 * middleware, which guarantees:
 *   - Valid Bearer token present
 *   - All required headers present and X-Source-System = crm
 *   - integration.* attributes set on the request
 *   - X-Request-Id / X-Correlation-Id echoed on the response
 *
 * This controller ONLY handles read operations.
 * Mutate operations (approve / reject) are intentionally out of scope.
 */
class ReservationReadController extends Controller
{
    public function __construct(
        private readonly IntegrationReservationReadService $service,
    ) {}

    /**
     * GET /api/integration/v1/reservations
     *
     * Supported query parameters:
     *   - status          string  One of: PENDING, READY, FULFILLED, CANCELLED, EXPIRED
     *   - user_id         string  UUID — patron user ID
     *   - reserved_after  string  ISO-8601 / date string — lower bound on reservedAt
     *   - reserved_before string  ISO-8601 / date string — upper bound on reservedAt
     *   - page            int     Default 1
     *   - per_page        int     Default 20, max 100
     */
    public function index(Request $request): JsonResponse
    {
        // ---- filter: status ----
        $statusInput = $request->query('status');
        if ($statusInput !== null) {
            $statusInput = strtoupper((string) $statusInput);
            if (! in_array($statusInput, IntegrationReservationReadService::allowedStatuses(), true)) {
                return $this->errorResponse(
                    $request, 400, 'invalid_request', 'invalid_filter_value',
                    'Filter "status" must be one of: '.implode(', ', IntegrationReservationReadService::allowedStatuses()).'.'
                );
            }
        }

        // ---- filter: user_id ----
        $userIdInput = $request->query('user_id');
        if ($userIdInput !== null && ! Str::isUuid((string) $userIdInput)) {
            return $this->errorResponse(
                $request, 400, 'invalid_request', 'invalid_filter_value',
                'Filter "user_id" must be a valid UUID.'
            );
        }

        // ---- pagination ----
        $pageRaw = $request->query('page', '1');
        $perPageRaw = $request->query('per_page', '20');

        $page = filter_var($pageRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $perPage = filter_var($perPageRaw, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 100]]);

        if ($page === false || $perPage === false) {
            return $this->errorResponse(
                $request, 400, 'invalid_request', 'invalid_filter_value',
                'Query parameters "page" and "per_page" must be positive integers. "per_page" max is 100.'
            );
        }

        // ---- build filter map ----
        /** @var array<string, string> $filters */
        $filters = array_filter([
            'status' => $statusInput,
            'user_id' => $userIdInput !== null ? (string) $userIdInput : null,
            'reserved_after' => $request->query('reserved_after'),
            'reserved_before' => $request->query('reserved_before'),
        ], fn ($v): bool => $v !== null);

        $orgContext = (string) $request->header('X-Operator-Org-Context', '');

        $result = $this->service->listReservations($filters, $orgContext, (int) $page, (int) $perPage);

        return response()->json([
            'data' => $result['data'],
            'meta' => $result['meta'],
            'request_id' => $request->attributes->get('integration.request_id'),
            'correlation_id' => $request->attributes->get('integration.correlation_id'),
            'timestamp' => now()->toISOString(),
        ]);
    }

    /**
     * GET /api/integration/v1/reservations/{id}
     *
     * Returns a single reservation by its canonical UUID.
     * 404 if not found or outside the operator's org context.
     */
    public function show(Request $request, string $id): JsonResponse
    {
        $orgContext = (string) $request->header('X-Operator-Org-Context', '');

        $reservation = $this->service->findById($id, $orgContext);

        if ($reservation === null) {
            return $this->errorResponse(
                $request, 404, 'not_found', 'reservation_not_found',
                'Reservation not found or not accessible in your org context.'
            );
        }

        return response()->json([
            'data' => $reservation,
            'request_id' => $request->attributes->get('integration.request_id'),
            'correlation_id' => $request->attributes->get('integration.correlation_id'),
            'timestamp' => now()->toISOString(),
        ]);
    }

    // -------------------------------------------------------------------------
    // Internal helper — mirrors the boundary middleware error envelope shape
    // -------------------------------------------------------------------------

    private function errorResponse(
        Request $request,
        int $status,
        string $errorCode,
        string $reasonCode,
        string $message,
    ): JsonResponse {
        return response()->json([
            'error' => [
                'error_code' => $errorCode,
                'reason_code' => $reasonCode,
                'message' => $message,
            ],
            'request_id' => $request->attributes->get('integration.request_id'),
            'correlation_id' => $request->attributes->get('integration.correlation_id'),
            'timestamp' => now()->toISOString(),
        ], $status);
    }
}
