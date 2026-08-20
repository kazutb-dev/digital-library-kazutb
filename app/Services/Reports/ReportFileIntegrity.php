<?php

namespace App\Services\Reports;

use Illuminate\Contracts\Filesystem\Filesystem;
use RuntimeException;

final class ReportFileIntegrity
{
    /** @return array{hash: string, size: int} */
    public function inspect(Filesystem $disk, string $path): array
    {
        $stream = $disk->readStream($path);
        if (! is_resource($stream)) {
            throw new RuntimeException('Unable to open the protected report archive.');
        }

        try {
            $hash = hash_init('sha256');
            $size = 0;
            while (! feof($stream)) {
                $chunk = fread($stream, 1024 * 1024);
                if ($chunk === false) {
                    throw new RuntimeException('Unable to read the protected report archive.');
                }
                $size += strlen($chunk);
                hash_update($hash, $chunk);
            }

            return ['hash' => hash_final($hash), 'size' => $size];
        } finally {
            fclose($stream);
        }
    }

    public function assert(Filesystem $disk, string $path, string $hash, int $size): void
    {
        if (! $disk->exists($path)) {
            throw new RuntimeException('Protected report archive is missing.');
        }
        $actual = $this->inspect($disk, $path);
        if (! hash_equals($hash, $actual['hash']) || $actual['size'] !== $size) {
            throw new RuntimeException('Protected report archive integrity verification failed.');
        }
    }
}
