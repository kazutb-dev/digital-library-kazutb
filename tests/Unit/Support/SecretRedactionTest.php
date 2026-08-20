<?php

namespace Tests\Unit\Support;

use App\Integrations\Support\SecretRedactor;
use App\Services\AuditLogger;
use PHPUnit\Framework\Attributes\DataProvider;
use ReflectionMethod;
use Tests\TestCase;

class SecretRedactionTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function sensitiveKeys(): array
    {
        return collect([
            'password',
            'passwd',
            'token',
            'secret',
            'authorization',
            'cookie',
            'api_key',
            'client_secret',
            'access_token',
            'refresh_token',
        ])->mapWithKeys(fn (string $key): array => [$key => [$key]])->all();
    }

    #[DataProvider('sensitiveKeys')]
    public function test_nested_integration_values_are_redacted(string $key): void
    {
        $result = app(SecretRedactor::class)->redact([
            'outer' => ['inner' => [$key => 'synthetic-sensitive-marker']],
            'safe' => 'visible',
        ]);

        $this->assertSame('[REDACTED]', $result['outer']['inner'][$key]);
        $this->assertSame('visible', $result['safe']);
    }

    #[DataProvider('sensitiveKeys')]
    public function test_nested_audit_values_are_redacted(string $key): void
    {
        $sanitize = new ReflectionMethod(AuditLogger::class, 'sanitize');
        $result = $sanitize->invoke(app(AuditLogger::class), [
            'outer' => ['inner' => [$key => 'synthetic-sensitive-marker']],
            'safe' => 'visible',
        ]);

        $this->assertSame('[REDACTED]', $result['outer']['inner'][$key]);
        $this->assertSame('visible', $result['safe']);
    }
}
