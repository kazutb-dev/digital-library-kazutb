<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Database\Migrations\Migrator;
use Illuminate\Support\Facades\DB;

require __DIR__.'/../vendor/autoload.php';

$app = require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$environment = (string) $app->environment();
$connection = (string) config('database.default');
$database = (string) config("database.connections.{$connection}.database");
$locale = (string) config('app.locale');
$debug = (bool) config('app.debug');

if ($connection !== 'pgsql') {
    fwrite(STDERR, "[entrypoint] PostgreSQL is required for the application runtime.\n");
    exit(64);
}

$pdoDatabase = (string) (DB::selectOne('select current_database() as name')->name ?? '');

echo "[entrypoint] env={$environment} db={$database} pdo_db={$pdoDatabase} locale={$locale} debug=".($debug ? 'true' : 'false').PHP_EOL;

if ($environment === 'testing' || $database === '' || str_ends_with(strtolower($database), '_test') || $debug) {
    fwrite(STDERR, "[entrypoint] Unsafe application runtime configuration; refusing to start.\n");
    exit(64);
}

if ($pdoDatabase !== $database) {
    fwrite(STDERR, "[entrypoint] Configured database and PDO current_database() differ; refusing to start.\n");
    exit(64);
}

/** @var Migrator $migrator */
$migrator = $app->make(Migrator::class);
if (! $migrator->repositoryExists()) {
    fwrite(STDERR, "[entrypoint] Migration repository is missing; refusing to start.\n");
    exit(78);
}

$files = $migrator->getMigrationFiles([database_path('migrations')]);
$ran = $migrator->getRepository()->getRan();
$pending = array_values(array_diff(array_keys($files), $ran));

if ($pending !== []) {
    fwrite(STDERR, sprintf(
        "[entrypoint] Schema is incompatible: %d migration(s) pending; migrations were NOT applied. First pending: %s\n",
        count($pending),
        $pending[0],
    ));
    exit(78);
}

echo '[entrypoint] Schema compatibility confirmed; pending migrations=0.'.PHP_EOL;
