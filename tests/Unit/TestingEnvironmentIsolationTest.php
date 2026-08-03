<?php

namespace Tests\Unit;

use Tests\TestCase;

class TestingEnvironmentIsolationTest extends TestCase
{
    public function test_phpunit_cannot_load_the_runtime_config_cache_or_database(): void
    {
        $this->assertSame('testing', app()->environment());
        if (config('database.default') === 'pgsql') {
            $this->assertStringEndsWith('_test', (string) config('database.connections.pgsql.database'));
            $this->assertNotSame((string) getenv('POSTGRES_DB'), (string) config('database.connections.pgsql.database'));
        } else {
            $this->assertSame('sqlite', config('database.default'));
            $this->assertSame(':memory:', config('database.connections.sqlite.database'));
            $this->assertSame(':memory:', config('database.connections.pgsql.database'));
            $this->assertSame('127.0.0.1', config('database.connections.pgsql.host'));
            $this->assertSame('1', (string) config('database.connections.pgsql.port'));
            $this->assertSame('phpunit_forbidden', config('database.connections.pgsql.username'));
        }
        $this->assertStringContainsString(
            'kazutb-library-',
            app()->getCachedConfigPath(),
        );
        $this->assertFileDoesNotExist(app()->getCachedConfigPath());
    }
}
