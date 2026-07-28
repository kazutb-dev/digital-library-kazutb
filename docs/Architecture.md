# Architecture Overview

## Stack summary

| Layer | Technology | Notes |
|-------|-----------|-------|
| Backend | Laravel 13, PHP 8.4 | Monolith, route closures for most endpoints |
| Frontend | Blade + React + Vite 8 | Blade = public surfaces, React = interactive widgets |
| Database | PostgreSQL 16 | Primary store; SQLite for testing |
| Runtime | Docker Compose | Separate dev and prod compose files |
| Testing | PHPUnit 12, Playwright | Unit/Feature + E2E smoke |
| CI/CD | GitHub Actions | `.github/workflows/ci.yml`, `deploy.yml` |

## Domain boundaries

```
┌──────────────────────────────────────────────────┐
│  Library Platform (this repo)                    │
│                                                  │
│  Public discovery  → /catalog /book/{isbn}       │
│  Reader account   → /account /reservations       │
│  Scientific repo  → /repository                 │
│  Librarian panel  → /librarian                  │
│  Admin panel      → /admin                      │
│  API              → /api/v1/*                   │
└────────────────┬─────────────────────────────────┘
                 │ auth tokens (Sanctum)
                 ▼
         ┌──────────────┐
         │  CRM         │
         │  (external)  │
         │  auth only   │
         └──────────────┘
```

## Key design decisions

- **Monolith-first** — all logic in one Laravel app; see ADR-001.md
- **CRM as auth boundary** — CRM issues tokens; library domain owns business logic
- **PostgreSQL as primary store** — no Redis in MVP; cache/session on DB
- **Vite 8 + React 19** — modern frontend toolchain; hot reload in dev via `docker-compose.dev.yml`
- **No multi-tenancy** — single-institution deployment

## Request flow

```
Browser → nginx:80 → php-fpm:9000 → Laravel Router → Controller / Closure
                                                      ↓
                                              Service Layer (app/Services/)
                                                      ↓
                                              PostgreSQL
```

## Directory map

```
app/Http/          → Controllers (Api/) and middleware
app/Models/        → Eloquent models
app/Services/      → Business logic (BibliographyFormatter, ExternalResourceService, etc.)
app/Services/Admin → Admin-specific services
app/Services/Library → Library domain services (catalog, circulation, etc.)
config/            → Per-feature config files
database/migrations → 18 migration files (see docs/Database.md)
resources/views/   → Blade templates
resources/js/      → React components + React Router
routes/web.php     → ~35 web routes (mostly closures)
routes/api.php     → ~101 API route definitions
```

## See also

- [Database.md](Database.md)
- [Deployment.md](Deployment.md)
- [Development.md](Development.md)
- [Release.md](Release.md)
