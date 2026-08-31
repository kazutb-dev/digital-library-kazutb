# KazUTB Digital Library

## Project Overview

KazUTB Digital Library is a Laravel-based university digital library platform for catalog discovery, member services, librarian workflows, administrative operations, and API integration.

This repository includes:

- the web application (public and authenticated surfaces),
- backend services and domain logic,
- automated test suites (PHPUnit and Playwright),
- QA and research evidence artifacts used for academic reporting.

Main purpose:

- provide an operational digital library system,
- support reproducible QA validation and evidence-based reporting.

Main technologies:

- Laravel 13, PHP 8.3+,
- PostgreSQL,
- Vite/Node.js frontend pipeline,
- PHPUnit 12 and Playwright.

## Key Features

- Public catalog, book detail, news/events, and informational pages.
- Member flows (account, shortlist, reservations, notifications/history surfaces).
- Librarian and internal operational modules.
- Admin and integration-facing workflows.
- Risk-aware QA evidence package under qa/.
- Multi-language content support (en, kk, ru).

## Technology Stack

- Backend: PHP, Laravel 13
- Database: PostgreSQL (Docker service uses postgres:18)
- Frontend tooling: Node.js, npm, Vite, TailwindCSS
- Test frameworks: PHPUnit (Unit/Feature/API), Playwright (browser E2E)
- Container workflow: Docker Compose

## Repository Structure

Important paths for evaluators:

- app/: Laravel application code (controllers, middleware, models, services)
- config/: runtime and integration configuration
- database/: migrations, factories, seeders
- routes/: web/api route definitions
- resources/: Blade views, JS/CSS source
- tests/Unit: unit tests
- tests/Feature: feature and API/integration-oriented tests
- tests/e2e: Playwright browser tests
- scripts/dev/test-postgres.sh: guarded isolated PostgreSQL test runner
- qa/: QA workspace and research artifacts
- qa/final-improvements/: canonical metrics, tables, figures, traceability
- qa/final-research/: manuscript and final integration/proofread reports

## Prerequisites

Recommended workflow uses Docker.

Required tools (Docker workflow):

- Docker Desktop or Docker Engine with Docker Compose v2

Required tools (non-Docker/local workflow):

- PHP 8.3+ (the current verified development runtime is PHP 8.4)
- Composer 2+
- Node.js 20.19.0+
- npm 10+
- PostgreSQL (if not using Docker PostgreSQL)

The guarded PostgreSQL test runner is a Bash script; on Windows use Git Bash,
WSL, or the Linux-based application container.

## Installation and Setup

Primary recommended path: Docker.

### 1. Clone and enter repository

```bash
git clone https://github.com/kazutb-dev/digital-library-kazutb.git
cd digital-library-kazutb
```

### 2. Environment configuration

```bash
cp .env.example .env
```

Then update critical values in .env:

