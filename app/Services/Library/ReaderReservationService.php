<?php

namespace App\Services\Library;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class ReaderReservationService
{
    private const RESERVATION_EXPIRY_DAYS = 7;
    private const MAX_ACTIVE_RESERVATIONS = 5;

    /**
     * Create a reservation for a reader.
     *
     * @return array{reservation: array, created: bool}
     *
     * @throws ReaderReservationException
     */
    public function create(string $crmUserId, string $bookId): array
    {
        $book = DB::connection('pgsql')
            ->table('public.Book')
            ->where('id', $bookId)
            ->first(['id', 'title', 'isbn']);

        if (! $book) {
            throw ReaderReservationException::bookNotFound();
        }

        // Check duplicate: active reservation for same book by same user.
        $existing = DB::connection('pgsql')
            ->table('public.Reservation')
            ->where('userId', $crmUserId)
            ->where('bookId', $bookId)
            ->whereIn('status', ['PENDING', 'READY'])
            ->first(['id', 'status']);

        if ($existing) {
            throw ReaderReservationException::duplicateReservation($existing->status);
        }

        // Check active reservation count limit.
        $activeCount = DB::connection('pgsql')
            ->table('public.Reservation')
            ->where('userId', $crmUserId)
            ->whereIn('status', ['PENDING', 'READY'])
            ->count();

        if ($activeCount >= self::MAX_ACTIVE_RESERVATIONS) {
            throw ReaderReservationException::limitReached(self::MAX_ACTIVE_RESERVATIONS);
        }
        // Find an available copy for this book (prefer first available).
        $copy = DB::connection('pgsql')
            ->table('public.BookCopy')
            ->where('bookId', $bookId)
            ->where('status', 'AVAILABLE')
            ->first(['id', 'libraryBranchId']);

        // If no copy is available, still allow reservation (waitlist-style) — pick branch from any copy.
        $libraryBranchId = $copy?->libraryBranchId;
        $copyId = $copy?->id;

        if (! $libraryBranchId) {
            $anyCopy = DB::connection('pgsql')
                ->table('public.BookCopy')
                ->where('bookId', $bookId)
                ->first(['libraryBranchId']);

            $libraryBranchId = $anyCopy?->libraryBranchId;
        }

        if (! $libraryBranchId) {
            throw ReaderReservationException::noCopiesExist();
        }

        $now = now();
        $reservationId = (string) Str::uuid();

        DB::connection('pgsql')
            ->table('public.Reservation')
            ->insert([
                'id' => $reservationId,
                'status' => 'PENDING',
                'reservedAt' => $now,
                'expiresAt' => $now->copy()->addDays(self::RESERVATION_EXPIRY_DAYS),
                'userId' => $crmUserId,
                'bookId' => $bookId,
                'libraryBranchId' => $libraryBranchId,
                'copyId' => $copyId,
                'notes' => json_encode(['origin' => 'reader_self_service']),
                'createdAt' => $now,
                'updatedAt' => $now,
            ]);

        // If a specific copy was assigned, mark it as RESERVED.
        if ($copyId) {
            DB::connection('pgsql')
                ->table('public.BookCopy')
                ->where('id', $copyId)
                ->where('status', 'AVAILABLE')
                ->update(['status' => 'RESERVED', 'updatedAt' => $now]);
        }

        $reservation = DB::connection('pgsql')
            ->table('public.Reservation as r')
            ->leftJoin('public.Book as b', 'b.id', '=', 'r.bookId')
            ->where('r.id', $reservationId)
            ->first([
                'r.id', 'r.status', 'r.reservedAt', 'r.expiresAt',
                'r.copyId', 'r.createdAt',
                'b.title as bookTitle', 'b.isbn as bookIsbn', 'b.publishYear as bookPublishYear',
            ]);

        return [
            'reservation' => $this->formatReservation($reservation),
            'created' => true,
        ];
    }

    /**
     * Cancel a reader's own reservation.
     *
     * @throws ReaderReservationException
     */
    public function cancel(string $reservationId, string $crmUserId): array
    {
        $reservation = DB::connection('pgsql')
            ->table('public.Reservation')
            ->where('id', $reservationId)
            ->first(['id', 'status', 'userId', 'copyId']);

        if (! $reservation) {
            throw ReaderReservationException::notFound();
        }

        if ($reservation->userId !== $crmUserId) {
            throw ReaderReservationException::notOwner();
        }

        if (! in_array($reservation->status, ['PENDING', 'READY'], true)) {
            throw ReaderReservationException::cannotCancel($reservation->status);
        }

        $now = now();

        DB::connection('pgsql')
            ->table('public.Reservation')
            ->where('id', $reservationId)
            ->update([
                'status' => 'CANCELLED',
                'processedAt' => $now,
                'updatedAt' => $now,
                'notes' => json_encode([
                    'cancel_origin' => 'reader_self_service',
                    'cancel_reason_code' => 'READER_CANCELLED',
                ]),
            ]);

        // Release the copy back to AVAILABLE if one was assigned.
        if ($reservation->copyId) {
            DB::connection('pgsql')
                ->table('public.BookCopy')
                ->where('id', $reservation->copyId)
                ->where('status', 'RESERVED')
                ->update(['status' => 'AVAILABLE', 'updatedAt' => $now]);
        }

        return ['cancelled' => true, 'id' => $reservationId];
    }

    /**
     * Check if user has an active reservation for a specific book.
     */
    public function checkForBook(string $crmUserId, string $bookId): ?array
    {
        $reservation = DB::connection('pgsql')
            ->table('public.Reservation as r')
            ->leftJoin('public.Book as b', 'b.id', '=', 'r.bookId')
            ->where('r.userId', $crmUserId)
            ->where('r.bookId', $bookId)
            ->whereIn('r.status', ['PENDING', 'READY'])
            ->first([
                'r.id', 'r.status', 'r.reservedAt', 'r.expiresAt',
                'r.copyId', 'r.createdAt',
                'b.title as bookTitle', 'b.isbn as bookIsbn', 'b.publishYear as bookPublishYear',
            ]);

        return $reservation ? $this->formatReservation($reservation) : null;
    }

    private function formatReservation(object $row): array
    {
        return [
            'id' => $row->id,
            'status' => $row->status,
            'reservedAt' => $row->reservedAt,
            'expiresAt' => $row->expiresAt,
            'copyId' => $row->copyId,
            'book' => [
                'title' => $row->bookTitle,
                'isbn' => $row->bookIsbn,
                'publishYear' => $row->bookPublishYear ?? null,
            ],
        ];
    }

    /**
     * Resolve a public.Book UUID from an ISBN string.
     */
    public function resolveBookIdByIsbn(string $isbn): ?string
    {
        $isbn = trim($isbn);
        if ($isbn === '') {
            return null;
        }

        $id = DB::connection('pgsql')
            ->table('public.Book')
            ->where('isbn', $isbn)
            ->value('id');

        return is_string($id) ? $id : null;
    }
}
