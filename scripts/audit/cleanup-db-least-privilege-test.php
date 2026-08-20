<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require dirname(__DIR__, 2).'/vendor/autoload.php';

const PRODUCTION_DATABASE = 'digital_library_recovered';
const TEST_DATABASES = [
    'digital_library_privilege_20260820_test',
    'digital_library_privilege_negative_20260820_test',
];
const TEST_ROLES = [
    'library_runtime_privilege_test',
    'library_migrator_privilege_test',
    'library_privilege_probe_test',
];

foreach (TEST_DATABASES as $database) {
    if ($database === PRODUCTION_DATABASE || ! str_ends_with($database, '_test')) {
        throw new RuntimeException('Unsafe cleanup database target: ABORT.');
    }
}
foreach (TEST_ROLES as $role) {
    if (! str_ends_with($role, '_test')) {
        throw new RuntimeException('Unsafe cleanup role target: ABORT.');
    }
}

$root = dirname(__DIR__, 2);
$values = Dotenv::parse((string) file_get_contents($root.'/.env'));
$username = (string) ($values['POSTGRES_USER'] ?? '');
$password = (string) ($values['POSTGRES_PASSWORD'] ?? '');
$port = (string) ($values['POSTGRES_PORT'] ?? '5432');
if ($username === '' || $password === '' || ! ctype_digit($port)) {
    throw new RuntimeException('Administrative cleanup connection is unavailable.');
}

$pdo = new PDO(
    sprintf('pgsql:host=127.0.0.1;port=%s;dbname=postgres', $port),
    $username,
    $password,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
$pdo->exec("set statement_timeout = '30s'");

$productionExists = (bool) $pdo->query("select exists(select 1 from pg_database where datname = '".PRODUCTION_DATABASE."')")
    ->fetchColumn();
if (! $productionExists) {
    throw new RuntimeException('Production identity guard failed: ABORT.');
}

$quotedDatabases = implode(', ', array_map($pdo->quote(...), TEST_DATABASES));
$quotedRoles = implode(', ', array_map($pdo->quote(...), TEST_ROLES));
$existingDatabases = $pdo->query("select datname from pg_database where datname in ({$quotedDatabases}) order by datname")
    ->fetchAll(PDO::FETCH_COLUMN);
$existingRoles = $pdo->query("select rolname from pg_roles where rolname in ({$quotedRoles}) order by rolname")
    ->fetchAll(PDO::FETCH_COLUMN);

echo 'Validated cleanup databases: '.implode(', ', $existingDatabases).PHP_EOL;
echo 'Validated cleanup roles: '.implode(', ', $existingRoles).PHP_EOL;

foreach (TEST_DATABASES as $database) {
    $pdo->exec("drop database if exists {$database} with (force)");
}
foreach (TEST_ROLES as $role) {
    $pdo->exec("drop role if exists {$role}");
}

$remainingDatabases = (int) $pdo->query("select count(*) from pg_database where datname in ({$quotedDatabases})")
    ->fetchColumn();
$remainingRoles = (int) $pdo->query("select count(*) from pg_roles where rolname in ({$quotedRoles})")
    ->fetchColumn();
if ($remainingDatabases !== 0 || $remainingRoles !== 0) {
    throw new RuntimeException('Isolated cleanup verification failed.');
}

echo "ISOLATED DATABASE/ROLE CLEANUP: PASS\n";
