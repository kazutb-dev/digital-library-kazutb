<?php

namespace App\Integrations\Transport;

use InvalidArgumentException;

final class SftpTransport
{
    /** @return array{host:string,port:int,host_key_fingerprint:string} */
    public function validateConfiguration(string $host, int $port, string $hostKeyFingerprint): array
    {
        $host = mb_strtolower(rtrim(trim($host), '.'));
        if ($host === '' || $host === 'localhost' || filter_var($host, FILTER_VALIDATE_IP) !== false || ! preg_match('/^[a-z0-9.-]+$/', $host)) {
            throw new InvalidArgumentException('unsafe_sftp_host');
        }
        if ($port < 1 || $port > 65535 || ! preg_match('/^SHA256:[A-Za-z0-9+\/=]{20,}$/', $hostKeyFingerprint)) {
            throw new InvalidArgumentException('invalid_sftp_configuration');
        }

        return ['host' => $host, 'port' => $port, 'host_key_fingerprint' => $hostKeyFingerprint];
    }
}
