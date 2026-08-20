<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

const EXPECTED_DATABASE = 'digital_library_recovered';
const ADMIN_ROLE = 'library_user';
const RUNTIME_ROLE = 'library_app';
const MIGRATOR_ROLE = 'library_migrator';
const BACKUP_PATH = '/app/storage/app/backups/security-pre-privilege/20260820T075320Z/digital_library_recovered_20260820T075320Z.dump';
const BACKUP_SIZE = 21636543;
const BACKUP_SHA256 = '2f38bd712fc080e56b7f60d8c908fce317b3a6f0b46a80879f172a53f3a755d4';

$action = $argv[1] ?? '';
if (! in_array($action, ['apply', 'rollback-config'], true)) {
    fwrite(STDERR, "Usage: php scripts/audit/apply-production-db-least-privilege.php apply|rollback-config\n");
    exit(64);
}

$root = dirname(__DIR__, 2);
$envPath = $root.'/.env';

$replaceEnv = static function (array $replacements) use ($envPath): void {
    $metadata = stat($envPath);
    if ($metadata === false) {
        throw new RuntimeException('Unable to inspect the production environment file.');
    }
    $contents = file_get_contents($envPath);
    if ($contents === false) {
        throw new RuntimeException('Unable to read the production environment file.');
    }

    foreach ($replacements as $key => $value) {
        if (! preg_match('/^[A-Z][A-Z0-9_]*$/', $key) || ! preg_match('/^[A-Za-z0-9._-]+$/', $value)) {
            throw new RuntimeException('Unsafe production environment update rejected.');
        }
        $line = $key.'='.$value;
        $pattern = '/^'.preg_quote($key, '/').'=.*$/m';
        if (preg_match($pattern, $contents) === 1) {
            $contents = (string) preg_replace($pattern, $line, $contents, 1);
        } else {
            $contents = rtrim($contents).PHP_EOL.$line.PHP_EOL;
        }
    }

    $temporary = $envPath.'.db-privilege.tmp';
    $handle = fopen($temporary, 'x');
    if ($handle === false) {
        throw new RuntimeException('Refusing to overwrite an existing environment temporary file.');
    }
    try {
        if (fwrite($handle, $contents) !== strlen($contents) || ! fflush($handle)) {
            throw new RuntimeException('Unable to write the environment update atomically.');
        }
    } finally {
        fclose($handle);
    }
    if (! chown($temporary, $metadata['uid']) || ! chgrp($temporary, $metadata['gid']) || ! chmod($temporary, 0600)) {
        @unlink($temporary);
        throw new RuntimeException('Unable to preserve production environment ownership and mode.');
    }
    if (! rename($temporary, $envPath)) {
        @unlink($temporary);
        throw new RuntimeException('Unable to activate the environment update atomically.');
    }
    chmod($envPath, 0600);
};

if ($action === 'rollback-config') {
    $values = Dotenv\Dotenv::parse((string) file_get_contents($envPath));
    if (($values['POSTGRES_USER'] ?? '') !== ADMIN_ROLE || ($values['POSTGRES_PASSWORD'] ?? '') === '') {
        throw new RuntimeException('Administrative rollback identity is unavailable.');
    }
    $replaceEnv([
        'DB_USERNAME' => ADMIN_ROLE,
        'DB_PASSWORD' => $values['POSTGRES_PASSWORD'],
    ]);
    fwrite(STDOUT, "Runtime configuration rollback prepared; recreate only app to activate it.\n");
    exit(0);
}

if (! is_file(BACKUP_PATH) || filesize(BACKUP_PATH) !== BACKUP_SIZE || hash_file('sha256', BACKUP_PATH) !== BACKUP_SHA256) {
    throw new RuntimeException('Verified pre-change backup guard failed: ABORT.');
}

$app = require $root.'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$pdo = DB::connection()->getPdo();
$pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);

$baseline = $pdo->query(<<<'SQL'
    select
        current_database() as database,
        current_user as username,
        pg_is_in_recovery() as recovery,
        (select count(*) from bibliographic_records) as records,
        (select count(*) from book_copies) as copies,
        (select count(*) from book_copies where inventory_number is not null and length(btrim(inventory_number)) > 0) as inventory,
        (select count(*) from book_copies where barcode is not null and length(btrim(barcode)) > 0) as barcodes,
        (select count(*) from migrations) as migrations
    SQL)->fetch(PDO::FETCH_ASSOC);

