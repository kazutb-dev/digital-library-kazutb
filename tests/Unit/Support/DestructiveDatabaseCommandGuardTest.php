<?php

namespace Tests\Unit\Support;

use App\Support\DestructiveDatabaseCommandGuard;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class DestructiveDatabaseCommandGuardTest extends TestCase
{
    #[DataProvider('destructiveCommands')]
    public function test_it_blocks_destructive_commands_for_a_non_test_database(string $command): void
    {
        $this->expectException(RuntimeException::class);

        DestructiveDatabaseCommandGuard::assertSafe($command, 'production', 'pgsql', 'digital_library_dev');
    }

    public function test_it_allows_an_explicit_postgresql_test_database(): void
    {
        DestructiveDatabaseCommandGuard::assertSafe('migrate:fresh', 'testing', 'pgsql', 'digital_library_test');

        $this->addToAssertionCount(1);
    }

    public function test_it_blocks_a_test_named_database_outside_testing_environment(): void
    {
        $this->expectException(RuntimeException::class);

        DestructiveDatabaseCommandGuard::assertSafe('migrate:fresh', 'production', 'pgsql', 'digital_library_test');
    }

    public function test_it_allows_in_memory_sqlite_and_non_destructive_migrations(): void
    {
        DestructiveDatabaseCommandGuard::assertSafe('migrate:fresh', 'testing', 'sqlite', ':memory:');
        DestructiveDatabaseCommandGuard::assertSafe('migrate', 'production', 'pgsql', 'digital_library_dev');

        $this->addToAssertionCount(2);
    }

    /** @return array<string, array{string}> */
    public static function destructiveCommands(): array
    {
        return [
            'wipe' => ['db:wipe'],
            'fresh' => ['migrate:fresh'],
            'refresh' => ['migrate:refresh'],
            'reset' => ['migrate:reset'],
            'rollback' => ['migrate:rollback'],
            'schema drop' => ['schema:drop'],
        ];
    }
}
