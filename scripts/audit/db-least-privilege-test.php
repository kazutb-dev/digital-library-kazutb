<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

const PRODUCTION_DATABASE = 'digital_library_recovered';
const TEST_DATABASE = 'digital_library_privilege_20260820_test';
const NEGATIVE_DATABASE = 'digital_library_privilege_negative_20260820_test';
const RUNTIME_ROLE = 'library_runtime_privilege_test';
const MIGRATOR_ROLE = 'library_migrator_privilege_test';
const PROBE_ROLE = 'library_privilege_probe_test';

$action = $argv[1] ?? '';
if (! in_array($action, ['prepare', 'grant-runtime', 'negative'], true)) {
    fwrite(STDERR, "Usage: php scripts/audit/db-least-privilege-test.php prepare|grant-runtime|negative\n");
    exit(64);
}

foreach ([TEST_DATABASE, NEGATIVE_DATABASE] as $target) {
    if ($target === PRODUCTION_DATABASE || ! str_ends_with($target, '_test')) {
        throw new RuntimeException('Unsafe test database target: ABORT.');
    }
}

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$production = DB::selectOne('select current_database() as database, current_user as username, pg_is_in_recovery() as recovery');
if (($production->database ?? null) !== PRODUCTION_DATABASE || (bool) ($production->recovery ?? true)) {
    throw new RuntimeException('Production identity/recovery guard failed: ABORT.');
}

$host = (string) config('database.connections.pgsql.host');
$port = (string) config('database.connections.pgsql.port');
$username = (string) config('database.connections.pgsql.username');
$password = (string) config('database.connections.pgsql.password');
if ($host === '' || $port === '' || $username === '' || $password === '') {
    throw new RuntimeException('Administrative connection configuration is incomplete.');
}

$connect = static function (string $database) use ($host, $port, $username, $password): PDO {
    $pdo = new PDO(
        sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, $database),
        $username,
        $password,
        [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
    );
    $pdo->exec("set statement_timeout = '30s'");

    return $pdo;
};

$admin = $connect('postgres');

if ($action === 'prepare') {
    $existing = $admin->query("select datname from pg_database where datname in ('".TEST_DATABASE."', '".NEGATIVE_DATABASE."') order by datname")
        ->fetchAll(PDO::FETCH_COLUMN);
    fwrite(STDOUT, 'Validated isolated targets; existing target count: '.count($existing).PHP_EOL);

    foreach ([TEST_DATABASE, NEGATIVE_DATABASE] as $database) {
        $admin->exec("drop database if exists {$database} with (force)");
    }
    foreach ([RUNTIME_ROLE, MIGRATOR_ROLE, PROBE_ROLE] as $role) {
        $admin->exec("drop role if exists {$role}");
    }

    $admin->exec('create role '.RUNTIME_ROLE.' nologin nosuperuser nocreatedb nocreaterole noreplication nobypassrls inherit');
    $admin->exec('create role '.MIGRATOR_ROLE.' nologin nosuperuser nocreatedb nocreaterole noreplication nobypassrls inherit');
    $admin->exec('create role '.PROBE_ROLE.' nologin nosuperuser nocreatedb nocreaterole noreplication nobypassrls inherit');
    $admin->exec('create database '.TEST_DATABASE.' owner '.$username.' encoding '."'UTF8'");
    $admin->exec('create database '.NEGATIVE_DATABASE.' owner '.$username.' encoding '."'UTF8'");
    $admin->exec('grant connect, create on database '.TEST_DATABASE.' to '.MIGRATOR_ROLE);
    $admin->exec('grant connect on database '.TEST_DATABASE.' to '.RUNTIME_ROLE);

    $test = $connect(TEST_DATABASE);
    $test->exec('revoke create on schema public from public, '.RUNTIME_ROLE);
    $test->exec('grant usage, create on schema public to '.MIGRATOR_ROLE);

    fwrite(STDOUT, 'SAFE ISOLATED DATABASE PREPARED: '.TEST_DATABASE.PHP_EOL);
    exit(0);
}

