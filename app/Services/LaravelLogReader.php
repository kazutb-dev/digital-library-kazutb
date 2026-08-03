<?php

namespace App\Services;

/**
 * Read-only tail parser for the standard Laravel single-file log. The admin
 * error-log page must never mutate the log, so this class only reads the last
 * chunk of the file and splits it into entries.
 */
class LaravelLogReader
{
    /**
     * How much of the file tail to inspect. One megabyte comfortably covers
     * hundreds of entries even with long stack traces.
     */
    private const MAX_BYTES = 1_048_576;

    private const ENTRY_PATTERN = '/^\[(\d{4}-\d{2}-\d{2}[ T][0-9:.,+\-]+)\]\s+(\w+)\.([A-Z]+):\s/m';

    /**
     * Values that must never reach the UI even if they leak into a message or
     * stack trace: credential-like key/value pairs, bearer tokens, and
     * base64-encoded app keys.
     */
    private const SECRET_PATTERNS = [
        // Bearer first: the generic key/value rule below would otherwise
        // consume the word "Bearer" and leave the token itself visible.
        '/(Bearer\s+)[A-Za-z0-9._\-]{8,}/i' => '$1***',
        '/((?:password|passwd|pwd|secret|api[_-]?key|token|authorization|db_password|app_key)["\']?\s*[:=>]+\s*["\']?)[^\s"\'&,;)]+/i' => '$1***',
        '/base64:[A-Za-z0-9+\/=]{16,}/' => 'base64:***',
        '/(postgres(?:ql)?:\/\/[^:\/\s]+:)[^@\s]+(@)/i' => '$1***$2',
    ];

    /**
     * @return list<array{timestamp: string, environment: string, level: string, message: string, trace: string}>
     */
    public function entries(string $path, ?string $level = null, int $limit = 100): array
    {
        if (! is_file($path) || ! is_readable($path)) {
            return [];
        }

        $size = (int) filesize($path);
        $handle = fopen($path, 'rb');
        if ($handle === false) {
            return [];
        }

        try {
            $offset = max(0, $size - self::MAX_BYTES);
            if ($offset > 0) {
                fseek($handle, $offset);
            }
            $chunk = (string) stream_get_contents($handle);
        } finally {
            fclose($handle);
        }

        $matches = [];
        if (! preg_match_all(self::ENTRY_PATTERN, $chunk, $matches, PREG_OFFSET_CAPTURE)) {
            return [];
        }

        $entries = [];
        $count = count($matches[0]);

        for ($index = 0; $index < $count; $index++) {
            // A truncated first entry has its header cut off — the first
            // regex hit is always a complete entry start, so nothing partial
            // ever slips in.
            $start = $matches[0][$index][1];
            $end = $index + 1 < $count ? $matches[0][$index + 1][1] : strlen($chunk);
            $raw = substr($chunk, $start, $end - $start);

            $entryLevel = strtolower($matches[3][$index][0]);
            if ($level !== null && $entryLevel !== $level) {
                continue;
            }

            $body = trim(preg_replace(self::ENTRY_PATTERN, '', $raw, 1) ?? '');
            $newlinePosition = strpos($body, "\n");
            $message = $newlinePosition === false ? $body : substr($body, 0, $newlinePosition);
            $trace = $newlinePosition === false ? '' : trim(substr($body, $newlinePosition + 1));

            $entries[] = [
                'timestamp' => $matches[1][$index][0],
                'environment' => $matches[2][$index][0],
                'level' => $entryLevel,
                'message' => $this->maskSecrets($message),
                'trace' => $this->maskSecrets($trace),
            ];
        }

        return array_slice(array_reverse($entries), 0, $limit);
    }

    /**
     * @return array<string, int>
     */
    public function levelCounts(string $path): array
    {
        $counts = [];
        foreach ($this->entries($path, null, PHP_INT_MAX) as $entry) {
            $counts[$entry['level']] = ($counts[$entry['level']] ?? 0) + 1;
        }
        ksort($counts);

        return $counts;
    }

    public function maskSecrets(string $text): string
    {
        foreach (self::SECRET_PATTERNS as $pattern => $replacement) {
            $text = (string) preg_replace($pattern, $replacement, $text);
        }

        return $text;
    }
}
