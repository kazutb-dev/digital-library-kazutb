<?php

namespace App\Support;

use RuntimeException;

/**
 * Refuses commands that erase or roll back a database unless the resolved
 * database is unmistakably isolated. Both APP_ENV=testing and an isolated
 * target are required; --force is deliberately irrelevant. This prevents a
 * stale cached database configuration from redirecting a test reset to live
 * data.
 */
final class DestructiveDatabaseCommandGuard
{
    /** @var list<string> */
    private const COMMANDS = [
        'db:wipe',
        'migrate:fresh',
        'migrate:refresh',
        'migrate:reset',
        'migrate:rollback',
        'schema:drop',
    ];

    public static function assertSafe(
        ?string $command,
        string $environment,
        string $connection,
        string $database,
    ): void {
        if ($command === null || ! in_array($command, self::COMMANDS, true)) {
            return;
        }

        $environment = mb_strtolower(trim($environment));
        $database = trim($database);
        $isMemorySqlite = $connection === 'sqlite' && $database === ':memory:';
        $isNamedTestDatabase = str_ends_with(mb_strtolower($database), '_test');

        if ($environment === 'testing' && ($isMemorySqlite || $isNamedTestDatabase)) {
            return;
        }

        throw new RuntimeException(sprintf(
            'Blocked destructive database command [%s] in environment [%s] for [%s/%s]. Destructive commands require APP_ENV=testing and either an isolated database ending in "_test" or SQLite :memory:.',
            $command,
            $environment !== '' ? $environment : '[empty]',
            $connection,
            $database !== '' ? $database : '[empty]',
        ));
    }
}