if ($action === 'grant-runtime') {
    $test = $connect(TEST_DATABASE);
    $schemas = $test->query("select nspname from pg_namespace where nspname in ('public', 'app') order by nspname")
        ->fetchAll(PDO::FETCH_COLUMN);
    if ($schemas !== ['app', 'public']) {
        throw new RuntimeException('Expected application schemas were not created by migrations.');
    }

    $test->exec('revoke create on database '.TEST_DATABASE.' from '.RUNTIME_ROLE);
    $test->exec('revoke create on schema public, app from public, '.RUNTIME_ROLE);
    $test->exec('grant usage on schema public, app to '.RUNTIME_ROLE);
    $test->exec('grant select, insert, update, delete on all tables in schema public, app to '.RUNTIME_ROLE);
    $test->exec('grant usage, select on all sequences in schema public, app to '.RUNTIME_ROLE);
    $test->exec('revoke execute on all functions in schema public, app from public, '.RUNTIME_ROLE);

    foreach (['public', 'app'] as $schema) {
        $test->exec('alter default privileges for role '.MIGRATOR_ROLE.' in schema '.$schema.' grant select, insert, update, delete on tables to '.RUNTIME_ROLE);
        $test->exec('alter default privileges for role '.MIGRATOR_ROLE.' in schema '.$schema.' grant usage, select on sequences to '.RUNTIME_ROLE);
        $test->exec('alter default privileges for role '.MIGRATOR_ROLE.' in schema '.$schema.' revoke execute on functions from public, '.RUNTIME_ROLE);
    }

    $test->exec('set role '.MIGRATOR_ROLE);
    $test->exec('create table public.privilege_runtime_probe (id bigint generated by default as identity primary key, value text not null)');
    $test->exec("insert into public.privilege_runtime_probe (value) values ('probe')");
    $test->exec('reset role');

    fwrite(STDOUT, "RUNTIME GRANTS INSTALLED IN ISOLATED DATABASE.\n");
    exit(0);
}

$results = [];
$recordAttempt = static function (string $name, PDO $pdo, string $sql) use (&$results): void {
    try {
        $pdo->exec($sql);
        $results[$name] = ['denied' => false, 'sqlstate' => null];
    } catch (PDOException $exception) {
        $results[$name] = [
            'denied' => $exception->getCode() === '42501',
            'sqlstate' => $exception->getCode(),
        ];
    }
};

$test = $connect(TEST_DATABASE);
$test->exec('set role '.RUNTIME_ROLE);
$identity = $test->query('select current_database() as database, current_user as username')->fetch(PDO::FETCH_ASSOC);
$flags = $test->query('select rolsuper, rolcreatedb, rolcreaterole, rolreplication, rolbypassrls from pg_roles where rolname = current_user')->fetch(PDO::FETCH_ASSOC);
$recordAttempt('create_table', $test, 'create table public.runtime_should_not_create (id bigint)');
$recordAttempt('alter_table', $test, 'alter table public.privilege_runtime_probe add column forbidden text');
$recordAttempt('truncate_table', $test, 'truncate table public.privilege_runtime_probe');
$test->exec('reset role');

$admin->exec('set role '.RUNTIME_ROLE);
$recordAttempt('create_database', $admin, 'create database digital_library_runtime_create_probe_test');
$recordAttempt('create_role', $admin, 'create role library_runtime_create_probe_test nologin');
$recordAttempt('alter_role', $admin, 'alter role '.PROBE_ROLE.' login');
$recordAttempt('drop_database', $admin, 'drop database '.NEGATIVE_DATABASE);
$admin->exec('reset role');

// Cleanup only exact isolated probe objects if an unexpected permission allowed
// an operation. Production identifiers can never be constructed here.
$admin->exec('drop database if exists digital_library_runtime_create_probe_test with (force)');
$admin->exec('drop role if exists library_runtime_create_probe_test');
$negativeExists = (bool) $admin->query("select exists(select 1 from pg_database where datname = '".NEGATIVE_DATABASE."')")
    ->fetchColumn();
if (! $negativeExists) {
    $admin->exec('create database '.NEGATIVE_DATABASE.' owner '.$username.' encoding '."'UTF8'");
}

$allDenied = array_reduce(
    $results,
    static fn (bool $carry, array $result): bool => $carry && $result['denied'],
    true,
);
$allFlagsFalse = ! array_filter($flags, static fn (mixed $value): bool => (bool) $value);

$artifact = [
    'generated_at_utc' => gmdate(DATE_ATOM),
    'identity' => $identity,
    'role_flags' => $flags,
    'negative_attempts' => $results,
    'all_negative_attempts_denied' => $allDenied,
    'all_dangerous_flags_false' => $allFlagsFalse,
];

$path = dirname(__DIR__, 2).'/docs/runtime/DB-LEAST-PRIVILEGE-ISOLATED.json';
file_put_contents($path, json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL, LOCK_EX);

fwrite(STDOUT, json_encode($artifact, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL);
if (! $allDenied || ! $allFlagsFalse) {
    fwrite(STDERR, "LEAST-PRIVILEGE NEGATIVE GATE FAILED.\n");
    exit(1);
}

fwrite(STDOUT, "LEAST-PRIVILEGE NEGATIVE GATE: PASS\n");
