# Deployment Guide

## Environments

| Environment | Docker Compose File | Branch | URL |
|-------------|-------------------|--------|-----|
| **Dev** | `docker-compose.dev.yml` | `develop` / `feature/*` | http://localhost:8080 |
| **Production** | `docker-compose.prod.yml` | `main` (tagged release) | https://library.kazutb.kz |

## DEV deployment (local)

```bash
# Configure
cp .env.dev.example .env
# Edit .env: POSTGRES_PASSWORD, APP_KEY

# Start
docker compose -f docker-compose.dev.yml up --build -d

# Migrate
docker compose -f docker-compose.dev.yml exec app php artisan migrate

# Follow logs
docker compose -f docker-compose.dev.yml logs -f app frontend-dev
```

Ports:
- App (nginx): http://localhost:**8080**
- Vite (HMR): http://localhost:**5173**
- PostgreSQL: localhost:**5432** (host-bound, DEV only)

## PROD deployment (server)

### Prerequisites on server

Install Docker Engine with the Compose v2 plugin and the PostgreSQL client from
the supported packages for the server OS. This repository does not ship or run
a privileged host-installer script.

### Initial deploy

```bash
# On server, as deploy user
mkdir -p /opt/library && cd /opt/library

# Copy app files (or git pull)
git clone git@github.com:almazmurat/library.git .

# Configure
cp .env.prod.example .env
# Edit .env: all REPLACE_WITH_* values

# Build the candidate image. Do not run migrations from the runtime app role.
docker compose -f docker-compose.prod.yml build
```

Before the first activation, discover and verify the actual Compose project,
database identity and network topology. Follow
[`docs/runtime/PRODUCTION-STACK.md`](runtime/PRODUCTION-STACK.md) and
[`docs/runtime/DB-LEAST-PRIVILEGE-MAINTENANCE.md`](runtime/DB-LEAST-PRIVILEGE-MAINTENANCE.md).
Production migrations are a separate approved operation and must use the
guarded `library_migrator` profile:

```bash
php scripts/deploy/run-production-migrations.php --execute
```

Only after the migration preflight and rollback checks pass should the exact
affected service be activated. Do not use the runtime `app` container for DDL.

### Updating production

```bash
cd /opt/library
git pull origin main
make prod-deploy
```

`make prod-deploy` builds the candidate image only. Verify a restorable backup,
schema compatibility, the effective runtime database and the rollback plan;
then run the guarded migration wrapper above if the release has pending
migrations. Activate only the intended application service using the discovered
production Compose configuration. The container entrypoint performs read-only
schema compatibility checks and warms Laravel caches.

Or via Makefile:

```bash
make prod-deploy
```

### Automated deploy via GitHub Actions

See `docs/Release.md` for tag-based deploy workflow.

Configure secrets in GitHub → Settings → Secrets:
- `SERVER_HOST`, `SERVER_USER`, `SERVER_SSH_KEY`, `SERVER_PATH`

## Container services

| Service | Image | Purpose |
|---------|-------|---------|
| `postgres` | postgres:16 | Primary database |
| `app` | `./Dockerfile` (PHP 8.4 + nginx + supervisor) | Laravel app |
| `frontend-dev` | node:22 | Vite dev server (DEV only) |

## Data volumes

| Volume | Purpose | Notes |
|--------|---------|-------|
| `library_dev_postgres_data` | DEV DB data | Docker managed |
| `library_prod_postgres_data` | PROD DB data | Docker managed, back up regularly |
| `library_prod_repository_private_data` | Private repository PDFs and version history | Persistent across image deploys; back up with the database |
| `library_dev_node_modules` | DEV npm cache | Avoids host-container UID issues |

## Backup

```bash
# Create a custom-format dump, verify its TOC and checksum, restore it into a
# unique temporary *_test database, and validate core counts and foreign keys.
bash scripts/backup-verify.sh digital_library_recovered

# Backup private repository files (restore together with the matching DB dump)
docker run --rm \
  -v library_prod_repository_private_data:/data:ro \
  -v "$PWD":/backup \
  alpine tar -czf "/backup/repository-private-$(date +%Y%m%d).tar.gz" -C /data .
```

The database backup command refuses `*_test` sources and existing artifacts.
Its restore-verification database is removed on exit; backup and verification
artifacts are retained. Configure and verify off-site storage separately before
treating the backup as operationally complete.

## Health check

```bash
curl -s http://localhost/api/v1/catalog-db?limit=1 | python3 -m json.tool
```

Expected: HTTP 200 with `{"data": [...], "meta": {...}}`.

## Nginx & Supervisor

- Config: `docker/nginx.conf` — serves from `/app/public`, PHP-FPM on `:9000`
- Config: `docker/supervisord.conf` — manages nginx + php-fpm processes
- PHP config: `docker/php.ini`
- Entrypoint: `docker/entrypoint.sh` — checks runtime/schema compatibility without
  changing the database, then warms caches and starts supervisor. Migrations are
  a separate, explicitly approved deployment operation.
