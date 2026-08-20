<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        // Several legacy integration tests deliberately replace database and
        // endpoint environment variables. PHPUnit keeps the PHP process alive
        // between test classes, so restore the isolated contract before the
        // next Laravel application is bootstrapped.
        $environment = [
            'APP_ENV' => 'testing',
            'APP_DEMO_LOGIN' => 'false',
            'APP_DEMO_LOGIN_ENABLED' => 'false',
            'CACHE_STORE' => 'array',
            'QUEUE_CONNECTION' => 'sync',
            'SESSION_DRIVER' => 'array',
        ];

        if (getenv('KAZUTB_CANONICAL_POSTGRES_TESTS') !== 'true') {
            $environment += [
                'DB_CONNECTION' => 'sqlite',
                'DB_DATABASE' => ':memory:',
                'DB_URL' => '',
                'DB_HOST' => '127.0.0.1',
                'DB_PORT' => '1',
                'DB_USERNAME' => 'phpunit_forbidden',
                'DB_PASSWORD' => 'phpunit_forbidden',
            ];
        }

        foreach ($environment as $key => $value) {
            putenv($key.'='.$value);
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
        }

        parent::setUp();

        $this->withoutVite();
    }
}
