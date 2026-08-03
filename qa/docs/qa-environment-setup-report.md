# QA Environment Setup Report — KazUTB Digital Library Platform

> **Version:** 1.0
> **Date:** 2026-05-13
> **Project:** `kazutb-dev/digital-library-kazutb`
> **Author:** (your name)
> **Purpose:** Document environment setup steps and validation results for reproducible test execution

---

## 1. ENVIRONMENT OVERVIEW

| Component | Required | Notes |
|-----------|---------|-------|
| OS | Windows 10/11 / macOS / Ubuntu | Tested on Windows 11 |
| PHP | 8.4+ | Strict requirement (checked in `run-ci-gates.sh`) |
| Composer | 2.x | PHP dependency manager |
| Node.js | ≥ 20.19.0 | Enforced in `package.json` engines field |
| npm | ≥ 10 | Bundled with Node 20 |
| Docker Desktop | Latest | For PostgreSQL + full app stack |
| Git | Any | Version control |
| Chromium | Playwright-managed | For E2E tests (auto-installed) |
| k6 | Latest (optional, experimental evaluation layer) | Performance testing |

---

## 2. STEP-BY-STEP SETUP

### Step 1: Clone Repository

```bash
git clone https://github.com/kazutb-dev/digital-library-kazutb.git
cd digital-library-kazutb
```

**Expected result:** Repository cloned, `C:\dev\kazutb-library` (or your path) populated.

**Validation:** `ls` / `dir` shows: `app/`, `routes/`, `tests/`, `composer.json`, `package.json`

---

### Step 2: Configure Environment

```bash
# Copy example env
cp .env.example .env

# REQUIRED: Edit .env and set these values:
# POSTGRES_PASSWORD=your_secure_password
# APP_KEY=  (leave blank — will be generated next)
# EXTERNAL_AUTH_LOGIN_URL=http://crm.local/api/login  (or use demo auth)
# APP_DEMO_LOGIN=true  (ADD THIS LINE for E2E tests)
# INTEGRATION_ALLOWED_TOKENS=test-token-12345  (for integration API tests)
```

**Validation:** `cat .env | grep APP_KEY` shows key is blank (will be generated in Step 4).

---

### Step 3: Install PHP Dependencies

```bash
composer install
```

**Expected result:** `vendor/` directory created with all PHP packages.

**Validation:** `composer show | grep laravel/framework` shows `laravel/framework v13.x`

**Common issue:** If `php: command not found` → install PHP 8.4 and add to PATH.

---

### Step 4: Generate App Key

```bash
php artisan key:generate
```

**Expected result:** `.env` file updated with `APP_KEY=base64:...`

**Validation:** `grep APP_KEY .env` shows a base64 key.

---

### Step 5: Install Node Dependencies

```bash
npm install
```

**Expected result:** `node_modules/` populated with React, Playwright, Vite, etc.

**Validation:** `node_modules/.bin/vite --version` returns version number.

---

### Step 6: Build Frontend Assets

```bash
npm run build
```

**Expected result:** `public/build/` directory created with compiled JS/CSS assets.

**Validation:** `ls public/build/` shows `manifest.json` and asset files.

**Common issue:** `ReferenceError: process is not defined` → update Node.js to ≥ 20.19.

---

### Step 7: Start PostgreSQL (Docker)

```bash
docker compose up -d postgres
```

**Expected result:** PostgreSQL 18 container running, healthy.

**Validation:**
```bash
docker compose ps
# postgres container status: "healthy"
```

**Common issue:** `POSTGRES_PASSWORD:?Set POSTGRES_PASSWORD in .env` → add `POSTGRES_PASSWORD=yourpassword` to `.env`.

---

### Step 8: Run Database Migrations

```bash
php artisan migrate --force
```

**Expected result:** All 19 migrations run successfully.

**Validation:**
```bash
php artisan migrate:status
# All migrations show "Yes" in Ran? column
```

**Common issue:** `Connection refused` → ensure Docker PostgreSQL is healthy (Step 7), or check `DB_HOST` / `DB_PORT` in `.env`.

---

### Step 9: (Optional) Run Database Seeders

```bash
php artisan db:seed
```

**Expected result:** `DatabaseSeeder` and `DigitalMaterialSeeder` run.

---

### Step 10: Install Playwright Browsers

```bash
npx playwright install chromium
# Or with system dependencies (recommended for first install):
npx playwright install --with-deps chromium
```

**Expected result:** Chromium browser installed in Playwright's managed directory.

**Validation:** `npx playwright --version` returns version.

---

### Step 11: Start Application

#### Option A: Local (php artisan serve)

```bash
php artisan serve --host=127.0.0.1 --port=8000
```

**Access:** `http://127.0.0.1:8000`

#### Option B: Full Docker Stack

```bash
docker compose up --build -d app frontend-dev
```

**Access:** `http://localhost` (port 80)

#### Option C: Development Mode (all services + hot reload)

```bash
composer dev
# Starts: php artisan serve + queue:listen + pail logging + npm run dev
```

---

## 3. TEST EXECUTION VALIDATION

### 3.1 PHP Tests (All)

```bash
composer test
```

| Expected | Actual (fill in) |
|---------|-----------------|
| All tests pass | ___/___  passed |
| No errors | Error count: |
| Execution time | seconds |

```bash
# PHPUnit with coverage (requires pcov PHP extension)
./vendor/bin/phpunit --coverage-html build/coverage --coverage-clover build/test-results/clover.xml
```

**Coverage report location:** `build/coverage/index.html`

---

### 3.2 Critical Path Tests

```bash
composer test:critical-paths
```

| Expected | Actual (fill in) |
|---------|-----------------|
| 18 test class suite passes | Pass/Fail |

