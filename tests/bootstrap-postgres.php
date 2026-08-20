<?php

use Illuminate\Foundation\Testing\RefreshDatabaseState;

/**
 * Canonical integration-test bootstrap. Its first responsibility is refusing
 * to boot against development or production data.
 */
$database = (string) (getenv('DB_DATABASE') ?: '');
$connection = (string) (getenv('DB_CONNECTION') ?: '');

if ($connection !== 'pgsql' || ! str_ends_with($database, '_test')) {
    throw new RuntimeException('Canonical tests require an isolated PostgreSQL database whose name ends in "_test".');
}

$runtimeDatabase = (string) (getenv('POSTGRES_DB') ?: '');
if ($runtimeDatabase !== '' && $database === $runtimeDatabase) {
    throw new RuntimeException('The canonical test database must differ from the runtime PostgreSQL database.');
}

$testCacheToken = bin2hex(random_bytes(8));
$testCachePrefix = sys_get_temp_dir().'/kazutb-library-postgres-phpunit-'.$testCacheToken;

$environment = [
    'APP_ENV' => 'testing',
    'APP_DEMO_LOGIN' => 'false',
    'APP_DEMO_LOGIN_ENABLED' => 'false',
    'APP_URL' => 'http://localhost',
    'AD_ENABLED' => 'false',
    'KAZUTB_CANONICAL_POSTGRES_TESTS' => 'true',
    'APP_CONFIG_CACHE' => $testCachePrefix.'-config.php',
    'APP_EVENTS_CACHE' => $testCachePrefix.'-events.php',
    'APP_PACKAGES_CACHE' => $testCachePrefix.'-packages.php',
    'APP_ROUTES_CACHE' => $testCachePrefix.'-routes.php',
    'APP_SERVICES_CACHE' => $testCachePrefix.'-services.php',
    'DB_CONNECTION' => 'pgsql',
    'DB_DATABASE' => $database,
    'DB_URL' => '',
    'CACHE_STORE' => 'array',
    'FILESYSTEM_DISK' => 'local',
    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
    'INTEGRATION_ALLOWED_TOKENS' => 'phpunit-test-token',
    'EXTERNAL_AUTH_LOGIN_URL' => 'http://127.0.0.1:1/test-login-disabled',
    'EXTERNAL_AUTH_LOGOUT_URL' => 'http://127.0.0.1:1/test-logout-disabled',
    'FRONTEND_CATALOG_PROXY_URL' => 'http://127.0.0.1:1/test-catalog-disabled',
];

foreach ($environment as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require dirname(__DIR__).'/vendor/autoload.php';

// The wrapper has already performed the only canonical migrate:fresh. Mark it
// for Laravel's RefreshDatabase trait so the trait opens per-test transactions
// instead of attempting a second fresh migration that cannot discover tables
// in the deliberately separate `app` schema.
RefreshDatabaseState::$migrated = true;
