<?php

namespace App\Support;

final class StorageKey
{
    public static function isSafe(string $path): bool
    {
        $path = trim($path);
        if ($path === '' || str_contains($path, "\0") || str_contains($path, '\\')) {
            return false;
        }

        $decoded = $path;
        for ($pass = 0; $pass < 2; $pass++) {
            $next = rawurldecode($decoded);
            if ($next === $decoded) {
                break;
            }
            $decoded = $next;
        }

        if (str_starts_with($decoded, '/') || preg_match('/^[A-Za-z]:[\\\\\/]/', $decoded) === 1) {
            return false;
        }

        foreach (explode('/', $decoded) as $segment) {
            if ($segment === '' || $segment === '.' || $segment === '..') {
                return false;
            }
        }

        return true;
    }
}