---

### 3.3 CI Quality Gates (Local)

```bash
composer qa:ci
```

**This runs:**
1. `php artisan optimize:clear`
2. Pint code style check on 15 key files
3. Critical path PHPUnit tests
4. Frontend production build

| Step | Expected | Actual (fill in) |
|------|---------|-----------------|
| optimize:clear | OK | |
| Pint | 0 violations | |
| PHPUnit critical paths | All pass | |
| npm run build | No errors | |

---

### 3.4 E2E Tests (Playwright)

```bash
npm run test:e2e
```

**Prerequisites:**
- Application running (auto-started by Playwright config if PHP 8.4 detected)
- `APP_DEMO_LOGIN=true` in `.env` (for member/librarian flows)

| Spec File | Expected | Actual (fill in) |
|-----------|---------|-----------------|
| `public-smoke.spec.ts` | Pass | |
| `phase0-smoke.spec.ts` | Pass | |
| `canonical-pages-visual-smoke.spec.ts` | Pass | |
| `no-dead-buttons-smoke.spec.ts` | Pass | |
| `member-librarian-flows.spec.ts` | Skipped if no demo auth / Pass | |
| `news-events-controls.spec.ts` | Pass | |

**Report:** `playwright-report/index.html`

---

### 3.5 Integration Tests

```bash
composer test:integration-reservations
composer test:internal
composer test:reservation-core
composer test:stewardship
```

| Suite | Expected | Actual (fill in) |
|-------|---------|-----------------|
| Integration reservations | Pass | |
| Internal core | Pass | |
| Reservation + circulation | Pass | |
| Stewardship metrics | Pass | |

---

## 4. SMOKE CHECK AFTER SETUP

Run these manual checks to confirm the stack is working:

```bash
# 1. App health
curl -s -o /dev/null -w "%{http_code}" http://localhost/
# Expected: 200

# 2. Catalog API
curl -s "http://localhost/api/v1/catalog-db?limit=1" | python -m json.tool
# Expected: JSON with {"data": [...], "total": N}

# 3. Auth endpoint (POST with no body → validation error expected)
curl -s -X POST http://localhost/login \
  -H "Content-Type: application/json" \
  -d '{}' | python -m json.tool
# Expected: 422 with validation errors

# 4. Protected route without auth (→ 401)
curl -s -o /dev/null -w "%{http_code}" http://localhost/api/v1/account/summary
# Expected: 401

# 5. Integration boundary without token (→ 401)
curl -s -o /dev/null -w "%{http_code}" http://localhost/api/integration/v1/_boundary/ping
# Expected: 401

# 6. Demo auth identities (if APP_DEMO_LOGIN=true)
curl -s http://localhost/api/demo-auth/identities | python -m json.tool
# Expected: JSON array with demo user identities
```

---

## 5. ENVIRONMENT VALIDATION RESULTS

> Fill in after each setup session:

| Check | Result | Date | Notes |
|-------|--------|------|-------|
| PHP version | `php -v` → | | |
| Composer version | `composer -V` → | | |
| Node version | `node -v` → | | |
| Docker version | `docker -v` → | | |
| PostgreSQL healthy | `docker compose ps` → | | |
| Migrations status | `php artisan migrate:status` → | | |
| `composer test` | Pass/Fail → | | |
| `npm run test:e2e` | Pass/Fail → | | |
| `composer qa:ci` | Pass/Fail → | | |

---

## 6. KNOWN ISSUES & WORKAROUNDS

| Issue | Workaround | Status |
|-------|-----------|--------|
| CRM auth not reachable in dev | Set `APP_DEMO_LOGIN=true`, use `/demo-auth/login` | Documented |
| SQLite vs PostgreSQL behavior differences | Run separate PG test job (future CI improvement) | Open |
| Playwright: member/librarian flows skip | Ensure `APP_DEMO_LOGIN=true` in test env | Documented |
| `INTEGRATION_ALLOWED_TOKENS` not set | Add to `.env`: `INTEGRATION_ALLOWED_TOKENS=test-token-12345` | Documented |
| Port 5432 conflict | Change `POSTGRES_PORT=5433` in `.env` (docker-compose default is already 5433) | Documented |
| `vendor/bin/infection` slow | Use `--threads=4` flag | Documented |
| Vite manifest missing in tests | Run `npm run build` before tests that need assets | Documented |

---

## 7. DOCKER QUICK REFERENCE

```bash
# Start all services
docker compose up -d

# Start only database
docker compose up -d postgres

# Rebuild app image
docker compose up --build -d app

# View logs
docker compose logs -f app

# Access PostgreSQL shell
docker compose exec postgres psql -U library_user -d digital_library

# Stop all
docker compose down

# Stop and remove volumes (full reset)
docker compose down -v
```

---

## 8. ENVIRONMENT SETUP CHECKLIST

- [ ] PHP 8.4+ installed and in PATH
- [ ] Composer 2.x installed
- [ ] Node.js ≥ 20.19.0 installed
- [ ] Docker Desktop running
- [ ] `.env` configured from `.env.example`
- [ ] `APP_DEMO_LOGIN=true` added to `.env`
- [ ] `INTEGRATION_ALLOWED_TOKENS=test-token-12345` added to `.env`
- [ ] `composer install` completed
- [ ] `php artisan key:generate` run
- [ ] `npm install` completed
- [ ] `npm run build` completed
- [ ] `docker compose up -d postgres` running
- [ ] `php artisan migrate` completed
- [ ] `npx playwright install chromium` completed
- [ ] `composer test` — all tests pass
- [ ] `npm run test:e2e` — all tests pass (or documented skip reasons)
- [ ] `composer qa:ci` — all gates pass
