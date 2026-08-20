<?php

namespace App\Integrations\Transport;

use InvalidArgumentException;

final class FileImportTransport
{
    /** @return array{path:string,size:int,sha256:string} */
    public function inspect(string $path, int $maxBytes = 52_428_800): array
    {
        $root = realpath(storage_path('app/private/integration-imports'));
        $real = realpath($path);
        if ($root === false || $real === false || ! str_starts_with($real, $root.DIRECTORY_SEPARATOR) || ! is_file($real)) {
            throw new InvalidArgumentException('unsafe_import_path');
        }
        $size = filesize($real);
        if (! is_int($size) || $size < 1 || $size > $maxBytes) {
            throw new InvalidArgumentException('invalid_import_size');
        }

        return ['path' => $real, 'size' => $size, 'sha256' => hash_file('sha256', $real) ?: throw new InvalidArgumentException('import_hash_failed')];
    }
}
