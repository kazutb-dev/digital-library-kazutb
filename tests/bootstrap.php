<?php

/**
 * PHPUnit runs inside the same container as the application. Override the
 * container-level production environment before Laravel is bootstrapped so a
 * cached config or a named pgsql connection can never reach the live database.
 */
$testCacheToken = bin2hex(random_bytes(8));
$testCachePrefix = sys_get_temp_dir().'/kazutb-library-phpunit-'.$testCacheToken;

$testEnvironment = [
    'APP_ENV' => 'testing',
    'APP_DEMO_LOGIN' => 'false',
    'APP_DEMO_LOGIN_ENABLED' => 'false',
    'APP_URL' => 'http://localhost',
    'AD_ENABLED' => 'false',
    'KAZUTB_CANONICAL_POSTGRES_TESTS' => 'false',
    'APP_CONFIG_CACHE' => $testCachePrefix.'-config.php',
    'APP_EVENTS_CACHE' => $testCachePrefix.'-events.php',
    'APP_PACKAGES_CACHE' => $testCachePrefix.'-packages.php',
    'APP_ROUTES_CACHE' => $testCachePrefix.'-routes.php',
    'APP_SERVICES_CACHE' => $testCachePrefix.'-services.php',
    'DB_CONNECTION' => 'sqlite',
    'DB_DATABASE' => ':memory:',
    'DB_URL' => '',
    'DB_HOST' => '127.0.0.1',
    'DB_PORT' => '1',
    'DB_USERNAME' => 'phpunit_forbidden',
    'DB_PASSWORD' => 'phpunit_forbidden',
    'CACHE_STORE' => 'array',
    'EXTERNAL_AUTH_LOGIN_URL' => 'http://127.0.0.1:1/test-login-disabled',
    'EXTERNAL_AUTH_LOGOUT_URL' => 'http://127.0.0.1:1/test-logout-disabled',
    'FILESYSTEM_DISK' => 'local',
    'FRONTEND_CATALOG_PROXY_URL' => 'http://127.0.0.1:1/test-catalog-disabled',
    'INTEGRATION_ALLOWED_TOKENS' => 'phpunit-test-token',
    // Integration middleware writes explicitly to the `stack` channel rather
    // than the default channel. Keep both test log paths on stderr so PHPUnit
    // never appends to runtime-owned daily log files.
    'LOG_CHANNEL' => 'stderr',
    'LOG_STACK' => 'stderr',
    'LOG_LEVEL' => 'debug',
    'MAIL_MAILER' => 'array',
    'QUEUE_CONNECTION' => 'sync',
    'SESSION_DRIVER' => 'array',
];

foreach ($testEnvironment as $key => $value) {
    putenv($key.'='.$value);
    $_ENV[$key] = $value;
    $_SERVER[$key] = $value;
}

require dirname(__DIR__).'/vendor/autoload.php';
