<?php

namespace App\Exceptions;

use RuntimeException;

/**
 * Domain rule violation in circulation/reservation flows. The reason code is
 * a stable machine key; the human message comes from lang/librarian.php.
 */
class CirculationException extends RuntimeException
{
    public function __construct(
        public readonly string $reasonCode,
        ?string $message = null,
    ) {
        parent::__construct($message ?? $reasonCode);
    }

    public static function because(string $reasonCode, array $replace = []): self
    {
        return new self($reasonCode, __('librarian.errors.'.$reasonCode, $replace));
    }
}