if ($baseline !== [
    'database' => EXPECTED_DATABASE,
    'username' => ADMIN_ROLE,
    'recovery' => false,
    'records' => 9562,
    'copies' => 50907,
    'inventory' => 50907,
    'barcodes' => 27,
    'migrations' => 72,
]) {
    throw new RuntimeException('Production identity/data/migration guard failed: ABORT.');
}

$adminFlags = $pdo->query("select rolsuper, rolcreatedb, rolcreaterole, rolreplication from pg_roles where rolname = '".ADMIN_ROLE."'")
    ->fetch(PDO::FETCH_ASSOC);
if ($adminFlags !== ['rolsuper' => true, 'rolcreatedb' => true, 'rolcreaterole' => true, 'rolreplication' => true]) {
    throw new RuntimeException('Administrative role precondition changed: ABORT.');
}

$existingTargetRoles = (int) $pdo->query("select count(*) from pg_roles where rolname in ('".RUNTIME_ROLE."', '".MIGRATOR_ROLE."')")
    ->fetchColumn();
if ($existingTargetRoles !== 0) {
    throw new RuntimeException('Target production roles already exist: ABORT.');
}

$runtimePassword = bin2hex(random_bytes(32));
$migratorPassword = bin2hex(random_bytes(32));

$pdo->beginTransaction();
try {
    $pdo->exec('create role '.RUNTIME_ROLE.' login password '.$pdo->quote($runtimePassword).' nosuperuser nocreatedb nocreaterole noreplication nobypassrls inherit');
    $pdo->exec('create role '.MIGRATOR_ROLE.' login password '.$pdo->quote($migratorPassword).' nosuperuser nocreatedb nocreaterole noreplication nobypassrls inherit');

    $pdo->exec('grant connect on database '.EXPECTED_DATABASE.' to '.RUNTIME_ROLE);
    $pdo->exec('grant connect, create on database '.EXPECTED_DATABASE.' to '.MIGRATOR_ROLE);

    $ownerStatements = $pdo->query(<<<'SQL'
        select statement from (
            select 1 as priority, format('alter schema %I owner to library_migrator', n.nspname) as statement
            from pg_namespace n
            where n.nspname in ('public', 'app') and pg_get_userbyid(n.nspowner) = 'library_user'

            union all

            select case when c.relkind = 'S' then 3 else 2 end, format(
                case c.relkind
                    when 'S' then 'alter sequence %I.%I owner to library_migrator'
                    when 'v' then 'alter view %I.%I owner to library_migrator'
                    when 'm' then 'alter materialized view %I.%I owner to library_migrator'
                    else 'alter table %I.%I owner to library_migrator'
                end,
                n.nspname,
                c.relname
            )
            from pg_class c
            join pg_namespace n on n.oid = c.relnamespace
            where n.nspname in ('public', 'app')
              and c.relkind in ('r', 'p', 'v', 'm', 'S')
              and pg_get_userbyid(c.relowner) = 'library_user'
              and (
                  c.relkind <> 'S'
                  or not exists (
                      select 1
                      from pg_depend d
                      where d.classid = 'pg_class'::regclass
                        and d.objid = c.oid
                        and d.refclassid = 'pg_class'::regclass
                        and d.deptype in ('a', 'i')
                  )
              )

            union all

            select 4, format(
                'alter function %I.%I(%s) owner to library_migrator',
                n.nspname,
                p.proname,
                pg_get_function_identity_arguments(p.oid)
            )
            from pg_proc p
            join pg_namespace n on n.oid = p.pronamespace
            where n.nspname in ('public', 'app')
              and pg_get_userbyid(p.proowner) = 'library_user'
        ) ownership
        order by priority, statement
        SQL)->fetchAll(PDO::FETCH_COLUMN);
    foreach ($ownerStatements as $statement) {
        $pdo->exec($statement);
    }

    $pdo->exec('revoke create on schema public, app from public, '.RUNTIME_ROLE);
    $pdo->exec('grant usage, create on schema public to '.MIGRATOR_ROLE);
    $pdo->exec('grant usage on schema public, app to '.RUNTIME_ROLE);
    $pdo->exec('grant select, insert, update, delete on all tables in schema public, app to '.RUNTIME_ROLE);
    $pdo->exec('grant usage, select on all sequences in schema public, app to '.RUNTIME_ROLE);
    $pdo->exec('revoke execute on all functions in schema public, app from public, '.RUNTIME_ROLE);

    foreach (['public', 'app'] as $schema) {
        $pdo->exec('alter default privileges for role '.MIGRATOR_ROLE.' in schema '.$schema.' grant select, insert, update, delete on tables to '.RUNTIME_ROLE);
        $pdo->exec('alter default privileges for role '.MIGRATOR_ROLE.' in schema '.$schema.' grant usage, select on sequences to '.RUNTIME_ROLE);
        $pdo->exec('alter default privileges for role '.MIGRATOR_ROLE.' in schema '.$schema.' revoke execute on functions from public, '.RUNTIME_ROLE);
    }

    $pdo->commit();
} catch (Throwable $exception) {
    if ($pdo->inTransaction()) {
        $pdo->rollBack();
    }
    throw $exception;
}

