# Production stack topology

Last verified: 2026-08-20 UTC

- Project directory: `/home/admtutor/projects/library`
- Compose project: `library`
- Canonical Compose config: `/home/admtutor/projects/library/docker-compose.yml`
- Application service/container: `app` / `library-app-1`
- Database service/container: `postgres` / `library-postgres-1`
- Canonical Docker network: `library_default` (user-defined bridge)
- PostgreSQL service aliases: `postgres`, `library-postgres-1`
- Application service aliases: `app`, `library-app-1`
- Application resolver: Docker embedded DNS at `127.0.0.11`
- Application DB host contract: `postgres:5432` (never a container IP)
- Production database: `digital_library_recovered`
- Runtime DB role: `library_app` (DML and sequence usage only)
- Migration DB role: `library_migrator` (application-schema DDL only)
- PostgreSQL administrative/bootstrap role: `library_user` (not present in the
  web/worker container environment)
- Public host: https://elibrary.kaztbu.edu.kz

Never use a PostgreSQL superuser for application runtime. Runtime and migration
credentials are distinct and stored only in the mode-600 production environment
file. Future production migrations must use the profile-only migrator wrapper:

```text
php scripts/deploy/run-production-migrations.php --execute
```

## Runtime recovery rule

**DO NOT USE GENERIC FORCE-RECREATE ON PRODUCTION.**

DO NOT USE on production without first discovering the active stack and
network:

```text
docker compose up -d --force-recreate
```

Safe sequence:

1. Inspect the current runtime.
2. Identify the exact Compose project, config, service, and network.
3. Validate the intended configuration and service aliases.
4. Modify only the minimal affected component.
5. Restart or reload only that component, and only when required.
6. Verify Docker network membership, aliases, and embedded DNS.
7. Verify PostgreSQL TCP connectivity, Laravel queries, and database identity.
8. Verify HTTPS, redirect behavior, HSTS, and secure cookies.
9. Run read-only public, librarian, and admin smoke checks.

PostgreSQL must not be restarted to address an application hostname-resolution
failure unless evidence first proves that PostgreSQL itself is the cause.

## 2026-08-20 verification note

The inspected app and PostgreSQL endpoints share `library_default`, and the
PostgreSQL endpoint exposes the `postgres` alias. Resolution and TCP connection
from the app both passed. The app container was replaced alone at 07:57 UTC to
activate the least-privilege runtime credential, then once more at 08:06 UTC to
activate the verified backup-free image. Activations took two and three seconds.
PostgreSQL and the Docker network were not restarted or recreated.

PostgreSQL remained running from 2026-08-14 with restart count zero. Database
queries pass even though Docker reports its healthcheck as unhealthy because
healthcheck executions time out before starting. Treat that health status as
infrastructure debt, separately from database service availability.
