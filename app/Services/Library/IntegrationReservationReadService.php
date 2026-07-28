<?php

namespace App\Services\Library;

use Illuminate\Database\Query\Builder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Read-only service for the CRM-facing external reservation API.
 *
 * Reads from public."Reservation" (legacy/CRM schema, source of truth for patron
 * reservation requests). Joins public."User" and public."Book" for minimal snapshots.
 *
 * Org-scope enforcement: if X-Operator-Org-Context contains a valid UUID branch_id,
 * results are scoped to that branch. Non-UUID branch_id values (e.g. test sentinels)
 * result in no branch filter.
 */
class IntegrationReservationReadService
{
    /** @var list<string> */
    private const ALLOWED_STATUSES = ['PENDING', 'READY', 'FULFILLED', 'CANCELLED', 'EXPIRED'];

    private const DEFAULT_PER_PAGE = 20;

    private const MAX_PER_PAGE = 100;

    /**
     * List reservations with optional filtering and pagination.
     *
     * @param  array<string, string>  $filters  Keys: status, user_id, reserved_after, reserved_before
     * @return array{data: list<array<string, mixed>>, meta: array{page: int, per_page: int, total: int, pages: int}}
     */
    public function listReservations(array $filters, string $orgContext, int $page, int $perPage): array
    {
        $perPage = min(max(1, $perPage), self::MAX_PER_PAGE);
        $page = max(1, $page);
        $offset = ($page - 1) * $perPage;

        $query = $this->baseQuery();
        $this->applyOrgScope($query, $orgContext);
        $this->applyFilters($query, $filters);

        $total = (int) (clone $query)->count();

        $rows = (clone $query)
            ->orderByDesc('r.reservedAt')
            ->offset($offset)
            ->limit($perPage)
            ->get();

        return [
            'data' => $rows->map(fn (object $row): array => $this->toShape($row))->all(),
            'meta' => [
                'page' => $page,
                'per_page' => $perPage,
                'total' => $total,
                'pages' => $total === 0 ? 1 : (int) ceil($total / $perPage),
            ],
        ];
    }

    /**
     * Find a single reservation by its canonical UUID, scoped to the operator's org context.
     *
     * @return array<string, mixed>|null  null when not found or outside org scope
     */
    public function findById(string $id, string $orgContext): ?array
    {
        if (! Str::isUuid($id)) {
            return null;
        }

        $query = $this->baseQuery()->where('r.id', $id);
        $this->applyOrgScope($query, $orgContext);

        $row = $query->first();

        return $row ? $this->toShape($row) : null;
    }

    /**
     * @return list<string>
     */
    public static function allowedStatuses(): array
    {
        return self::ALLOWED_STATUSES;
    }

    // -------------------------------------------------------------------------
    // Internal helpers
    // -------------------------------------------------------------------------

    private function baseQuery(): Builder
    {
        // All three tables live in the public schema (CRM/legacy side).
        // Column names are camelCase — Laravel's PostgreSQL grammar quotes identifiers
        // from dotted notation correctly, e.g. r.reservedAt → "r"."reservedAt".
        return DB::connection('pgsql')
            ->table(DB::raw('"Reservation" as r'))
            ->leftJoin(DB::raw('"User" as u'), 'u.id', '=', 'r.userId')
            ->leftJoin(DB::raw('"Book" as b'), 'b.id', '=', 'r.bookId')
            ->select([
                'r.id',
                'r.status',
                'r.reservedAt',
                'r.expiresAt',
                'r.processedAt',
                'r.libraryBranchId',
                'r.copyId',
                'r.notes',
                'r.userId',
                'r.bookId',
                DB::raw('"u"."fullName" AS "userFullName"'),
                DB::raw('"u"."universityId" AS "userUniversityId"'),
                'b.isbn',
                'b.title',
            ]);
    }

    private function applyOrgScope(Builder $query, string $orgContext): void
    {
        $branchId = $this->extractBranchId($orgContext);
        if ($branchId !== null) {
            $query->where('r.libraryBranchId', $branchId);
        }
    }

    /**
     * @param  array<string, string>  $filters
     */
    private function applyFilters(Builder $query, array $filters): void
    {
        if (isset($filters['status'])) {
            $query->where('r.status', $filters['status']);
        }

        if (isset($filters['user_id'])) {
            $query->where('r.userId', $filters['user_id']);
        }

        if (isset($filters['reserved_after'])) {
            $query->where('r.reservedAt', '>=', $filters['reserved_after']);
        }

        if (isset($filters['reserved_before'])) {
            $query->where('r.reservedAt', '<=', $filters['reserved_before']);
        }
    }

    /** Extract a UUID branch_id from the JSON org-context header value, or null. */
    private function extractBranchId(string $orgContext): ?string
    {
        if ($orgContext === '') {
            return null;
        }

        $decoded = json_decode($orgContext, true);
        if (! is_array($decoded)) {
            return null;
        }

        $branchId = (string) ($decoded['branch_id'] ?? '');

        return Str::isUuid($branchId) ? $branchId : null;
    }

    /**
     * Map a raw DB result row to the canonical external response shape.
     *
     * @return array<string, mixed>
     */
    private function toShape(object $row): array
    {
        return [
            'id' => (string) $row->id,
            'status' => (string) $row->status,
            'reason' => $this->extractReason($row->notes ?? null),
            'reserved_at' => $this->isoOrNull($row->reservedAt ?? null),
            'expires_at' => $this->isoOrNull($row->expiresAt ?? null),
            'processed_at' => $this->isoOrNull($row->processedAt ?? null),
            'library_branch_id' => isset($row->libraryBranchId) ? (string) $row->libraryBranchId : null,
            'copy_id' => isset($row->copyId) && $row->copyId !== null ? (string) $row->copyId : null,
            'reader_snapshot' => [
                'user_id' => (string) ($row->userId ?? ''),
                'full_name' => isset($row->userFullName) ? (string) $row->userFullName : null,
                'university_id' => isset($row->userUniversityId) ? (string) $row->userUniversityId : null,
            ],
            'book_snapshot' => [
                'book_id' => isset($row->bookId) ? (string) $row->bookId : null,
                'isbn' => isset($row->isbn) ? (string) $row->isbn : null,
                'title' => isset($row->title) ? (string) $row->title : null,
            ],
        ];
    }

    private function isoOrNull(mixed $datetime): ?string
    {
        if ($datetime === null || $datetime === '') {
            return null;
        }

        return \Carbon\Carbon::parse((string) $datetime)->toISOString();
    }

    /**
     * @return array{cancel_origin:string|null, cancel_reason_code:string|null}|null
     */
    private function extractReason(mixed $notes): ?array
    {
        if ($notes === null || $notes === '') {
            return null;
        }

        $decoded = json_decode((string) $notes, true);
        if (! is_array($decoded)) {
            return null;
        }

        $cancelOrigin = isset($decoded['cancel_origin']) ? (string) $decoded['cancel_origin'] : null;
        $cancelReasonCode = isset($decoded['cancel_reason_code']) ? (string) $decoded['cancel_reason_code'] : null;

        if ($cancelOrigin === null && $cancelReasonCode === null) {
            return null;
        }

        return [
            'cancel_origin' => $cancelOrigin,
            'cancel_reason_code' => $cancelReasonCode,
        ];
    }
}
