<?php

declare(strict_types=1);

use Dotenv\Dotenv;

require dirname(__DIR__, 2).'/vendor/autoload.php';

if (($argv[1] ?? '') !== '--execute') {
    fwrite(STDERR, "Refusing production migration without --execute.\n");
    exit(64);
}

$root = dirname(__DIR__, 2);
$values = Dotenv::parse((string) file_get_contents($root.'/.env'));

$required = [
    'MIGRATION_DB_USERNAME',
    'MIGRATION_DB_PASSWORD',
    'DB_DATABASE',
    'DB_HOST',
];
foreach ($required as $key) {
    if (($values[$key] ?? '') === '') {
        throw new RuntimeException("Missing required production migration setting: {$key}");
    }
}
if ($values['DB_DATABASE'] !== 'digital_library_recovered' || $values['DB_HOST'] !== 'postgres') {
    throw new RuntimeException('Production migration database identity/topology guard failed.');
}
if (($values['DB_USERNAME'] ?? '') === $values['MIGRATION_DB_USERNAME']) {
    throw new RuntimeException('Runtime and migration identities must differ.');
}

$environment = getenv();
if (! is_array($environment)) {
    $environment = [];
}

$baseCommand = [
    'docker', 'compose',
    '-f', $root.'/docker-compose.yml',
    '--profile', 'maintenance',
    'run', '--rm', '--no-deps',
    '--entrypoint', 'php',
];

$run = static function (array $command) use ($root, $environment): int {
    $process = proc_open($command, [STDIN, STDOUT, STDERR], $pipes, $root, $environment);
    if (! is_resource($process)) {
        throw new RuntimeException('Unable to start the isolated migration container.');
    }

    return proc_close($process);
};

$probeCode = <<<'PHP'
require '/app/vendor/autoload.php';
$app = require '/app/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
$row = Illuminate\Support\Facades\DB::selectOne(
    "select current_database() database, current_user username, rolsuper, rolcreatedb, rolcreaterole, rolreplication from pg_roles where rolname = current_user"
);
if ($row->database !== 'digital_library_recovered'
    || $row->username !== 'library_migrator'
    || $row->rolsuper
    || $row->rolcreatedb
    || $row->rolcreaterole
    || $row->rolreplication) {
    fwrite(STDERR, "Migration identity/privilege preflight failed.\n");
    exit(65);
}
echo json_encode($row, JSON_UNESCAPED_SLASHES), PHP_EOL;
PHP;

$probeExit = $run([...$baseCommand, 'migrator', '-r', $probeCode]);
if ($probeExit !== 0) {
    exit($probeExit);
}

exit($run([...$baseCommand, 'migrator', 'artisan', 'migrate', '--force', '--no-interaction']));
