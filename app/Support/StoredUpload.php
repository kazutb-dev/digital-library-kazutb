<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Throwable;

final class StoredUpload
{
    public static function put(UploadedFile $file, string $directory, string $disk): string
    {
        UploadedFileSecurity::assertSafe($file);
        $path = $file->store($directory, $disk);

        if (! is_string($path) || trim($path) === '') {
            throw new RuntimeException("Unable to store uploaded file on disk [{$disk}].");
        }

        return $path;
    }

    /**
     * Best-effort compensation for a file write that cannot participate in a
     * database transaction. Failed cleanup is reported instead of being
     * silently ignored or masking the original application exception.
     */
    public static function deleteOrReport(?string $path, string $disk): void
    {
        if (! is_string($path) || trim($path) === '') {
            return;
        }

        try {
            $filesystem = Storage::disk($disk);
            if (! $filesystem->exists($path)) {
                return;
            }

            if (! $filesystem->delete($path)) {
                throw new RuntimeException("Unable to delete stored file [{$path}] from disk [{$disk}].");
            }
        } catch (Throwable $exception) {
            report($exception);
        }
    }
}
