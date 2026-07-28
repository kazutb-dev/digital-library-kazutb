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

```bash
sudo bash scripts/dev/install-docker.sh   # installs docker + postgresql-client
```

### Initial deploy

```bash
# On server, as deploy user
mkdir -p /opt/library && cd /opt/library

# Copy app files (or git pull)
git clone git@github.com:almazmurat/library.git .

# Configure
cp .env.prod.example .env
# Edit .env: all REPLACE_WITH_* values

# Deploy
docker compose -f docker-compose.prod.yml up --build -d

# Run migrations
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
```

### Updating production

```bash
cd /opt/library
git pull origin main
docker compose -f docker-compose.prod.yml up --build -d
docker compose -f docker-compose.prod.yml exec app php artisan migrate --force
docker compose -f docker-compose.prod.yml exec app php artisan optimize
```

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
| `library_dev_node_modules` | DEV npm cache | Avoids host-container UID issues |

## Backup

```bash
# Backup PROD database
docker compose -f docker-compose.prod.yml exec postgres \
  pg_dump -U "$POSTGRES_USER" "$POSTGRES_DB" \
  | gzip > "backup-$(date +%Y%m%d).sql.gz"
```

## Health check

```bash
curl -s http://localhost/api/v1/catalog-db?limit=1 | python3 -m json.tool
```

Expected: HTTP 200 with `{"data": [...], "meta": {...}}`.

## Nginx & Supervisor

- Config: `docker/nginx.conf` — serves from `/app/public`, PHP-FPM on `:9000`
- Config: `docker/supervisord.conf` — manages nginx + php-fpm processes
- PHP config: `docker/php.ini`
- Entrypoint: `docker/entrypoint.sh` — runs migrations, warms caches, starts supervisor
