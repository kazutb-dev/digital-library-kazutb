# Runtime Inspection

Date: 2026-05-13
Workspace: `C:\dev\kazutb-library`

## 1. Runtime Architecture Identified

The repository uses a Docker Compose runtime with:

1. Laravel app container (`app`) built from local `Dockerfile`.
2. PostgreSQL container (`postgres`) using `postgres:18`.
3. Frontend development container (`frontend-dev`) using `node:22` and Vite dev server.

Observed stack characteristics:

1. App service combines `nginx` + `php-fpm` under `supervisord`.
2. App entrypoint executes migrations automatically (`php artisan migrate --force`) and clears cache in live-sync mode.
3. Frontend service runs `npm install` when needed, then `npm run dev -- --host 0.0.0.0 --port 5173`.

## 2. Compose Services and Ports

From `docker-compose.yml`:

1. `app`:
    - Host port `80` -> container `80`
    - Depends on healthy `postgres`
    - Healthcheck hits `http://localhost/api/v1/catalog-db?limit=1`
2. `postgres`:
    - Host port `${POSTGRES_PORT:-5433}` -> container `5432`
    - Healthcheck via `pg_isready`
3. `frontend-dev`:
    - Host port `5173` -> container `5173`

## 3. Volumes and Mounts

1. `postgres_data` named volume for DB data.
2. Bind mount `./:/app:cached` for `app` and `frontend-dev`.
3. `frontend_node_modules` named volume mounted at `/app/node_modules`.

## 4. Env and Config Prerequisites

Inspected prerequisites:

1. `.env` exists in workspace.
2. Required compose env values resolved (`POSTGRES_USER`, `POSTGRES_PASSWORD`, `POSTGRES_DB`, `APP_KEY`).
3. `docker compose config --services` resolved successfully with `postgres`, `app`, `frontend-dev`.

No additional env file creation or modification was required for startup.

## 5. Database and Migration Assumptions

1. `DB_CONNECTION=pgsql`, DB host expected as `postgres` in container runtime.
2. Entry-point migration behavior means startup depends on DB readiness.
3. Session/cache drivers are database-backed by default in current env.

## 6. Background Services

No dedicated compose worker/scheduler services were found in `docker-compose.yml`.
The running runtime services are only `app`, `postgres`, and `frontend-dev`.
