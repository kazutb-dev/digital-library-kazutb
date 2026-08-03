<?php

namespace Tests\Unit\Services;

use App\Services\LaravelLogReader;
use PHPUnit\Framework\TestCase;

class LaravelLogReaderTest extends TestCase
{
    private string $logPath;

    protected function setUp(): void
    {
        parent::setUp();
        $this->logPath = tempnam(sys_get_temp_dir(), 'log-reader-test-');
    }

    protected function tearDown(): void
    {
        @unlink($this->logPath);
        parent::tearDown();
    }

    public function test_parses_entries_newest_first_with_levels_and_traces(): void
    {
        file_put_contents($this->logPath, <<<'LOG'
[2026-07-29 10:00:00] production.ERROR: Something broke {"context":1}
#0 /app/app/Http/Controllers/HomeController.php(10): boom()
#1 {main}
[2026-07-29 11:00:00] production.WARNING: Slow query detected
[2026-07-29 12:00:00] production.INFO: Cache warmed
LOG);

        $reader = new LaravelLogReader;
        $entries = $reader->entries($this->logPath);

        $this->assertCount(3, $entries);
        $this->assertSame('info', $entries[0]['level']);
        $this->assertSame('error', $entries[2]['level']);
        $this->assertStringContainsString('Something broke', $entries[2]['message']);
        $this->assertStringContainsString('HomeController.php', $entries[2]['trace']);

        $errorsOnly = $reader->entries($this->logPath, 'error');
        $this->assertCount(1, $errorsOnly);
        $this->assertSame('error', $errorsOnly[0]['level']);
    }

    public function test_masks_credentials_tokens_and_connection_strings(): void
    {
        $reader = new LaravelLogReader;

        $masked = $reader->maskSecrets(
            'password=SuperSecret123 api_key: "abc123xyz" Authorization: Bearer eyJhbGciOi.payload.sig '
            .'base64:aGVsbG8td29ybGQtc2VjcmV0 postgresql://library_user:dev_secret@postgres:5432/db'
        );

        $this->assertStringNotContainsString('SuperSecret123', $masked);
        $this->assertStringNotContainsString('abc123xyz', $masked);
        $this->assertStringNotContainsString('eyJhbGciOi.payload.sig', $masked);
        $this->assertStringNotContainsString('aGVsbG8td29ybGQtc2VjcmV0', $masked);
        $this->assertStringNotContainsString('dev_secret', $masked);
        $this->assertStringContainsString('library_user', $masked);
    }

    public function test_unreadable_path_returns_empty_list(): void
    {
        $reader = new LaravelLogReader;

        $this->assertSame([], $reader->entries('/nonexistent/laravel.log'));
    }
}
