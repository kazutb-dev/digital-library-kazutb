<?php

namespace App\Exceptions;

use RuntimeException;

final class ActiveDirectoryException extends RuntimeException
{
    public function __construct(public readonly string $category, ?\Throwable $previous = null)
    {
        parent::__construct('Active Directory operation failed.', 0, $previous);
    }
}
