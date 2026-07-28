<?php

namespace App\Services\Library;

use RuntimeException;

class IntegrationReservationMutationException extends RuntimeException
{
    public function __construct(
        public readonly string $errorCode,
        public readonly string $reasonCode,
        string $message,
        public readonly int $httpStatus,
    ) {
        parent::__construct($message);
    }
}
