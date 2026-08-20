<?php

namespace App\Directory;

final readonly class ActiveDirectoryHealth
{
    public function __construct(
        public bool $connected,
        public float $latencyMs,
        public ?string $errorCategory = null,
    ) {}

    /** @return array{connected:bool,latency_ms:float,error_category:?string} */
    public function toArray(): array
    {
        return ['connected' => $this->connected, 'latency_ms' => $this->latencyMs, 'error_category' => $this->errorCategory];
    }
}
