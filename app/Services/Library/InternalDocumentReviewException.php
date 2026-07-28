<?php

namespace App\Services\Library;

use Exception;

class InternalDocumentReviewException extends Exception
{
    public function __construct(
        private readonly string $errorCode,
        private readonly int $httpStatus,
        string $message = '',
    ) {
        parent::__construct($message);
    }

    public function errorCode(): string
    {
        return $this->errorCode;
    }

    public function httpStatus(): int
    {
        return $this->httpStatus;
    }
}
