<?php

namespace App\Integrations\Transport;

use App\Directory\ActiveDirectoryHealth;
use App\Directory\ActiveDirectoryService;

final readonly class LdapTransport
{
    public function __construct(private ActiveDirectoryService $directory) {}

    public function health(): ActiveDirectoryHealth
    {
        return $this->directory->health();
    }
}
