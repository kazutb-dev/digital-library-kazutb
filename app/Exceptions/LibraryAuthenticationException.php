<?php

namespace App\Exceptions;

use RuntimeException;

class LibraryAuthenticationException extends RuntimeException
{
    public function __construct(
        string $message,
        public readonly int $status = 401,
    ) {
        parent::__construct($message);
    }
}
