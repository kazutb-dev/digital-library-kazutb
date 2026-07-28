<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\Library\AccountSummaryReadService;
use App\Services\Library\CirculationLoanReadService;
use App\Services\Library\CirculationLoanWriteService;
use App\Services\Library\ReaderReservationException;
use App\Services\Library\ReaderReservationService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class AccountController extends Controller
{
    public function summary(Request $request, AccountSummaryReadService $service): JsonResponse
    {
        $user = $request->attributes->get('authenticated_reader');

        return response()->json([
            'authenticated' => true,
            ...$service->summary($user),
        ]);
    }

    public function loans(Request $request, CirculationLoanReadService $loanService): JsonResponse
    {
        $user = $request->attributes->get('authenticated_reader');
        $readerId = $this->resolveReaderId($user);

        if ($readerId === null) {
            return response()->json([
                'authenticated' => true,
                'data' => [],
                'message' => 'No linked reader profile found.',
            ]);
        }

        $status = $request->query('status');
        $validStatuses = ['active', 'returned'];
        $status = in_array($status, $validStatuses, true) ? $status : null;

        $loans = $loanService->findLoansByReader($readerId, $status);

        return response()->json([
            'authenticated' => true,
            'data' => $loans,
            'meta' => [
                'readerId' => $readerId,
                'total' => count($loans),
            ],
        ]);
    }

    public function loanSummary(Request $request, CirculationLoanReadService $loanService): JsonResponse
    {
        $user = $request->attributes->get('authenticated_reader');
        $readerId = $this->resolveReaderId($user);

        if ($readerId === null) {
            return response()->json([
                'authenticated' => true,
                'data' => [
                    'activeLoans' => 0,
                    'overdueLoans' => 0,
                    'dueSoonLoans' => 0,
                    'returnedLoans' => 0,
                    'totalLoans' => 0,
                ],
                'message' => 'No linked reader profile found.',
            ]);
        }

        $summary = $loanService->summaryForReader($readerId);

        return response()->json([
            'authenticated' => true,
            'data' => $summary,
            'meta' => ['readerId' => $readerId],
        ]);
    }

    public function renewLoan(string $loanId, Request $request, CirculationLoanWriteService $writeService, CirculationLoanReadService $loanService): JsonResponse
    {
        $user = $request->attributes->get('authenticated_reader');
        $readerId = $this->resolveReaderId($user);

        if ($readerId === null) {
            return response()->json([
                'error' => 'no_reader_profile',
                'message' => 'No linked reader profile found.',
                'success' => false,
            ], 403);
        }

        $loan = $loanService->findLoan($loanId);

        if ($loan === null || ($loan['readerId'] ?? '') !== $readerId) {
            return response()->json([
                'error' => 'loan_not_found',
                'message' => 'Loan not found.',
                'success' => false,
            ], 404);
        }

        try {
            $result = $writeService->renew(
                loanId: $loanId,
                allowOverdue: false,
                context: [
                    'actorUserId' => $readerId,
                    'actorType' => 'reader_self_service',
                ],
            );
        } catch (\App\Services\Library\CirculationWriteException $exception) {
            return response()->json([
                'error' => $exception->errorCode(),
                'message' => $exception->getMessage(),
                'success' => false,
            ], $exception->httpStatus());
        }

        return response()->json([
            'success' => true,
        ] + $result);
    }

    public function reservations(Request $request): JsonResponse
    {
        $user = $request->attributes->get('authenticated_reader');
        $crmUserId = $this->resolveCrmUserId($user);

        if ($crmUserId === null) {
            return response()->json([
                'authenticated' => true,
                'data' => [],
                'message' => 'No linked CRM user found.',
            ]);
        }

        $status = $request->query('status');
        $allowedStatuses = ['PENDING', 'READY', 'FULFILLED', 'CANCELLED', 'EXPIRED'];
        $status = in_array($status, $allowedStatuses, true) ? $status : null;

        $query = DB::connection('pgsql')
            ->table('public.Reservation as r')
            ->leftJoin('public.Book as b', 'b.id', '=', 'r.bookId')
            ->where('r.userId', $crmUserId)
            ->select([
                'r.id',
                'r.status',
                'r.reservedAt',
                'r.expiresAt',
                'r.processedAt',
                'r.notes',
                'r.copyId',
                'r.createdAt',
                'b.title as bookTitle',
                'b.isbn as bookIsbn',
                'b.publishYear as bookPublishYear',
            ])
            ->orderByDesc('r.reservedAt');

        if ($status !== null) {
            $query->where('r.status', $status);
        }

        $reservations = $query->limit(100)->get()->map(function (object $row): array {
            $notes = null;
            if (! empty($row->notes)) {
                $decoded = json_decode($row->notes, true);
                $notes = is_array($decoded) ? $decoded : null;
            }

            return [
                'id' => $row->id,
                'status' => $row->status,
                'reservedAt' => $row->reservedAt,
                'expiresAt' => $row->expiresAt,
                'processedAt' => $row->processedAt,
                'copyId' => $row->copyId,
                'cancelOrigin' => $notes['cancel_origin'] ?? null,
                'cancelReasonCode' => $notes['cancel_reason_code'] ?? null,
                'book' => [
                    'title' => $row->bookTitle,
                    'isbn' => $row->bookIsbn,
                    'publishYear' => $row->bookPublishYear,
                ],
            ];
        })->all();

        return response()->json([
            'authenticated' => true,
            'data' => $reservations,
            'meta' => [
                'crmUserId' => $crmUserId,
                'total' => count($reservations),
            ],
        ]);
    }

    public function createReservation(Request $request, ReaderReservationService $service): JsonResponse
    {
        $user = $request->attributes->get('authenticated_reader');
        $crmUserId = $this->resolveCrmUserId($user);

        if ($crmUserId === null) {
            return response()->json([
                'error' => 'no_crm_user',
                'message' => 'Не удалось определить ваш профиль в системе.',
                'success' => false,
            ], 403);
        }

        $request->validate([
            'bookId' => 'nullable|string|uuid',
            'isbn' => 'nullable|string|max:20',
        ]);

        $bookId = $request->input('bookId');

        if (! $bookId && $request->filled('isbn')) {
            $bookId = $service->resolveBookIdByIsbn($request->input('isbn'));
        }

        if (! $bookId) {
            return response()->json([
                'error' => 'book_not_found',
                'message' => 'Книга не найдена. Укажите bookId или isbn.',
                'success' => false,
            ], 422);
        }

        try {
            $result = $service->create($crmUserId, $bookId);
        } catch (ReaderReservationException $e) {
            return response()->json([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
                'success' => false,
            ], $e->httpStatus());
        }

        return response()->json([
            'success' => true,
            ...$result,
        ], 201);
    }

    public function cancelReservation(string $reservationId, Request $request, ReaderReservationService $service): JsonResponse
    {
        $user = $request->attributes->get('authenticated_reader');
        $crmUserId = $this->resolveCrmUserId($user);

        if ($crmUserId === null) {
            return response()->json([
                'error' => 'no_crm_user',
                'message' => 'Не удалось определить ваш профиль в системе.',
                'success' => false,
            ], 403);
        }

        try {
            $result = $service->cancel($reservationId, $crmUserId);
        } catch (ReaderReservationException $e) {
            return response()->json([
                'error' => $e->errorCode(),
                'message' => $e->getMessage(),
                'success' => false,
            ], $e->httpStatus());
        }

        return response()->json([
            'success' => true,
            ...$result,
        ]);
    }

    public function checkReservation(Request $request, ReaderReservationService $service): JsonResponse
    {
        $user = $request->attributes->get('authenticated_reader');
        $crmUserId = $this->resolveCrmUserId($user);

        if ($crmUserId === null) {
            return response()->json([
                'hasActive' => false,
                'reservation' => null,
            ]);
        }

        $request->validate([
            'bookId' => 'nullable|string|uuid',
            'isbn' => 'nullable|string|max:20',
        ]);

        $bookId = $request->query('bookId');

        if (! $bookId && $request->filled('isbn')) {
            $bookId = $service->resolveBookIdByIsbn($request->query('isbn'));
        }

        if (! $bookId) {
            return response()->json([
                'hasActive' => false,
                'reservation' => null,
            ]);
        }

        $active = $service->checkForBook($crmUserId, $bookId);

        return response()->json([
            'hasActive' => $active !== null,
            'reservation' => $active,
        ]);
    }

    private function resolveCrmUserId(array $sessionUser): ?string
    {
        $sessionId = trim((string) ($sessionUser['id'] ?? ''));

        // The session user's `id` is the authoritative CRM user identifier — it is
        // populated directly from the CRM authentication response during login and
        // stored in the signed, server-side session.  A secondary DB lookup against
        // public.User to "confirm" the ID is redundant, fragile (hard-codes a
        // schema/connection detail), and creates an unnecessary runtime dependency.
        // This approach is already used by the web route resolver and is consistent
        // with how the rest of the application trusts session-resident identity.
        if ($sessionId !== '' && \Illuminate\Support\Str::isUuid($sessionId)) {
            return $sessionId;
        }

        return null;
    }

    private function resolveReaderId(array $sessionUser): ?string
    {
        $email = mb_strtolower(trim((string) ($sessionUser['email'] ?? '')));
        $adLogin = mb_strtolower(trim((string) ($sessionUser['ad_login'] ?? '')));

        $identifiers = array_values(array_filter(array_unique([$email, $adLogin]), fn (string $v) => $v !== ''));

        if ($identifiers === []) {
            return null;
        }

        $readerId = DB::table('app.readers as r')
            ->join('app.reader_contacts as rc', 'rc.reader_id', '=', 'r.id')
            ->where('rc.contact_type', 'EMAIL')
            ->where(function ($query) use ($identifiers): void {
                foreach ($identifiers as $value) {
                    $query->orWhere('rc.value_normalized_key', $value);
                }
            })
            ->orderByDesc('r.registration_at')
            ->value('r.id');

        return is_string($readerId) ? $readerId : null;
    }
}
