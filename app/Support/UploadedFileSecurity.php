<?php

namespace App\Support;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class UploadedFileSecurity
{
    private const EXECUTABLE_EXTENSIONS = [
        'php', 'php3', 'php4', 'php5', 'php7', 'php8', 'phtml', 'phar',
        'cgi', 'pl', 'py', 'rb', 'sh', 'bash', 'cmd', 'bat', 'com', 'exe',
        'dll', 'msi', 'ps1', 'vbs', 'jar',
    ];

    public static function assertSafe(UploadedFile $file, string $field = 'file'): void
    {
        $extension = mb_strtolower(trim($file->getClientOriginalExtension()));
        if (in_array($extension, self::EXECUTABLE_EXTENSIONS, true)) {
            self::reject($field);
        }

        $mime = (string) $file->getMimeType();
        if (in_array($mime, ['application/pdf', 'application/x-pdf'], true)) {
            self::assertPassivePdf($file, $field);
        }
    }

    public static function assertPassivePdf(UploadedFile $file, string $field = 'file'): void
    {
        $contents = file_get_contents($file->getRealPath());
        if (! is_string($contents)
            || ! str_starts_with($contents, '%PDF-')
            || ! str_contains(substr($contents, -4096), '%%EOF')
            || preg_match('/\/(JavaScript|JS|Launch|EmbeddedFile)\b/i', $contents) === 1) {
            self::reject($field);
        }
    }

    private static function reject(string $field): never
    {
        throw ValidationException::withMessages([
            $field => __('validation.uploaded', ['attribute' => $field]),
        ]);
    }
}
