<?php

namespace App\Services\Library;

use App\Models\Library\CirculationLoan;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class CirculationLoanReadService
{
    private const DUE_SOON_DAYS = 3;

    /**
     * @return array<string, mixed>|null
     */
    public function findLoan(string $loanId): ?array
    {
        $loan = CirculationLoan::query()->find($loanId);

        return $loan ? $this->toArray($loan) : null;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function findActiveLoanByCopy(string $copyId): ?array
    {
        $loan = CirculationLoan::query()
            ->where('copy_id', $copyId)
            ->where('status', 'active')
            ->whereNull('returned_at')
            ->orderByDesc('issued_at')
            ->first();

        return $loan ? $this->toArray($loan) : null;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function findLoansByReader(string $readerId, ?string $status = null): array
    {
        $query = CirculationLoan::query()
            ->where('reader_id', $readerId)
            ->orderByDesc('issued_at')
            ->orderByDesc('created_at');

        if ($status !== null) {
            $query->where('status', $status);
        }

        return $query
            ->get()
            ->map(fn (CirculationLoan $loan): array => $this->toArray($loan))
            ->all();
    }

    /**
     * Loan summary counts for a reader.
     *
     * @return array{activeLoans: int, overdueLoans: int, dueSoonLoans: int, returnedLoans: int, totalLoans: int}
     */
    public function summaryForReader(string $readerId): array
    {
        $now = now();
        $dueSoonThreshold = $now->copy()->addDays(self::DUE_SOON_DAYS);

        $active = CirculationLoan::query()
            ->where('reader_id', $readerId)
            ->where('status', 'active')
            ->get();

        $overdueCount = 0;
        $dueSoonCount = 0;

        foreach ($active as $loan) {
            $dueAt = $loan->due_at instanceof Carbon ? $loan->due_at : null;
            if ($dueAt === null) {
                continue;
            }

            if ($dueAt->isPast()) {
                $overdueCount++;
            } elseif ($dueAt->lte($dueSoonThreshold)) {
                $dueSoonCount++;
            }
        }

        $returnedCount = CirculationLoan::query()
            ->where('reader_id', $readerId)
            ->where('status', 'returned')
            ->count();

        return [
            'activeLoans' => $active->count(),
            'overdueLoans' => $overdueCount,
            'dueSoonLoans' => $dueSoonCount,
            'returnedLoans' => $returnedCount,
            'totalLoans' => $active->count() + $returnedCount,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function toArray(CirculationLoan $loan): array
    {
        $dueAt = $loan->due_at instanceof Carbon ? $loan->due_at : null;
        $returnedAt = $loan->returned_at instanceof Carbon ? $loan->returned_at : null;
        $isOverdue = $returnedAt === null && $dueAt !== null && $dueAt->isPast();
        $isDueSoon = ! $isOverdue && $returnedAt === null && $dueAt !== null
            && $dueAt->isFuture() && $dueAt->diffInDays(now()) <= self::DUE_SOON_DAYS;

        $book = $this->resolveBookForCopy((string) $loan->copy_id);

        $renewCount = (int) $loan->renew_count;
        [$canRenew, $renewBlockReason] = $this->assessRenewEligibility(
            (string) $loan->status, $returnedAt, $isOverdue, $renewCount
        );

        return [
            'id' => (string) $loan->id,
            'readerId' => (string) $loan->reader_id,
            'copyId' => (string) $loan->copy_id,
            'status' => (string) $loan->status,
            'issuedAt' => $loan->issued_at?->toAtomString(),
            'dueAt' => $dueAt?->toAtomString(),
            'returnedAt' => $returnedAt?->toAtomString(),
            'renewCount' => $renewCount,
            'maxRenewals' => CirculationLoanWriteService::MAX_RENEWALS,
            'isOverdue' => $isOverdue,
            'isDueSoon' => $isDueSoon,
            'canRenew' => $canRenew,
            'renewBlockReason' => $renewBlockReason,
            'book' => $book,
            'source' => 'app.circulation_loans',
        ];
    }

    /**
     * Determine renewal eligibility and a human-readable block reason if not.
     *
     * @return array{0: bool, 1: string|null}
     */
    private function assessRenewEligibility(string $status, ?Carbon $returnedAt, bool $isOverdue, int $renewCount): array
    {
        if ($status !== 'active' || $returnedAt !== null) {
            return [false, 'Возвращённые книги нельзя продлить.'];
        }

        if ($isOverdue) {
            return [false, 'Просроченную выдачу нельзя продлить онлайн. Обратитесь в библиотеку.'];
        }

        if ($renewCount >= CirculationLoanWriteService::MAX_RENEWALS) {
            return [false, 'Достигнут лимит продлений (' . CirculationLoanWriteService::MAX_RENEWALS . '/' . CirculationLoanWriteService::MAX_RENEWALS . ').'];
        }

        return [true, null];
    }

    /**
     * Resolve book metadata from copy_id via app.book_copies → app.documents.
     *
     * @return array{title: string|null, isbn: string|null, author: string|null, inventoryNumber: string|null}
     */
    private function resolveBookForCopy(string $copyId): array
    {
        $row = DB::connection('pgsql')
            ->table('app.book_copies as bc')
            ->leftJoin('app.documents as d', 'd.id', '=', 'bc.document_id')
            ->where('bc.id', $copyId)
            ->first([
                'd.title_display',
                'd.title_raw',
                'd.isbn_normalized',
                'd.isbn_raw',
                'bc.inventory_number_normalized',
                'bc.document_id',
            ]);

        if (! $row) {
            return ['title' => null, 'isbn' => null, 'author' => null, 'inventoryNumber' => null];
        }

        $title = $row->title_display ?: $row->title_raw ?: null;
        $isbn = $row->isbn_normalized ?: $row->isbn_raw ?: null;

        // Resolve primary author.
        $author = null;
        if ($row->document_id) {
            $authorRow = DB::connection('pgsql')
                ->table('app.document_authors as da')
                ->join('app.authors as a', 'a.id', '=', 'da.author_id')
                ->where('da.document_id', $row->document_id)
                ->orderBy('da.sort_order')
                ->first(['a.display_name', 'a.normalized_name']);

            if ($authorRow) {
                $author = $authorRow->display_name ?: $authorRow->normalized_name ?: null;
            }
        }

        return [
            'title' => $title ? (string) $title : null,
            'isbn' => $isbn ? (string) $isbn : null,
            'author' => $author ? (string) $author : null,
            'inventoryNumber' => $row->inventory_number_normalized ? (string) $row->inventory_number_normalized : null,
        ];
    }
}