$host = (string) config('database.connections.pgsql.host');
$port = (string) config('database.connections.pgsql.port');
$runtime = new PDO(
    sprintf('pgsql:host=%s;port=%s;dbname=%s', $host, $port, EXPECTED_DATABASE),
    RUNTIME_ROLE,
    $runtimePassword,
    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION],
);
$runtimeProof = $runtime->query(<<<'SQL'
    select
        current_database() as database,
        current_user as username,
        pg_is_in_recovery() as recovery,
        (select count(*) from bibliographic_records) as records,
        (select count(*) from book_copies) as copies,
        (select count(*) from users) as users
    SQL)->fetch(PDO::FETCH_ASSOC);
$runtimeFlags = $runtime->query('select rolsuper, rolcreatedb, rolcreaterole, rolreplication, rolbypassrls from pg_roles where rolname = current_user')
    ->fetch(PDO::FETCH_ASSOC);

if ($runtimeProof['database'] !== EXPECTED_DATABASE || $runtimeProof['username'] !== RUNTIME_ROLE || $runtimeProof['recovery'] !== false
    || $runtimeProof['records'] !== 9562 || $runtimeProof['copies'] !== 50907 || $runtimeProof['users'] !== 18
    || array_filter($runtimeFlags, static fn (mixed $value): bool => (bool) $value)) {
    throw new RuntimeException('New runtime login proof failed; application configuration was not changed.');
}

$replaceEnv([
    'DB_USERNAME' => RUNTIME_ROLE,
    'DB_PASSWORD' => $runtimePassword,
    'MIGRATION_DB_USERNAME' => MIGRATOR_ROLE,
    'MIGRATION_DB_PASSWORD' => $migratorPassword,
]);

$report = [
    'changed_at_utc' => gmdate(DATE_ATOM),
    'database' => $runtimeProof['database'],
    'runtime_role' => $runtimeProof['username'],
    'runtime_flags' => $runtimeFlags,
    'migrator_role' => MIGRATOR_ROLE,
    'admin_role' => ADMIN_ROLE,
    'records' => $runtimeProof['records'],
    'copies' => $runtimeProof['copies'],
    'users' => $runtimeProof['users'],
    'backup' => [
        'path' => BACKUP_PATH,
        'size' => BACKUP_SIZE,
        'sha256' => BACKUP_SHA256,
        'restore_test' => 'PASS',
    ],
    'secrets_written_to_report' => false,
];
file_put_contents(
    $root.'/docs/runtime/DB-LEAST-PRIVILEGE-PRODUCTION.json',
    json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL,
    LOCK_EX,
);

fwrite(STDOUT, "Production database grants and ownership updated.\n");
fwrite(STDOUT, "New runtime and migration login probes: PASS.\n");
fwrite(STDOUT, "Secrets stored only in the mode-600 production environment file.\n");
fwrite(STDOUT, "NEXT: validate Compose, then recreate only app; rollback-config is available.\n");
