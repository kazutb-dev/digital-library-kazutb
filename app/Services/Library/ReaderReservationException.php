<?php

namespace App\Services\Library;

class ReaderReservationException extends \RuntimeException
{
    private string $errorCode;
    private int $httpStatus;

    public function __construct(string $errorCode, string $message, int $httpStatus = 422)
    {
        parent::__construct($message);
        $this->errorCode = $errorCode;
        $this->httpStatus = $httpStatus;
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }

    public static function bookNotFound(): self
    {
        return new self('book_not_found', 'Книга не найдена.', 404);
    }

    public static function duplicateReservation(string $existingStatus): self
    {
        return new self(
            'duplicate_reservation',
            "У вас уже есть активное бронирование на эту книгу (статус: {$existingStatus}).",
            409,
        );
    }

    public static function limitReached(int $max): self
    {
        return new self(
            'reservation_limit_reached',
            "Достигнут лимит активных бронирований ({$max}).",
            422,
        );
    }

    public static function noCopiesExist(): self
    {
        return new self('no_copies', 'Экземпляры этой книги не найдены в системе.', 422);
    }

    public static function notFound(): self
    {
        return new self('reservation_not_found', 'Бронирование не найдено.', 404);
    }

    public static function notOwner(): self
    {
        return new self('not_owner', 'Вы не можете управлять чужим бронированием.', 403);
    }

    public static function cannotCancel(string $status): self
    {
        return new self(
            'cannot_cancel',
            "Бронирование со статусом «{$status}» нельзя отменить.",
            422,
        );
    }
}
