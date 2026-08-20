<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
$pdo = DB::connection()->getPdo();

$database = $pdo->query(<<<'SQL'
    select
        current_database() as database,
        current_user as username,
        pg_is_in_recovery() as recovery,
        (select count(*) from bibliographic_records) as records,
        (select count(*) from book_copies) as copies,
        (select count(*) from book_copies where inventory_number is not null and length(btrim(inventory_number)) > 0) as inventory,
        (select count(*) from book_copies where barcode is not null and length(btrim(barcode)) > 0) as barcodes,
        (select count(*) from loans) as loans,
        (select count(*) from reservations) as reservations,
        (select count(*) from users) as users
    SQL)->fetch(PDO::FETCH_ASSOC);

$roles = $pdo->query(<<<'SQL'
    select
        rolname,
        rolsuper,
        rolcreatedb,
        rolcreaterole,
        rolreplication,
        rolbypassrls,
        rolcanlogin
    from pg_roles
    where rolname in ('library_app', 'library_migrator', 'library_user')
    order by rolname
    SQL)->fetchAll(PDO::FETCH_ASSOC);

$owners = $pdo->query(<<<'SQL'
    select object_type, owner, count(*)::int as object_count
    from (
        select 'schema'::text as object_type, pg_get_userbyid(nspowner) as owner
        from pg_namespace
        where nspname in ('public', 'app')
        union all
        select case when relkind = 'S' then 'sequence' else 'table' end, pg_get_userbyid(relowner)
        from pg_class c
        join pg_namespace n on n.oid = c.relnamespace
        where n.nspname in ('public', 'app') and relkind in ('r', 'p', 'S')
        union all
        select 'function', pg_get_userbyid(proowner)
        from pg_proc p
        join pg_namespace n on n.oid = p.pronamespace
        where n.nspname in ('public', 'app')
    ) objects
    group by object_type, owner
    order by object_type, owner
    SQL)->fetchAll(PDO::FETCH_ASSOC);

echo json_encode([
    'database' => $database,
    'roles' => $roles,
    'owners' => $owners,
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;