- APP_KEY (generate if empty)
- DB*\* and POSTGRES*\* values
- POSTGRES_PASSWORD (must be set for docker-compose)
- APP_URL (default http://localhost)

Generate application key:

```bash
php artisan key:generate
```

### 3. Install dependencies

If using local host tools:

```bash
composer install
npm install
```

If using Docker-first workflow, dependencies can be installed in containers when needed:

```bash
docker compose exec app composer install
docker compose exec frontend-dev npm install
```

### 4. Database setup

For a newly provisioned, non-production database, migrations are an explicit
operator action:

```bash
php artisan migrate
```

Notes:

- The application entrypoint never applies migrations. It checks for pending
  migrations and fails closed when code and schema are incompatible.
- Production migration requires a verified backup restore, clone validation,
  an approved rollback plan, and a separate deployment operation.
- Never use a destructive Artisan command against a development or production
  database. Canonical PostgreSQL tests must use the guarded runner and a
  database name ending in `_test`:

```bash
TEST_DB_DATABASE=digital_library_test ./scripts/dev/test-postgres.sh
```

The runner uses an isolated Laravel config-cache path, verifies the effective
runtime database, and refuses to continue when the shell and Laravel settings
do not match.

## Running the Application

### Option A (Recommended): Docker runtime

```bash
docker compose up --build -d app frontend-dev
```

Default endpoints:

- Application: http://localhost
- Vite dev server: http://localhost:5173

Useful checks:

```bash
docker compose ps
docker compose logs app --tail=200
docker compose logs postgres --tail=200
```

### Option B: Local host runtime

Run backend and frontend separately:

```bash
php artisan serve
npm run dev
```

Or run the combined Composer dev process:

```bash
composer dev
```

This starts Laravel server, queue listener, logs stream, and Vite concurrently.

## Running Tests

This repository includes README-required test script instructions below.

### Unit tests

Run Unit suite only:

```bash
php artisan test --testsuite=Unit
```

Alternative:

```bash
php vendor/bin/phpunit --testsuite Unit
```

Expected output:

- PHPUnit/Laravel test summary with passed/failed counts.

### Feature tests

Run Feature suite only:

```bash
php artisan test --testsuite=Feature
```

Alternative:

```bash
php vendor/bin/phpunit --testsuite Feature
```

### Full backend test run

```bash
composer test
```

Equivalent core command:

```bash
php artisan test
```

### API / integration-focused tests

Run specific reservation integration files:

```bash
composer test:integration-reservations
```

Critical-path API and core filters:

```bash
composer test:critical-paths
```

Other focused backend groups:

```bash
composer test:internal
composer test:reservation-core
composer test:stewardship
```

Environment note:

- phpunit.xml sets DB_CONNECTION=sqlite and DB_DATABASE=:memory: for test defaults.
- PostgreSQL-specific tests must use `composer test:postgres` with an explicit
  database name ending in `_test`. Do not point generic PHPUnit commands at a
  live runtime database.

### Browser / end-to-end tests (Playwright)

Install browser runtime:

```bash
npm run test:e2e:install
```

If system dependencies are missing (Linux/container contexts):

```bash
npm run test:e2e:install:system
```

Run E2E tests:

```bash
npm run test:e2e
```

Expected output:

- Playwright terminal report and HTML report in playwright-report/ (or configured output path).

### Frontend quality gate

```bash
npm run qa:frontend
```

This runs:

1. npm run build
2. npm run test:e2e

## Test and QA commands

Verified Composer commands and what they do:

- composer qa:ci
    - Runs Composer validation, Pint, the isolated critical-path test filter,
      and the frontend production build directly from `composer.json`.
    - Use when: reproducing CI-like local gate checks.

- composer dev:critical-paths
    - Alias for the isolated `test:critical-paths` PHPUnit filter.

- composer dev:catalog-paths (alias composer test:catalog-paths)
    - Runs the public catalogue convergence/page/recovery feature tests.

- composer test:internal, composer test:reservation-core, composer
  test:integration-reservations, and composer test:stewardship
    - Run their maintained PHPUnit filters or explicit test files directly;
      no deleted wrapper script is involved.

The only maintained script under `scripts/dev/` is `test-postgres.sh`. It
refuses non-`_test` targets and verifies the effective connection before any
test migration.

## QA / Research Artifacts

Primary QA/research locations:

- qa/README.md: QA workspace overview
- qa/final-improvements/: canonical QA evidence package
    - SUMMARY.md, TRACEABILITY.md
    - metrics/ (CSV/JSON evidence)
    - tables/ (paper-ready markdown tables)
    - figures-publication/ and figure-sources/
    - docs/ methodology, validity, replication notes
- qa/final-research/: final manuscript artifacts
    - full-paper draft references and integration/proofread reports
- qa/phase4/paper/full-paper-draft.md: active integrated paper draft

If you are evaluating reproducibility, start with:

1. qa/final-improvements/SUMMARY.md
2. qa/final-improvements/TRACEABILITY.md
3. qa/final-research/

## Troubleshooting / Notes

- Docker DB startup issues:
    - Ensure POSTGRES_PASSWORD is set in .env.
    - Check docker compose logs postgres.

- App boots but migrations fail:
    - Verify DB_HOST, DB_PORT, DB_DATABASE, DB_USERNAME, DB_PASSWORD.

- Frontend not updating:
    - Ensure frontend-dev service or npm run dev is running.

- Playwright startup behavior:
    - playwright.config.ts can fallback to Docker web server when compatible local PHP is unavailable.

- Guarded PostgreSQL runner portability on Windows:
    - `scripts/dev/test-postgres.sh` requires Git Bash/WSL or a Linux-based
      container.

- Removed legacy helpers:
    - vault sync/hooks, ad-hoc recovery audits, evidence capture, and host
      installer wrappers are not repository commands. Follow the checked-in
      deployment/runtime documentation and approved operational procedure.

## License

Composer metadata declares MIT license.
No standalone LICENSE file was found in the repository root at the time of this README rewrite.

## Author / Academic Context

This repository includes an academic QA evidence and manuscript workflow under qa/, including:

- structured risk/testing evidence,
- final-improvements package,
- final-research integration and editorial reports.

For submission/evaluation context, use qa/final-research/ together with qa/final-improvements/.
