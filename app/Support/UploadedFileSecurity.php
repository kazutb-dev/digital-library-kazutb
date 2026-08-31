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
        $path = $file->getRealPath();
        $handle = is_string($path) ? @fopen($path, 'rb') : false;
        if ($handle === false) {
            self::reject($field);
        }

        try {
            $header = fread($handle, 5);
            $size = filesize($path);
            if ($header !== '%PDF-' || $size === false || fseek($handle, max(0, $size - 4096)) !== 0) {
                self::reject($field);
            }
            $tail = stream_get_contents($handle);
            if (! is_string($tail) || ! str_contains($tail, '%%EOF') || self::hasActivePdfDictionary($path)) {
                self::reject($field);
            }
        } finally {
            fclose($handle);
        }
    }

    /**
     * Scan PDF dictionaries while skipping binary stream bodies. Looking for
     * `/JS` in the complete byte string rejects large scanned books whenever
     * those three bytes happen to occur inside a compressed page image.
     */
    private static function hasActivePdfDictionary(string $path): bool
    {
        $handle = @fopen($path, 'rb');
        if ($handle === false) {
            return true;
        }

        $buffer = '';
        $insideStream = false;
        $active = '/\/(JavaScript|JS|Launch|EmbeddedFile)\b/i';

        try {
            while (! feof($handle)) {
                $chunk = fread($handle, 65536);
                if ($chunk === false) {
                    return true;
                }
                $buffer .= $chunk;

                while (true) {
                    if ($insideStream) {
                        if (preg_match('/(?:\r\n|\r|\n)endstream\b/', $buffer, $match, PREG_OFFSET_CAPTURE) !== 1) {
                            $buffer = substr($buffer, -32);
                            break;
                        }
                        $end = $match[0][1] + strlen($match[0][0]);
                        $buffer = substr($buffer, $end);
                        $insideStream = false;
                        continue;
                    }

                    if (preg_match('/\bstream(?:\r\n|\r|\n)/', $buffer, $match, PREG_OFFSET_CAPTURE) === 1) {
                        $start = $match[0][1];
                        if (preg_match($active, substr($buffer, 0, $start)) === 1) {
                            return true;
                        }
                        $buffer = substr($buffer, $start + strlen($match[0][0]));
                        $insideStream = true;
                        continue;
                    }

                    if (strlen($buffer) > 96) {
                        $searchable = substr($buffer, 0, -64);
                        if (preg_match($active, $searchable) === 1) {
                            return true;
                        }
                        $buffer = substr($buffer, -64);
                    }
                    break;
                }
            }

            return $insideStream || preg_match($active, $buffer) === 1;
        } finally {
            fclose($handle);
        }
    }

    private static function reject(string $field): never
    {
        throw ValidationException::withMessages([
            $field => __('validation.uploaded', ['attribute' => $field]),
        ]);
    }
}
