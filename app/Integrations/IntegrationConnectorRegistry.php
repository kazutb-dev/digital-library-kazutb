<?php

namespace App\Integrations;

use App\Integrations\Connectors\ActiveDirectoryConnector;
use App\Integrations\Connectors\EmailConnector;
use App\Integrations\Connectors\UnconfiguredConnector;
use App\Integrations\Contracts\IntegrationConnectorInterface;

final readonly class IntegrationConnectorRegistry
{
    public function __construct(private ActiveDirectoryConnector $activeDirectory, private EmailConnector $email) {}

    public function for(string $code): IntegrationConnectorInterface
    {
        return match ($code) {
            'active_directory' => $this->activeDirectory,
            'email' => $this->email,
            default => new UnconfiguredConnector($code),
        };
    }

    /** @return list<string> */
    public function connectedCodes(): array
    {
        return ['active_directory', 'email'];
    }
}
