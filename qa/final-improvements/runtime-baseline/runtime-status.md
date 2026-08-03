# Runtime Status

Date: 2026-05-13
Status: Running baseline established

## 1. Running Containers

From `docker compose ps` after startup:

1. `kazutb-library-app-1` (`kazutb-library-app`) — `Up ... (healthy)`
2. `kazutb-library-postgres-1` (`postgres:18`) — `Up ... (healthy)`
3. `kazutb-library-frontend-dev-1` (`node:22`) — `Up`

## 2. Exposed Ports

1. App: `0.0.0.0:80 -> 80/tcp`
2. Postgres: `0.0.0.0:5433 -> 5432/tcp`
3. Frontend dev server: `0.0.0.0:5173 -> 5173/tcp`

## 3. Application Reachability

HTTP baseline checks:

1. `GET http://localhost/` -> `200`
2. `GET http://localhost/api/v1/catalog-db?limit=1` -> `200`
3. `GET http://localhost/login` -> `200`

## 4. Database Reachability

DB readiness check result:

1. `pg_isready` returned `accepting connections`.

## 5. Migration Status

1. App entrypoint output included `Nothing to migrate`.
2. `php artisan migrate:status` listed existing migrations with `Ran` status.

## 6. Runtime Readiness Conclusion

Environment is ready for subsequent QA reruns from a known running Docker baseline.
