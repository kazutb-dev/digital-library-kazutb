# Production PostgreSQL least-privilege maintenance

Verified: 2026-08-20 UTC. Passwords are deliberately excluded.

## Current state

- Database: `digital_library_recovered`
- Runtime/migration/admin identity: `library_user`
- `library_user`: SUPERUSER, CREATEDB, CREATEROLE, REPLICATION, BYPASSRLS
- Database owner: `library_user`
- Application objects owned by `library_user`: 109 tables, 95 sequences,
  2 functions, and the `app` schema
- Current migration path: `artisan migrate --force` from the application
  container with runtime credentials

## Target state

- `library_app`: LOGIN runtime role; DML on application tables, sequence
  usage/select, schema usage; no schema CREATE, TRUNCATE, DDL, global role or
  replication privileges
- `library_migrator`: LOGIN deployment-only role; owns application schemas and
  objects and has CREATE only inside `digital_library_recovered`; no SUPERUSER,
  CREATEDB, CREATEROLE, REPLICATION or BYPASSRLS
- `library_user`: existing PostgreSQL bootstrap/admin and database owner, kept
  outside web/worker containers and available for controlled rollback

The effective SQL is generated over exact catalog-discovered objects and is
equivalent to:

```sql
CREATE ROLE library_app LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS;
CREATE ROLE library_migrator LOGIN NOSUPERUSER NOCREATEDB NOCREATEROLE NOREPLICATION NOBYPASSRLS;

GRANT CONNECT ON DATABASE digital_library_recovered TO library_app;
GRANT CONNECT, CREATE ON DATABASE digital_library_recovered TO library_migrator;

ALTER SCHEMA app OWNER TO library_migrator;
ALTER TABLE <each public/app application table> OWNER TO library_migrator;
ALTER SEQUENCE <each public/app application sequence> OWNER TO library_migrator;
ALTER FUNCTION <each public/app application function> OWNER TO library_migrator;

REVOKE CREATE ON SCHEMA public, app FROM PUBLIC, library_app;
GRANT USAGE ON SCHEMA public, app TO library_app;
GRANT SELECT, INSERT, UPDATE, DELETE ON ALL TABLES IN SCHEMA public, app TO library_app;
GRANT USAGE, SELECT ON ALL SEQUENCES IN SCHEMA public, app TO library_app;
REVOKE EXECUTE ON ALL FUNCTIONS IN SCHEMA public, app FROM PUBLIC, library_app;
```

Equivalent default privileges are installed for future objects created by
`library_migrator`. `GRANT ALL`, CREATEDB, CREATEROLE and REPLICATION are never
used.

## Isolated proof

- Database: `digital_library_privilege_20260820_test`
- All 72 migrations from an empty PostgreSQL database: PASS as
  `library_migrator_privilege_test`
- Targeted rollback/reapply of the final migration: PASS
- Runtime representative suites: PASS; no DB permission errors
- Queue worker, scheduler boot and Data Quality write: PASS
- CREATE/ALTER/TRUNCATE, CREATE/DROP DATABASE and CREATE/ALTER ROLE: all denied
  with SQLSTATE 42501

## Production activation and rollback

1. Verify the pre-change backup and baseline.
2. Apply catalog-derived ownership/grants and generate distinct credentials in
   memory.
3. Store runtime/migration credentials only in the mode-600 production `.env`.
4. Validate `docker compose config --quiet`.
5. Activate with `docker compose up -d --no-deps app` only. Do not recreate the
   PostgreSQL service or the whole stack.
6. Verify DB identity, role flags, web routes, workers, scheduler and logs.

Expected application downtime is limited to the single app-container
replacement, normally several seconds. PostgreSQL downtime is zero.

Fast rollback does not require ownership reversal: restore runtime configuration
to `library_user` using the guarded `rollback-config` action and replace only
the app container. Because `library_user` remains the isolated cluster admin,
it can operate objects owned by `library_migrator` during rollback.

Future production migrations must use:

```text
php scripts/deploy/run-production-migrations.php --execute
```

The wrapper starts the profile-only `migrator` service with a separate config
cache path. Compose resolves `MIGRATION_DB_*` from the mode-600 production
environment file; secret values are not CLI arguments and are never present in
the long-running app container. The wrapper fails before migration unless its
identity preflight reports `library_migrator` with all dangerous flags false.
Never run migrations through the long-running web/worker runtime identity.
