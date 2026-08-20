<?php

namespace Tests\Unit\Support;

use App\Support\StorageKey;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class StorageKeyTest extends TestCase
{
    /** @return array<string, array{string}> */
    public static function unsafePaths(): array
    {
        return [
            'parent traversal' => ['../private.pdf'],
            'encoded slash traversal' => ['..%2fprivate.pdf'],
            'encoded dot traversal' => ['%2e%2e/private.pdf'],
            'absolute unix path' => ['/etc/passwd'],
            'windows traversal' => ['..\\private.pdf'],
            'absolute windows path' => ['C:\\private.pdf'],
        ];
    }

    #[DataProvider('unsafePaths')]
    public function test_unsafe_storage_paths_are_rejected(string $path): void
    {
        $this->assertFalse(StorageKey::isSafe($path));
    }

    public function test_generated_private_storage_key_is_allowed(): void
    {
        $this->assertTrue(StorageKey::isSafe('repository/550e8400-e29b-41d4-a716-446655440000/v1/document.pdf'));
    }
}
