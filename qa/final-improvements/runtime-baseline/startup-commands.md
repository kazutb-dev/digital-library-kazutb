# Startup Commands

Date: 2026-05-13

The commands below were executed from `C:\dev\kazutb-library`.

## 1. Runtime Inspection Commands

```powershell
Test-Path .env
docker compose config --services
docker compose ps
```

## 2. Stack Startup Command

```powershell
docker compose up --build -d app frontend-dev
```

## 3. Runtime Verification Commands

```powershell
docker compose ps
docker compose logs --no-color --tail=120 app
docker compose logs --no-color --tail=80 frontend-dev
```

```powershell
docker compose exec -T postgres pg_isready -U "$env:POSTGRES_USER" -d "$env:POSTGRES_DB"
docker compose exec -T app php artisan migrate:status
```

```powershell
curl.exe -s -o NUL -w "APP_ROOT_HTTP=%{http_code}\n" http://localhost/
curl.exe -s -o NUL -w "APP_CATALOG_API_HTTP=%{http_code}\n" "http://localhost/api/v1/catalog-db?limit=1"
curl.exe -s -o NUL -w "APP_LOGIN_HTTP=%{http_code}\n" http://localhost/login
```

## 4. Verification Summary of Command Outputs

1. Services resolved: `postgres`, `app`, `frontend-dev`.
2. Containers running with health:
    - `app` healthy
    - `postgres` healthy
    - `frontend-dev` running
3. DB readiness: `/var/run/postgresql:5432 - accepting connections`.
4. Migrations: `Nothing to migrate`; all listed migrations in `Ran` status.
5. HTTP checks:
    - `APP_ROOT_HTTP=200`
    - `APP_CATALOG_API_HTTP=200`
    - `APP_LOGIN_HTTP=200`
