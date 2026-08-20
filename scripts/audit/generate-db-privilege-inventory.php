<?php

declare(strict_types=1);

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;

require dirname(__DIR__, 2).'/vendor/autoload.php';

$app = require dirname(__DIR__, 2).'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

$select = static fn (string $sql): array => array_map(
    static fn (object $row): array => (array) $row,
    DB::select($sql),
);

$inventory = [
    'generated_at_utc' => gmdate(DATE_ATOM),
    'database' => $select(<<<'SQL'
        select
            d.datname as database,
            pg_get_userbyid(d.datdba) as owner,
            current_user as current_user,
            pg_is_in_recovery() as in_recovery
        from pg_database d
        where d.datname = current_database()
        SQL)[0] ?? null,
    'role' => $select(<<<'SQL'
        select
            rolname,
            rolsuper,
            rolcreatedb,
            rolcreaterole,
            rolreplication,
            rolinherit,
            rolcanlogin,
            rolbypassrls
        from pg_roles
        where rolname = current_user
        SQL)[0] ?? null,
    'cluster_roles' => $select(<<<'SQL'
        select
            rolname,
            rolsuper,
            rolcreatedb,
            rolcreaterole,
            rolreplication,
            rolinherit,
            rolcanlogin,
            rolbypassrls
        from pg_roles
        where rolname !~ '^pg_'
        order by rolname
        SQL),
    'memberships' => $select(<<<'SQL'
        select
            member.rolname as member,
            parent.rolname as granted_role,
            membership.admin_option,
            membership.inherit_option,
            membership.set_option
        from pg_auth_members membership
        join pg_roles parent on parent.oid = membership.roleid
        join pg_roles member on member.oid = membership.member
        where member.rolname = current_user or parent.rolname = current_user
        order by member.rolname, parent.rolname
        SQL),
    'owner_summary' => $select(<<<'SQL'
        select object_type, owner, count(*)::int as object_count
        from (
            select 'schema'::text as object_type, pg_get_userbyid(n.nspowner) as owner
            from pg_namespace n
            where n.nspname not like 'pg_%' and n.nspname <> 'information_schema'
            union all
            select
                case c.relkind
                    when 'S' then 'sequence'
                    when 'v' then 'view'
                    when 'm' then 'materialized_view'
                    when 'f' then 'foreign_table'
                    when 'p' then 'partitioned_table'
                    else 'table'
                end,
                pg_get_userbyid(c.relowner)
            from pg_class c
            join pg_namespace n on n.oid = c.relnamespace
            where n.nspname not like 'pg_%'
              and n.nspname <> 'information_schema'
              and c.relkind in ('r', 'p', 'v', 'm', 'S', 'f')
            union all
            select 'function', pg_get_userbyid(p.proowner)
            from pg_proc p
            join pg_namespace n on n.oid = p.pronamespace
            where n.nspname not like 'pg_%' and n.nspname <> 'information_schema'
        ) owned
        group by object_type, owner
        order by object_type, owner
        SQL),
    'schemas' => $select(<<<'SQL'
        select
            n.nspname as schema,
            pg_get_userbyid(n.nspowner) as owner,
            coalesce(n.nspacl::text, '<default>') as acl
        from pg_namespace n
        where n.nspname !~ '^pg_' and n.nspname <> 'information_schema'
        order by n.nspname
        SQL),
    'functions' => $select(<<<'SQL'
        select
            n.nspname||'.'||p.proname||'('||pg_get_function_identity_arguments(p.oid)||')' as function,
            pg_get_userbyid(p.proowner) as owner,
            p.prosecdef as security_definer
        from pg_proc p
        join pg_namespace n on n.oid = p.pronamespace
        where n.nspname !~ '^pg_' and n.nspname <> 'information_schema'
        order by 1
        SQL),
    'object_privileges' => $select(<<<'SQL'
        select object, object_type as type, owner, privilege, grantee
        from (
            select
                quote_ident(n.nspname) as object,
                'schema'::text as object_type,
                pg_get_userbyid(n.nspowner) as owner,
                acl.privilege_type as privilege,
                case when acl.grantee = 0 then 'PUBLIC' else pg_get_userbyid(acl.grantee) end as grantee
            from pg_namespace n
            cross join lateral aclexplode(coalesce(n.nspacl, acldefault('n', n.nspowner))) acl
            where n.nspname not like 'pg_%' and n.nspname <> 'information_schema'

            union all

            select
                quote_ident(n.nspname)||'.'||quote_ident(c.relname),
                case c.relkind
                    when 'S' then 'sequence'
                    when 'v' then 'view'
                    when 'm' then 'materialized_view'
                    when 'f' then 'foreign_table'
                    when 'p' then 'partitioned_table'
                    else 'table'
                end,
                pg_get_userbyid(c.relowner),
                acl.privilege_type,
                case when acl.grantee = 0 then 'PUBLIC' else pg_get_userbyid(acl.grantee) end
            from pg_class c
            join pg_namespace n on n.oid = c.relnamespace
            cross join lateral aclexplode(coalesce(c.relacl, acldefault(case when c.relkind = 'S' then 'S'::"char" else 'r'::"char" end, c.relowner))) acl
            where n.nspname not like 'pg_%'
              and n.nspname <> 'information_schema'
              and c.relkind in ('r', 'p', 'v', 'm', 'S', 'f')

            union all

            select
                quote_ident(n.nspname)||'.'||quote_ident(p.proname)||'('||pg_get_function_identity_arguments(p.oid)||')',
                'function',
                pg_get_userbyid(p.proowner),
                acl.privilege_type,
                case when acl.grantee = 0 then 'PUBLIC' else pg_get_userbyid(acl.grantee) end
            from pg_proc p
            join pg_namespace n on n.oid = p.pronamespace
            cross join lateral aclexplode(coalesce(p.proacl, acldefault('f', p.proowner))) acl
            where n.nspname not like 'pg_%' and n.nspname <> 'information_schema'
        ) privileges
        order by type, object, grantee, privilege
        SQL),
    'database_privileges' => $select(<<<'SQL'
        select
            d.datname as object,
            'database'::text as type,
            pg_get_userbyid(d.datdba) as owner,
            acl.privilege_type as privilege,
            case when acl.grantee = 0 then 'PUBLIC' else pg_get_userbyid(acl.grantee) end as grantee
        from pg_database d
        cross join lateral aclexplode(coalesce(d.datacl, acldefault('d', d.datdba))) acl
        where d.datname = current_database()
        order by grantee, privilege
        SQL),
    'default_privileges' => $select(<<<'SQL'
        select
            pg_get_userbyid(d.defaclrole) as owner,
            coalesce(n.nspname, '*') as schema,
            case d.defaclobjtype
                when 'r' then 'table'
                when 'S' then 'sequence'
                when 'f' then 'function'
                when 'T' then 'type'
                when 'n' then 'schema'
                else d.defaclobjtype::text
            end as type,
            acl.privilege_type as privilege,
            case when acl.grantee = 0 then 'PUBLIC' else pg_get_userbyid(acl.grantee) end as grantee
        from pg_default_acl d
        left join pg_namespace n on n.oid = d.defaclnamespace
        cross join lateral aclexplode(d.defaclacl) acl
        order by owner, schema, type, grantee, privilege
        SQL),
    'dangerous_effective_capabilities' => $select(<<<'SQL'
        select role_name, pg_has_role(current_user, role_name, 'MEMBER') as effective
        from (values
            ('pg_read_server_files'),
            ('pg_write_server_files'),
            ('pg_execute_server_program'),
            ('pg_read_all_data'),
            ('pg_write_all_data')
        ) wanted(role_name)
        order by role_name
        SQL),
];

$output = dirname(__DIR__, 2).'/docs/runtime/DB-PRIVILEGE-INVENTORY.json';
$encoded = json_encode($inventory, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR).PHP_EOL;

if (file_put_contents($output, $encoded, LOCK_EX) === false) {
    fwrite(STDERR, "Unable to write privilege inventory.\n");
    exit(1);
}

fwrite(STDOUT, sprintf(
    "Wrote %s (%d object privilege rows; no business rows read).\n",
    $output,
    count($inventory['object_privileges']),
));
