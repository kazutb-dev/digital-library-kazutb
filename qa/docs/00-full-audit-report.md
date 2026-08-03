# KazUTB Digital Library — Full Technical & QA Audit Report

> **Version:** 1.0
> **Date:** 2026-05-13
> **Repository:** `kazutb-dev/digital-library-kazutb`
> **Branch audited:** `wave2-wip-before-openclaw`
> **Auditor:** (your name)
> **Purpose:** Foundation for Advanced QA baseline through experimental QA program layers + Research Paper

---

## TABLE OF CONTENTS

1. [Executive Summary](#1-executive-summary)
2. [System Understanding (Deep)](#2-system-understanding-deep)
3. [Repository Inventory](#3-repository-inventory)
4. [Runbook](#4-runbook)
5. [Risk-Based QA Foundation](#5-risk-based-qa-foundation)
6. [Test Strategy Draft v0.1](#6-test-strategy-draft-v01)
7. [Metrics Baseline for Research](#7-metrics-baseline-for-research)
8. [QA Roadmap (Week-by-Week)](#8-qa-roadmap-week-by-week)
9. [Recommended QA Folder Structure](#9-recommended-qa-folder-structure)
10. [Ready Artifacts](#10-ready-artifacts)
11. [Gap Analysis](#11-gap-analysis)
12. [Final Checklist](#12-final-checklist)
13. [Questions for Team](#13-questions-for-team)

---

## 1. EXECUTIVE SUMMARY

### 1.1 What Is This System?

The **KazUTB Digital Library Platform** is a production-grade, full-domain university library management system built for Kazakh University of Technology and Business (KazUTB). It replaces a legacy MARC-SQL system (which only handled cataloging) with a modern, web-based platform serving:

- **Students and staff** (members): catalog search, book reservations, loan history, shortlist, digital materials
- **Librarians**: circulation desk (issue/return/renew), copy management, catalog enrichment, review queues, imports, messaging
- **Administrators**: user/role management, news, external resources, governance, analytics/reports
- **External integrations**: CRM authentication, reservation and document management APIs

**Tech stack:**

| Layer | Technology |
|-------|-----------|
| Backend | Laravel 13, PHP 8.4 |
| Frontend | React 19 + Vite 8 + TailwindCSS 4 (SPA-in-Blade hybrid) |
| Database | PostgreSQL 18 (production); SQLite :memory: (tests) |
| Auth | External CRM API + Laravel Sanctum sessions |
| Queue | Database queue |
| Cache/Session | Database (prod), Array (tests) |
| Mail | Log driver (dev/test) |
| Storage | Local filesystem |
| AI Integration | 21st-dev agent bridge |
| CI | GitHub Actions (2 jobs: backend + browser smoke) |
| E2E | Playwright 1.59 (Chromium) |

### 1.2 System Complexity

**Complexity level: HIGH**

- 7 distinct user roles with fine-grained RBAC
- 8 custom middleware guards
- ~45 API controllers across 3 route groups (public, internal, integration)
- ~44 service classes in Library + Admin modules
- Integration boundary with external systems (CRM auth, external reservation API, document management API)
- 19 database migrations (evolving schema)
- Digital materials streaming with access control
- Full loan/circulation lifecycle (reserve → approve → checkout → renew → return → retire)

### 1.3 Key Risks

| Risk | Severity |
|------|---------|
| Authentication bridge failure (CRM → app) breaks all logins | CRITICAL |
| Circulation state machine inconsistencies (double-checkout, orphan loans) | HIGH |
| Role/permission bypass (accessing librarian/admin as member) | HIGH |
| Integration API data contract drift | HIGH |
| Data integrity on legacy migration (bad/null fields) | MEDIUM-HIGH |
| No mutation testing baseline (Infection configured but not in CI gate) | MEDIUM |
| SQLite-in-memory tests may not catch PostgreSQL-specific behavior | MEDIUM |

### 1.4 QA-Ready Status

**Overall QA Readiness: 3.5 / 5** — Significantly above average for a project at this stage.

**Rationale:**

| Dimension | Score | Notes |
|-----------|-------|-------|
| Test infrastructure exists | 4/5 | PHPUnit + Playwright + CI pipeline all present |
| Test coverage breadth | 4/5 | ~112 PHP test files; 6 E2E spec files covering critical flows |
| Test data / seeding | 2/5 | Only UserFactory + DigitalMaterialSeeder; no rich fixture set |
| CI quality gates | 3/5 | Gates run but no mutation testing, no performance gates |
| Documentation | 4/5 | Excellent PROJECT_CONTEXT.md, clear scripts |
| Environment reproducibility | 4/5 | Docker Compose with health checks; clear .env.example |
| Coverage reporting | 3/5 | Clover generated in CI but threshold is minimal (4%) |
| Non-functional testing | 1/5 | No performance, no accessibility, no security scanning beyond Gitleaks |

---

## 2. SYSTEM UNDERSTANDING (DEEP)

### 2.1 Architecture Map

```
┌─────────────────────────────────────────────────────────────────┐
│  KazUTB Digital Library Platform — Architecture Overview        │
│                                                                 │
│  ┌──────────────┐    ┌──────────────────────────────────────┐  │
│  │  React SPA   │◄──►│  Laravel 13 API (PHP 8.4)            │  │
│  │  (Vite/TW4)  │    │  Monolith with service layer         │  │
│  │  Port 5173   │    │  Port 80 (nginx + php-fpm)           │  │
│  └──────────────┘    └────────────┬─────────────────────────┘  │
│                                   │                             │
│              ┌────────────────────┼────────────────────┐       │
│              │                    │                    │       │
│    ┌─────────▼──────┐  ┌──────────▼──────┐  ┌────────▼─────┐ │
│    │  PostgreSQL 18  │  │  External CRM   │  │  AI Agent    │ │
│    │  (main DB)      │  │  (auth only)    │  │  21st-dev    │ │
│    └────────────────┘  └─────────────────┘  └──────────────┘ │
└─────────────────────────────────────────────────────────────────┘
```

**Application type:** Laravel monolith with embedded React SPA frontend. API-first backend. Single codebase — not microservices.

### 2.2 Application Layers

| Layer | Location | Description |
|-------|---------|-------------|
| **UI (Blade)** | `resources/views/` | Server-rendered shell, route anchors for SPA |
| **UI (React SPA)** | `resources/js/` | React 19 components served via Vite |
| **API Controllers** | `app/Http/Controllers/Api/` | 20+ REST controllers |
| **Middleware (RBAC)** | `app/Http/Middleware/` | 8 guards (admin, librarian, member, integration, etc.) |
| **Services** | `app/Services/Library/` (44 files), `app/Services/Admin/` (7 files) | Business logic |
| **Models** | `app/Models/` + `app/Models/Library/` | Eloquent ORM |
| **Database** | `database/migrations/` (19 files) | PostgreSQL schema |
| **Providers** | `app/Providers/AppServiceProvider.php` | DI binding |

### 2.3 User Roles & Middleware Guards

| Role | Middleware | Access Scope |
|------|-----------|-------------|
| Public visitor | (none) | Catalog, homepage, news, resources |
| Member/Reader | `library.auth` + `EnsureMemberReader` | Dashboard, loans, reservations, shortlist |
| Librarian | `EnsureLibrarianStaff` | Circulation, copies, catalog, review, imports, reports |
| Internal staff | `internal.circulation.staff` | Internal circulation, review queues, enrichment |
| Admin | `EnsureAdminStaff` | Full admin panel, user management, governance |
| Integration client | `integration.boundary` | External API (reservations, documents) |
| Demo auth | config-gated | Quick-login for development/testing |

### 2.4 Key API Route Groups

| Route Prefix | Auth Guard | Purpose |
|-------------|-----------|---------|
| `/login`, `/logout`, `/v1/me` | web/throttle | Auth |
| `/v1/account/*` | `library.auth` | Member account (loans, reservations) |
| `/v1/shortlist/*` | web session | Shortlist (no login required for session) |
| `/v1/catalog-db`, `/v1/book-db/{isbn}` | public | Public catalog |
| `/v1/internal/circulation/*` | `internal.circulation.staff` | Librarian circulation |
| `/v1/internal/*` | `internal.circulation.staff` | Copy mgmt, review, enrichment, reader contacts |
| `/v1/bridge/*` | public | Bridge diagnostics |
| `/integration/v1/*` | `integration.boundary` | External system API |
| `/v1/internal/ai-assistant/*` | `internal.circulation.staff` | AI assistant |

### 2.5 Key User Flows

#### Flow 1: Authentication
```
User → POST /login (credentials via external CRM)
     → CRM validates → returns token/session
     → App creates local Sanctum session
     → Redirect based on role (member→dashboard, librarian→/librarian, admin→/admin)
```
Files: `app/Http/Controllers/Api/AuthController.php`, `app/Services/Library/IdentityMatchAudit.php`

#### Flow 2: Public Catalog Search
```
User → GET /catalog → React SPA loads
     → SPA calls GET /api/v1/catalog-db?search=X&subject=Y&limit=20
     → CatalogReadService queries PostgreSQL (full-text, UDC subjects, language filter)
     → SPA renders results
```
Files: `app/Services/Library/CatalogReadService.php`, `app/Http/Controllers/Api/CatalogController.php`

#### Flow 3: Book Reservation (Member)
```
Member → GET /book/{isbn} → Book detail page
       → POST /api/v1/account/reservations {document_id}
       → ReaderReservationService validates (active loans, limits, queue position)
       → Creates reservation record
       → Notifications sent
```
Files: `app/Services/Library/ReaderReservationService.php`, `app/Http/Controllers/Api/AccountController.php`

#### Flow 4: Circulation (Librarian Issue/Return)
```
Librarian → POST /api/v1/internal/circulation/checkouts {reader_id, copy_id}
          → CirculationLoanWriteService validates copy availability + reader eligibility
          → Creates CirculationLoan record + CirculationAuditEvent
          → POST /api/v1/internal/circulation/returns {copy_id}
          → Marks loan as returned, updates copy status
```
Files: `app/Services/Library/CirculationLoanWriteService.php`, `app/Http/Controllers/Api/InternalCirculationController.php`

#### Flow 5: Integration API (External Systems)
```
External system → GET/POST /integration/v1/reservations
               → integration.boundary middleware (validates INTEGRATION_ALLOWED_TOKENS)
               → integration.log middleware (logs all requests)
               → IntegrationReservationReadService / IntegrationReservationWriteService
               → Returns JSON response
```
Files: `app/Services/Library/IntegrationReservationReadService.php`, `app/Http/Controllers/Api/Integration/`

### 2.6 External Dependencies

| Dependency | Type | Configured Via | Test Double |
|-----------|------|---------------|------------|
| External CRM | Auth API | `EXTERNAL_AUTH_LOGIN_URL` | Demo auth controller |
| PostgreSQL 18 | Primary DB | `DB_*` env vars | SQLite :memory: |
| 21st-dev AI | AI bridge | `API_KEY_21ST`, `AGENT_21ST_SLUG` | Not found |
| File storage | Local disk | `FILESYSTEM_DISK=local` | Local |
| Email | Log driver | `MAIL_MAILER=log` | Array mailer (tests) |
| Queue | DB queue | `QUEUE_CONNECTION=database` | Sync (tests) |
| Cache | DB cache | `CACHE_STORE=database` | Array (tests) |
| Redis | Optional | `REDIS_*` env vars | Not active by default |

### 2.7 Models Inventory

| Model | Table / Source | Key Relations |
|-------|---------------|--------------|
| `User` | `users` | HasMany roles, sessions |
| `Library/Document` | external read-only PG view | HasMany BookCopy, DocumentAuthor |
| `Library/BookCopy` | `book_copies` (external PG) | BelongsTo Document |
| `Library/CirculationLoan` | `circulation_loans` | BelongsTo BookCopy, Reader |
| `Library/CirculationAuditEvent` | `circulation_audit_events` | BelongsTo CirculationLoan |
| `Library/Reader` | read-only external PG | HasMany CirculationLoans, Reservations |
| `Library/Author` | read-only external PG | BelongsToMany Documents |
| `Library/Publisher` | read-only external PG | |
| `Library/ScientificWork` | `scientific_works` | |
| `Library/DigitalMaterial` | `digital_materials` | BelongsTo Document |
| `Library/QualityIssue` | read-only PG view | |
| `Library/NotificationRecipient` | `notification_recipients` | |
| `AdminExternalResource` | `admin_external_resources` | |
| `LibraryNews` | `library_news` | |
| `LiteratureDraft` + `LiteratureDraftItem` | `literature_drafts` | HasMany items |
| `MemberContactSubmission` | `member_contact_submissions` | |
| `IdentityMatchLog` | `identity_match_logs` | |
| `IntegrationIdempotencyKey` | `integration_idempotency_keys` | |

---

## 3. REPOSITORY INVENTORY

### 3.1 Directory Tree (Key Paths)

```
C:\dev\kazutb-library\
├── .github\
│   └── workflows\
│       ├── ci.yml               ← Main CI: secret-scan + backend-quality + browser-smoke
│       └── release-package.yml  ← Release packaging
├── app\
│   ├── Http\
│   │   ├── Controllers\Api\     ← 20 API controllers + 3 Integration controllers
│   │   └── Middleware\          ← 8 RBAC/auth middleware guards
│   ├── Models\
│   │   ├── Library\             ← 13 domain models (Document, BookCopy, Loan, etc.)
│   │   └── User.php, etc.       ← User, News, ScientificWork, etc.
│   ├── Notifications\           ← Notification classes
│   ├── Providers\
│   │   └── AppServiceProvider.php
│   └── Services\
│       ├── Library\             ← 44 service classes (core domain logic)
│       ├── Admin\               ← 7 admin service classes
│       └── Ai\                  ← TwentyFirstBridgeService.php
├── database\
│   ├── factories\UserFactory.php
│   ├── migrations\              ← 19 migration files (2026-03-26 to 2026-05-10)
│   └── seeders\                 ← DatabaseSeeder.php, DigitalMaterialSeeder.php
├── routes\
│   ├── api.php                  ← ALL API routes (200+ lines)
│   ├── web.php                  ← SPA shell routes (Blade)
│   └── console.php
├── tests\
│   ├── Feature\                 ← ~60 feature test files (Blade page smoke tests)
│   │   └── Api\                 ← ~50 API feature tests (all roles + integration)
│   ├── Unit\
│   │   └── Services\BibliographyFormatterTest.php
│   └── e2e\                     ← 6 Playwright spec files
├── scripts\dev\                 ← 16 shell scripts (CI gates, test runners, dev utils)
├── resources\
│   ├── js\                      ← React SPA source
│   └── views\                   ← Blade templates
├── docker-compose.yml           ← postgres + app + frontend-dev services
├── Dockerfile                   ← nginx + php-fpm image
├── phpunit.xml                  ← Test config (SQLite :memory: for all tests)
├── playwright.config.ts         ← Playwright config (Chromium, auto webserver)
├── composer.json                ← PHP deps + 10 custom composer scripts
└── package.json                 ← Node deps + E2E scripts
```

### 3.2 Configuration Files

| File | Status | Notes |
|------|--------|-------|
| `.env.example` | ✅ Present | Well-documented, all vars listed |
| `.env` | ✅ Present | Local dev config (not committed to remote) |
| `composer.json` | ✅ | PHP 8.4, Laravel 13, Sanctum, PHPUnit 12, Infection |
| `package.json` | ✅ | React 19, Playwright 1.59, Vite 8, Tailwind 4 |
| `phpunit.xml` | ✅ | SQLite :memory:, coverage via pcov |
| `playwright.config.ts` | ✅ | Chromium, auto webserver (PHP serve or Docker), trace on failure |
| `docker-compose.yml` | ✅ | postgres:18 + app (nginx+php-fpm) + frontend-dev (node:22) |
| `Dockerfile` | ✅ | Custom nginx+php-fpm image |
| `.github/workflows/ci.yml` | ✅ | 3 jobs: secret-scan, backend-quality, browser-smoke |
| `scripts/dev/run-ci-gates.sh` | ✅ | Local CI gate script (Pint + tests + build) |

### 3.3 Test Inventory

#### PHP Tests (PHPUnit 12)

| Category | Location | Count (files) | Description |
|----------|---------|--------------|-------------|
| Unit | `tests/Unit/` | 2 | ExampleTest + BibliographyFormatterTest |
| Feature (Pages) | `tests/Feature/` | ~60 | Blade page smoke + auth + admin + member + librarian |
| Feature (API) | `tests/Feature/Api/` | ~50 | REST API contracts, auth hardening, circulation, integration |
| **Total PHP** | | **~112** | |

#### E2E Tests (Playwright)

| File | Focus |
|------|-------|
| `public-smoke.spec.ts` | Public pages load, no 500s |
| `phase0-smoke.spec.ts` | Phase 0 pages exist and render |
| `canonical-pages-visual-smoke.spec.ts` | Key routes render without crash |
| `no-dead-buttons-smoke.spec.ts` | All buttons are clickable (no JS errors) |
| `member-librarian-flows.spec.ts` | Shortlist add/remove, reservation cancel, librarian circulation |
| `news-events-controls.spec.ts` | News/events list and detail pages |

#### Coverage Status

- **PHPUnit clover** generated in CI → `build/test-results/clover.xml`
- **Minimum threshold:** 4% (very low — intentionally conservative start)
- **Estimated actual coverage:** 25–40% by file (assessed from test breadth)
- **Mutation testing:** Infection configured in `composer.json` but **NOT in CI gate**

### 3.4 Test Data & Fixtures

| Asset | Location | Status |
|-------|---------|--------|
| `UserFactory` | `database/factories/UserFactory.php` | ✅ Present (basic) |
| `DatabaseSeeder` | `database/seeders/DatabaseSeeder.php` | ✅ Present |
| `DigitalMaterialSeeder` | `database/seeders/DigitalMaterialSeeder.php` | ✅ Present |
| Book/Document factories | Not found | ❌ Missing |
| Reader factories | Not found | ❌ Missing |
| Loan/Reservation factories | Not found | ❌ Missing |
| Full fixture dataset | Not found | ❌ Missing |
| SQL seed scripts | `scripts/dev/audit_query.sql` (diagnostic only) | ⚠️ Partial |

---

## 4. RUNBOOK

### 4.1 Prerequisites

| Tool | Version | Check Command |
|------|---------|--------------|
| PHP | 8.4+ | `php -v` |
| Composer | 2.x | `composer --version` |
| Node.js | ≥20.19 | `node -v` |
| npm | ≥10 | `npm -v` |
| PostgreSQL | 18 (via Docker) | `docker --version` |
| Docker Desktop | Latest | `docker info` |
| Git | Any | `git --version` |

### 4.2 Full Setup (Docker — Recommended)

```bash
# 1. Clone repository
git clone https://github.com/kazutb-dev/digital-library-kazutb.git
cd digital-library-kazutb

# 2. Copy environment config
cp .env.example .env

# 3. Edit .env — set required values:
#    POSTGRES_PASSWORD=your_password
#    APP_KEY= (will be generated below)
#    EXTERNAL_AUTH_LOGIN_URL=http://crm.local/api/login

# 4. Generate app key
php artisan key:generate

# 5. Install PHP dependencies
composer install

# 6. Install Node dependencies
npm install

# 7. Build frontend assets
npm run build

# 8. Start Docker services (PostgreSQL + app + Vite dev)
docker compose up -d postgres

# 9. Wait for PostgreSQL to be healthy, then run migrations
php artisan migrate --force

# 10. Run optional seeders
php artisan db:seed

# 11. Start application
docker compose up --build -d app frontend-dev

# OR: Run locally (non-Docker)
composer dev
```

**Verify at:** `http://localhost` (Docker) or `http://localhost:8000` (local serve)

### 4.3 Quick Setup (one command via Composer)

```bash
cp .env.example .env
# Edit .env for POSTGRES_PASSWORD and EXTERNAL_AUTH_LOGIN_URL
composer setup
# Then: docker compose up -d postgres; php artisan migrate
```

### 4.4 Running Tests

```bash
# All PHP unit + feature tests (SQLite :memory:, no DB needed)
composer test

# CI quality gates (Pint lint + critical paths + frontend build)
composer qa:ci

# Only critical-path tests (fast ~1-2 min)
composer test:critical-paths

# Internal circulation + copy tests
composer test:internal

# Reservation + circulation core
composer test:reservation-core

# Integration reservation tests
composer test:integration-reservations

# Stewardship tests
composer test:stewardship

# E2E Playwright tests (needs app running or auto-starts)
npm run test:e2e

# E2E with specific file
npx playwright test tests/e2e/public-smoke.spec.ts

# PHPUnit with coverage (requires pcov extension)
./vendor/bin/phpunit --coverage-html build/coverage --coverage-clover build/test-results/clover.xml

# Generate coverage threshold check
composer qa:coverage-threshold
```

### 4.5 Common Errors & Fixes

| Error | Cause | Fix |
|-------|-------|-----|
| `SQLSTATE[HY000]: unable to open database file` | SQLite :memory: path issue | Ensure `DB_DATABASE=:memory:` in test env |
| `php artisan: command not found` | Wrong directory | `cd C:\dev\kazutb-library` |
| `Port 5432 already in use` | Local PG running | Stop local PG or change `POSTGRES_PORT` in `.env` |
| `APP_KEY not set` | Missing key | `php artisan key:generate` |
| `Class not found` | Autoload cache | `composer dump-autoload` |
| `Vite manifest not found` | Frontend not built | `npm run build` |
| `EXTERNAL_AUTH_LOGIN_URL refused` | CRM not reachable | Use demo auth: set `APP_DEMO_LOGIN=true` |
| Playwright `net::ERR_CONNECTION_REFUSED` | App not running | Check `php artisan serve` is running on correct port |
| `POSTGRES_PASSWORD:?Set POSTGRES_PASSWORD` | Docker env missing | Add to `.env`: `POSTGRES_PASSWORD=secret` |
| `infection/infection` failure | Mutation test threshold | Skip with `--skip-initial-tests` or set `minMsi=0` |

### 4.6 Minimal Smoke Check After Launch

```bash
# 1. App is alive
curl -I http://localhost

# 2. Catalog API returns data
curl http://localhost/api/v1/catalog-db?limit=1

# 3. Auth endpoint exists
curl -X POST http://localhost/login -H 'Content-Type: application/json' -d '{"login":"x","password":"x"}' -I

# 4. Integration boundary ping (no token → 401 expected)
curl http://localhost/api/integration/v1/_boundary/ping

# 5. Demo auth identities (returns list if APP_DEMO_LOGIN=true)
curl http://localhost/api/demo-auth/identities
```

---

## 5. RISK-BASED QA FOUNDATION

### 5.1 Risk Register

| # | Module / Component | Business Impact (1–5) | Failure Probability (1–5) | Risk Score | Why Risky | Suggested Test Types | Priority |
|---|-------------------|----------------------|--------------------------|------------|-----------|---------------------|---------|
| R01 | **Auth & Session** — CRM login bridge | 5 | 4 | **20** | External CRM is single auth point; failure locks out ALL users; session replay attacks possible | Unit, Integration, Security (rate limit, session fixation, brute force) | **P0** |
| R02 | **Circulation** — Checkout/Return/Renew | 5 | 3 | **15** | State machine errors cause ghost loans, double-checkout, copy unavailability; financial/audit impact | Integration, E2E, State machine, Boundary values | **P0** |
| R03 | **Role/Permission enforcement** — RBAC middleware | 5 | 3 | **15** | 8 distinct middleware guards; bypass allows unauthorized data access/mutation | Integration, Negative (unauthorized access), Boundary | **P0** |
| R04 | **Integration API** — /integration/v1/* | 4 | 4 | **16** | External systems depend on stable contracts; idempotency key logic, rate limiting, token auth | Contract tests, API integration, Negative (invalid token, exceeded rate) | **P0** |
| R05 | **Catalog Search & Filter** — CatalogReadService | 4 | 3 | **12** | Core user feature; SQL injection surface, language filter bugs, UDC subject filter logic | Unit (service), API, E2E, Boundary (empty results, special chars, large result sets) | **P1** |
| R06 | **Reservation workflow** — ReaderReservationService | 4 | 3 | **12** | Queue position, cancellation, duplicate reservation logic; member frustration if broken | Integration, State machine, E2E | **P1** |
| R07 | **Data integrity** — Migrations & legacy data | 4 | 4 | **16** | 19 migrations, nullable fields, legacy MARC-SQL data with known errors; risk of NULL dereference | Migration tests, DB constraint tests, Unit (null safety) | **P0** |
| R08 | **Digital Materials streaming** — DigitalMaterialService | 3 | 4 | **12** | File path resolution, unauthorized access to PDFs, broken streams | Security (auth check), Integration, Boundary (missing file) | **P1** |
| R09 | **Shortlist** — session-based persistence | 3 | 3 | **9** | Session-based without login; data loss on session expire, export format correctness | Unit, API, E2E (add/remove/export) | **P2** |
| R10 | **Admin panel** — user/role management | 4 | 2 | **8** | Admin mutations (role assignment, governance) must be atomic; errors cause privilege escalation | Integration, Negative, Admin-specific E2E | **P1** |
| R11 | **Error handling & logging** — unhandled exceptions | 3 | 4 | **12** | Unhandled exceptions expose stack traces in production; CirculationWriteException, InternalCopyWriteException | Unit (exception classes), Integration (error responses), E2E (error pages) | **P1** |
| R12 | **Performance** — catalog/search at scale | 3 | 4 | **12** | PostgreSQL full-text search without optimized indexes; SPA cold load; no perf baseline exists | Load test (k6), DB query analysis | **P1** |
| R13 | **ISBN/enrichment** — IsbnService + CatalogEnrichmentService | 3 | 3 | **9** | ISBN validation, external enrichment API reliability | Unit, Integration, Mocking external | **P2** |
| R14 | **Scientific repository** — ScientificRepositoryService | 3 | 3 | **9** | File upload, metadata accuracy, access control for theses/dissertations | Integration, Security (file type validation) | **P2** |
| R15 | **Notification system** — LibraryNotificationService | 2 | 3 | **6** | Notification delivery failures; duplicate notifications; bad recipient logic | Unit, Integration | **P2** |
| R16 | **AI assistant** — TwentyFirstBridgeService | 2 | 4 | **8** | External API dependency (21st-dev); token expiry; fallback not tested | Integration, Mocking, Error handling | **P2** |
| R17 | **News & Events** — AdminNewsManagementService | 2 | 2 | **4** | Low-risk CRUD; mostly content management | Unit, E2E (basic CRUD) | **P2** |

### 5.2 Risk Heatmap

```
Failure Probability
    5 │  R07  R04  │  R01     │       │
    4 │  R08  R16  │  R02 R03 │  R11  │
    3 │  R09  R13  │  R05 R06 │  R10  │
    2 │             │  R17     │       │
    1 │             │          │       │
      └────────────┴──────────┴───────┘
           1–2          3–4        5
              Business Impact →

P0 (Critical ≥15): R01, R02, R03, R04, R07
P1 (High 10–14):   R05, R06, R08, R11, R12
P2 (Medium <10):   R09, R10, R13, R14, R15, R16, R17
```

---

## 6. TEST STRATEGY DRAFT v0.1

### 6.1 Scope

**IN SCOPE:**
- All API endpoints in `routes/api.php`
- Authentication flows (login, session, logout, role enforcement)
- Circulation lifecycle (reserve → checkout → renew → return)
- Public catalog search/filter/sort
- Member dashboard (loans, reservations, shortlist, history)
- Librarian operations (issue, return, copy management, review queues)
- Admin operations (user management, news, external resources)
- Integration API contracts (`/integration/v1/*`)
- Digital materials access control
- Data integrity (migrations, constraints, null safety)
- Error handling (exception classes, HTTP response codes)
- Performance baseline (response time, concurrent users)

**OUT OF SCOPE (for baseline through experimental QA program layers):**
- Full mobile responsiveness testing
- Accessibility (WCAG) audit — separate assignment
- Security penetration testing (beyond auth hardening tests)
- Payment processing (not present in system)
- Third-party AI assistant deep testing (21st-dev internals)
- Legacy MARC-SQL migration validation

### 6.2 Test Levels

| Level | Framework | Execution | Target |
|-------|---------|-----------|--------|
| **Unit** | PHPUnit 12 | `./vendor/bin/phpunit --testsuite Unit` | Service methods, business rules, formatters |
| **Integration (API)** | PHPUnit 12 + HTTP TestCase | `./vendor/bin/phpunit --testsuite Feature` | API contracts, DB interactions, middleware |
| **E2E (Browser)** | Playwright 1.59 | `npm run test:e2e` | Critical user journeys (member + librarian) |
| **Contract** | PHPUnit Feature/Api | Existing integration tests | Integration API schema/response validation |
| **Performance** | k6 (to be added) | `k6 run performance/scripts/*.js` | Catalog search, catalog load, login throughput |
| **Mutation** | Infection | `./vendor/bin/infection` | Service layer mutation score |

### 6.3 Manual vs Automation Split

| Category | Manual | Automated | Rationale |
|----------|--------|-----------|----------|
| Auth happy paths | 20% | 80% | High risk, repetitive, fast to automate |
| RBAC boundary tests | 10% | 90% | Systematic, PHPUnit covers well |
| Catalog search/filter | 30% | 70% | Edge cases need manual exploration |
| Circulation lifecycle | 20% | 80% | State machine — automate all transitions |
| Integration API | 5% | 95% | Contract testing — fully automatable |
| Admin CRUD | 40% | 60% | Complex UI interactions; some manual needed |
| Performance | 0% | 100% | Tooling-only |
| Exploratory | 100% | 0% | By definition |

**Target automation:** 70% overall

### 6.4 Priority for Automation (Risk-Based)

```
Tier 1 (automate first — P0 risks):
  1. Auth: login/logout, session lifecycle, role-based redirect, rate limiting
  2. RBAC: unauthorized access to each protected route group
  3. Circulation: full checkout→return→renew state machine
  4. Integration API: all /integration/v1/* endpoints with valid/invalid tokens
  5. Data integrity: null-safety in critical service methods

Tier 2 (automate next — P1 risks):
  6. Catalog search: text search, subject filter, language filter, empty results
  7. Reservation: create, cancel, queue position, duplicate prevention
  8. Digital materials: auth check, streaming, missing file handling
  9. Error responses: 401, 403, 404, 422, 500 for all major endpoints

Tier 3 (complete after — P2 risks):
  10. Shortlist: add, remove, export, session persistence
  11. Scientific repository: upload, metadata, access
  12. Notifications: delivery, deduplication
```

### 6.5 Entry/Exit Criteria

**Entry Criteria (before test execution):**
- Application running (Docker or local)
- Migrations executed successfully
- `php artisan config:clear` run
- Demo auth enabled (`APP_DEMO_LOGIN=true`) for E2E

**Exit Criteria (Definition of Done):**
- All P0 test cases pass
- P1 test cases pass at 90%+
- No unresolved Critical/High defects
- Coverage meets threshold (target: 40% lines for automation and quality governance layer)
- All E2E smoke tests pass on main branch

### 6.6 Defect Severity/Priority Model

| Severity | Description | Example |
|---------|------------|---------|
| **S1 — Critical** | System unavailable or data loss | Login broken, DB corruption |
| **S2 — High** | Core feature broken, no workaround | Cannot checkout books, reservation fails |
| **S3 — Medium** | Feature degraded, workaround exists | Filter shows wrong results, export format wrong |
| **S4 — Low** | Minor UI/UX issue | Label typo, pagination off-by-one |

| Priority | SLA |
|---------|-----|
| **P1** | Fix before next push to main |
| **P2** | Fix in current sprint |
| **P3** | Fix in next sprint |
| **P4** | Backlog |

### 6.7 Quality Gates (CI/CD)

| Gate | Command | Threshold | Blocking? |
|------|---------|-----------|----------|
| Secret scan | Gitleaks | 0 secrets | ✅ Yes |
| Code style | `composer run pint --test` | 0 violations | ✅ Yes |
| Critical path tests | `composer test:critical-paths` | 100% pass | ✅ Yes |
| Coverage floor | `composer qa:coverage-threshold` | ≥4% (raise to 40%) | ✅ Yes |
| Frontend build | `npm run build` | No errors | ✅ Yes |
| Browser smoke | `npm run test:e2e` | All pass | ✅ Yes |
| Mutation score | `./vendor/bin/infection` (add to CI) | ≥60% MSI | ⚠️ Not yet |
| Performance gate | k6 (add to CI) | P95 < 500ms | ⚠️ Not yet |

---

## 7. METRICS BASELINE FOR RESEARCH

### 7.1 Current State Metrics

| Metric | Value | Type |
|--------|-------|------|
| High-risk modules (P0) | **5** | Actual |
| Medium-risk modules (P1) | **6** | Actual |
| Total risk items identified | **17** | Actual |
| PHP test files (total) | **~112** | Actual |
| — Unit test files | **2** | Actual |
| — Feature/Page test files | **~60** | Actual |
| — Feature/API test files | **~50** | Actual |
| E2E Playwright spec files | **6** | Actual |
| E2E test cases (scenarios) | **~15** | Actual |
| Current coverage threshold | **4%** | Actual |
| Estimated actual line coverage | **~30%** | Estimated |
| CI pipeline jobs | **3** | Actual |
| Composer QA scripts | **10** | Actual |
| Service classes (Library) | **44** | Actual |
| API endpoints | **~55** | Actual |
| Database migrations | **19** | Actual |
| Test factories | **1** (UserFactory) | Actual |
| Seeders | **2** | Actual |

### 7.2 Effort Estimates

| QA Layer | Estimated Hours | Breakdown |
|-----------|----------------|---------|
| **A1 — Risk-based QA** | **12–16 hrs** | Risk register refinement (3h) + manual test cases writing (6h) + environment validation (2h) + artifacts (3h) |
| **A2 — Automation baseline** | **20–28 hrs** | Auth automation (4h) + Catalog/circulation (8h) + API contract tests (6h) + CI gate setup (4h) + reporting (4h) |
| **A3 — Performance experiment** | **16–20 hrs** | k6 setup (3h) + baseline captures (4h) + load scenarios (4h) + analysis (4h) + charts for paper (3h) |
| **Research paper** | **12–16 hrs** | Methodology (3h) + results analysis (4h) + charts/graphs (3h) + writing (6h) |

### 7.3 Metrics for Research Paper Tracking

| # | Metric | Measurement Method | Frequency |
|---|--------|------------------|---------|
| M1 | **Test case count (by type)** | Count files/describe blocks | Per week |
| M2 | **PHPUnit line coverage %** | Clover XML | Per PR |
| M3 | **Mutation score (MSI)** | Infection report | Per assignment |
| M4 | **CI pass rate %** | GitHub Actions history | Per week |
| M5 | **P0 defects found/fixed** | Manual tracking (issues) | Per assignment |
| M6 | **API response time P50/P95** | k6 summary | Baseline + after changes |
| M7 | **E2E test execution time** | Playwright HTML report | Per run |
| M8 | **PHPUnit suite execution time** | JUnit XML timing | Per run |
| M9 | **Risk coverage %** (risks with tests) | Risk register | Per assignment |
| M10 | **Automation ratio %** (auto/total tests) | Count | Per assignment end |
| M11 | **False positive rate** (flaky tests) | Re-runs with `--repeat`) | Per week |
| M12 | **Defect detection efficiency** (bugs found per test hour) | Manual log | Per assignment |

---

## 8. QA ROADMAP (WEEK-BY-WEEK)

### Week 2 — baseline risk-based QA strategy: Risk-Based QA

**Goal:** Establish risk-based test foundation and manual test cases.

**Deliverables:**
- ✅ Full audit report (this document)
- 📝 Refined risk register (17+ risks with updated scores after review)
- 📝 Manual test case specs for P0 risks (auth, RBAC, circulation, integration API)
- 📝 Environment setup report validated (all commands tested locally)
- 📝 Test data plan (what fixtures are needed for each test case)

**Concrete tasks:**
```
1. Run full test suite: composer test → capture baseline pass/fail
2. Run E2E suite: npm run test:e2e → capture results
3. Generate coverage report: phpunit --coverage-html → document baseline %
4. Write 20+ manual test cases for: Auth (5), RBAC (5), Circulation (5), Catalog (5)
5. Document 3–5 defects or gaps found during exploration
6. Validate risk register against PROJECT_CONTEXT.md
```

**Risks for this week:**
- Environment setup issues (PostgreSQL, PHP version)
- Demo auth not working (blocks E2E)

**Dependencies:** Running application, PHP 8.4, Docker

---

### Week 4 — automation and quality governance layer: Automation Baseline + CI Quality Gates

**Goal:** Automate P0/P1 test cases and integrate into CI pipeline.

**Deliverables:**
- 🤖 Automated API tests for auth + circulation + integration boundary (PHPUnit)
- 🤖 Playwright E2E tests for member + librarian critical flows (expand existing)
- ⚙️ Updated CI gate with coverage ≥ 40% threshold
- ⚙️ Mutation testing baseline (Infection, MSI ≥ 50%)
- 📊 Automation coverage report

**Concrete tasks:**
```
1. Add factories: DocumentFactory, ReaderFactory, LoanFactory
2. Expand Feature/Api tests for missing coverage: Digital materials, Shortlist export
3. Add Playwright tests: admin news CRUD, shortlist export, error pages
4. Raise coverage threshold from 4% to 40% in composer.json
5. Add Infection to CI gate (mutation score report)
6. Add k6 install step to CI for performance scaffolding
7. Update ci.yml to upload coverage artifact to Codecov
```

**Risks for this week:**
- Factory creation complex (legacy external DB models may not have Eloquent factories)
- Mutation testing may require long CI run time

**Dependencies:** Week 2 test cases, stable environment

---

### Week 5 — Intermediate Empirical Review Checkpoint

**Goal:** Review quality metrics, adjust strategy, prepare for experimental phase.

**Deliverables:**
- 📊 Mid-project metrics snapshot (M1–M12)
- 📝 Defect summary report (found vs fixed)
- 📊 Coverage trend chart (Week 2 → Week 5)
- 📝 Risk register update (re-score based on findings)
- 🎯 Revised scope for experimental evaluation layer

**Concrete tasks:**
```
1. Export all metrics to metrics/baseline-metrics.csv (update)
2. Generate coverage diff: current vs Week 2 baseline
3. Review CI run history: pass rate, flaky tests
4. Write interim research paper section (methodology + early results)
5. Identify 2–3 performance experiment hypotheses
6. Screenshot evidence collection (evidence/screenshots/)
```

---

### Week 7 — experimental evaluation layer: Performance & Experimental Testing

**Goal:** Establish performance baseline, run load experiments, produce research data.

**Deliverables:**
- ⚡ k6 load test scripts for: catalog search, login, circulation endpoint
- 📊 Performance baseline report (P50/P95 at 1/10/50 concurrent users)
- 🧪 Experimental test design (hypothesis → test → result)
- 📊 Research paper draft with charts/graphs
- 🏁 Final QA summary (all 3 program layers combined)

**Concrete tasks:**
```
1. Install k6: https://k6.io/docs/get-started/installation/
2. Write scripts: performance/scripts/catalog-load.js, login-stress.js, circulation-load.js
3. Capture baseline (1 VU): response time, throughput, error rate
4. Run load scenarios: 5, 10, 25 concurrent users
5. Document PostgreSQL query times for catalog search
6. Compare: with/without database indexes (if applicable)
7. Write experimental hypothesis: "Adding index X reduces P95 by Y%"
8. Generate charts: response time curves, error rate vs load
9. Finalize research paper (all metrics M1–M12 populated)
```

**Risks:**
- PostgreSQL not accessible for performance tests (Docker vs local)
- k6 results vary by machine specs (document test environment)

**Dependencies:** Weeks 2 + 4 automation complete; stable Docker environment

---

## 9. RECOMMENDED QA FOLDER STRUCTURE

```
C:\dev\kazutb-library\qa\
│
├── README.md                          ← This index file
│
├── docs\                              ← All QA strategy and reporting documents
│   ├── 00-full-audit-report.md        ← This document
│   ├── risk-register.md               ← Risk register (standalone, updatable)
│   ├── qa-test-strategy.md            ← Test strategy v0.1 (standalone)
│   └── qa-environment-setup-report.md ← Env setup steps + validation results
│
├── test-cases\                        ← Manual and exploratory test specifications
│   ├── auth\                          ← Login, logout, session, RBAC boundary
│   ├── catalog\                       ← Search, filter, sort, book detail
│   ├── circulation\                   ← Checkout, return, renew, reserve, cancel
│   ├── admin\                         ← User mgmt, news, external resources, reports
│   └── api\                           ← Integration API contracts
│
├── automation\                        ← Automated test scripts (additional, beyond repo tests)
│   ├── ui\                            ← Playwright scripts for additional E2E coverage
│   └── api\                           ← REST API test collections (Bruno/Postman)
│
├── performance\                       ← Load and performance testing
│   ├── scripts\                       ← k6 test scripts
│   │   ├── catalog-load.js
│   │   ├── login-stress.js
│   │   └── circulation-load.js
│   └── results\                       ← Captured k6 JSON/HTML results
│
├── test-data\                         ← Test data for repeatable runs
│   ├── fixtures\                      ← JSON/CSV fixture files
│   └── seeds\                         ← Additional SQL seed scripts
│
├── evidence\                          ← Proof of test execution
│   ├── screenshots\                   ← Playwright failure screenshots + manual evidence
│   └── logs\                          ← Test run logs, CI output excerpts
│
├── metrics\                           ← Quantitative tracking for research paper
│   ├── baseline-metrics.csv           ← Week-by-week metric snapshots
│   └── charts\                        ← Generated charts (PNG/SVG) for paper
│
├── ci\                                ← CI/CD pipeline snippets and overrides
│   └── coverage-gate-update.yml       ← Updated threshold config snippet
│
├── final-research\                    ← Research paper and final deliverables
│   ├── paper-draft.md
│   └── figures\                       ← High-quality charts for publication
│
└── weekly-progress-log.md            ← Running log of weekly progress
```

---

## 10. READY ARTIFACTS

> See separate files:
> - `qa/docs/risk-register.md`
> - `qa/docs/qa-test-strategy.md`
> - `qa/docs/qa-environment-setup-report.md`
> - `qa/metrics/baseline-metrics.csv`
> - `qa/weekly-progress-log.md`

---

## 11. GAP ANALYSIS

### 11.1 What's Missing for a Complete AQA Cycle

| Gap | Impact | Effort to Fix |
|----|--------|--------------|
| **No domain model factories** (Document, BookCopy, Reader, Loan, Reservation) | Cannot write isolated DB tests without real PG data | Medium |
| **Coverage threshold too low** (4% minimum) | No meaningful coverage gate | Low (config change) |
| **No mutation testing in CI** | MSI unknown; tests may be low-quality | Low (add to ci.yml) |
| **No performance test baseline** | Cannot detect regressions | Medium (k6 setup) |
| **SQLite vs PostgreSQL gap** | Tests pass on SQLite but may fail on PG (ILIKE, full-text, etc.) | Medium |
| **No API contract documentation** (OpenAPI/Swagger) | Cannot do contract-drift detection | High |
| **No accessibility tests** | WCAG compliance unknown | Medium |
| **E2E demo auth dependency** | E2E tests skip if demo auth disabled | Low (always enable in CI) |
| **Shortlist factory missing** | Shortlist persistence tests need data | Low |
| **Integration API fixture (allowed tokens)** | Integration tests need `INTEGRATION_ALLOWED_TOKENS` | Low |

### 11.2 Top 10 Improvements (Quick Wins First)

| # | Improvement | Impact | Effort | Type |
|---|------------|--------|--------|------|
| 1 | **Raise coverage threshold** from 4% to 30%+ in `phpunit.xml`/`composer.json` | High | 5 min | Config |
| 2 | **Add `APP_DEMO_LOGIN=true` to CI .env** for E2E tests | High | 5 min | Config |
| 3 | **Add Infection to CI gate** with MSI threshold | High | 30 min | Config |
| 4 | **Create DocumentFactory + ReaderFactory** | High | 2–3 hrs | Code |
| 5 | **Add OpenAPI annotations** to top 10 API controllers | High | 4–6 hrs | Docs |
| 6 | **Add k6 script** for catalog search baseline | Medium | 2 hrs | Test |
| 7 | **Write negative test cases** for all RBAC middleware | High | 3 hrs | Test |
| 8 | **Add `DB_CONNECTION=pgsql` CI job** alongside SQLite to catch PG-only bugs | High | 1 hr | CI |
| 9 | **Expand E2E: admin news CRUD, shortlist export** | Medium | 2 hrs | Test |
| 10 | **Document `INTEGRATION_ALLOWED_TOKENS` in README** for test setup | Medium | 30 min | Docs |

---

## 12. FINAL CHECKLIST

### Readiness for baseline risk-based QA strategy Start

| Item | Status | Next Action |
|------|--------|------------|
| Repository cloned and accessible | ✅ Ready | — |
| `.env` configured from `.env.example` | ✅ Ready | — |
| PHP 8.4 available | ✅ Ready (verify: `php -v`) | Confirm version |
| `composer install` successful | ✅ Ready | Run if not done |
| `npm install` successful | ✅ Ready | Run if not done |
| PostgreSQL running (Docker) | ⚠️ Partially Ready | `docker compose up -d postgres` |
| Migrations executed | ⚠️ Partially Ready | `php artisan migrate` |
| App starts without error | ⚠️ Partially Ready | `php artisan serve` or Docker |
| `composer test` runs and passes | ⚠️ Partially Ready | Run and verify |
| `npm run test:e2e` runs and passes | ⚠️ Partially Ready | Requires app running |
| Coverage report generated | ⚠️ Partially Ready | `phpunit --coverage-html build/coverage` |
| Risk register reviewed against PROJECT_CONTEXT.md | ✅ Ready | Review `docs/risk-register.md` |
| Test strategy reviewed | ✅ Ready | Review `docs/qa-test-strategy.md` |
| Test cases folder created | ✅ Ready | `qa/test-cases/` exists |
| Metrics baseline CSV ready | ✅ Ready | `qa/metrics/baseline-metrics.csv` |
| Demo auth enabled for E2E | ❌ Not Ready | Add `APP_DEMO_LOGIN=true` to `.env` |
| `INTEGRATION_ALLOWED_TOKENS` set for API tests | ❌ Not Ready | Add token to `.env` |
| Factory for Document/BookCopy | ❌ Not Ready | Create `database/factories/DocumentFactory.php` |
| k6 installed for performance | ❌ Not Ready | Install for experimental evaluation layer (not urgent now) |

---

## 13. QUESTIONS FOR TEAM

1. **CRM auth endpoint availability:** Is `http://crm.local/api/login` accessible in the test environment, or should we rely solely on demo auth (`APP_DEMO_LOGIN=true`) for all automated tests?

2. **PostgreSQL vs SQLite gap:** Are there known test failures when running against real PostgreSQL (vs SQLite :memory:)? Should we add a PostgreSQL CI job?

3. **`INTEGRATION_ALLOWED_TOKENS` for tests:** What token value should be used in the test `.env` for integration API tests? The existing tests seem to work — are they using a pre-configured value in `phpunit.xml`?

4. **Coverage threshold intent:** The current threshold is 4% — is this a placeholder or intentional? What is the team's target coverage %?

5. **External PG data (read-only models):** Models like `Document`, `BookCopy`, `Reader` extend `ReadOnlyPgsqlModel` — are they reading from a separate external PostgreSQL instance, or the same DB? How are these represented in tests (mocked, or does test DB have this data)?

6. **Mutation testing:** Infection is in `composer.json` but not in CI. Is there a planned MSI target? Should we add it to the CI gate?

7. **Demo data availability:** Are there specific reader IDs and copy IDs expected by E2E tests (e.g., `demo-student-001`, `copy-demo-001` in `member-librarian-flows.spec.ts`)? Where are these seeded?

8. **Digital materials file storage:** For tests of `/digital-materials/{id}/stream`, are actual PDF files needed, or are they stubbed? Is there a test fixture for this?

9. **Release process:** Does the `release-package.yml` workflow deploy to a staging environment? Is there a staging URL where QA can validate before production?

10. **AI assistant testing:** Is the 21st-dev integration (`InternalAiAssistantController`) in scope for QA, or is it experimental/excluded? The `API_KEY_21ST` is hardcoded in `.env.example` — should this be rotated?
