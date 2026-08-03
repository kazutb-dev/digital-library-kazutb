# Replication Package & Reproducibility Guide

## Overview

This guide enables independent verification and reproduction of the enhanced QA campaign results.

## Package Contents

### 1. Source Code

- **Location:** Repository root + tests/
- **Test Files:**
    - tests/Feature/Api/AdminPrivilegeNegativePathTest.php (28 tests)
    - tests/Feature/Api/Integration/ReservationMutateTest.php (15 tests)
- **Framework:** Laravel 13.x
- **Git:** Available at kazutb-dev/digital-library-kazutb

### 2. Execution Logs

- **Location:** qa/final-improvements/testing/
- **Files:**
    - test-execution-full.log (complete output)
    - admin-privilege-tests.log (28 tests detail)
    - reservation-mutation-tests.log (15 tests detail)
    - feature-tests.log (887 tests)
    - unit-tests.log (17 tests)

### 3. Metrics & Evidence

- **Metrics:** qa/final-improvements/metrics/ (24 files)
- **Tables:** qa/final-improvements/tables/ (10 files)
- **Figures:** qa/final-improvements/figure-sources/ (11 files)

### 4. Configuration

- **Framework:** Laravel 13
- **PHP:** 8.4.21
- **PHPUnit:** 12.5.14
- **Database Test:** SQLite :memory: (default)
- **Database Live:** PostgreSQL 18

---

## Reproduction Steps

### Step 1: Environment Setup

```bash
# Clone repository
git clone https://github.com/kazutb-dev/digital-library-kazutb.git
cd digital-library-kazutb

# Install dependencies
composer install
npm install

# Copy environment
cp .env.example .env

# Database setup
php artisan migrate --database=sqlite
```

### Step 2: Run Enhanced Tests

```bash
# Run both enhanced test files
php ./vendor/bin/phpunit \
  tests/Feature/Api/AdminPrivilegeNegativePathTest.php \
  tests/Feature/Api/Integration/ReservationMutateTest.php \
  --testdox

# Expected output:
# ✅ AdminPrivilegeNegativePathTest: 28/28 PASS
# ✅ ReservationMutateTest: 15/15 PASS
# Total: 43 tests; 102 assertions; ~10 seconds
```

### Step 3: Run Full Suite

```bash
# Run entire test suite (Feature + Unit)
php ./vendor/bin/phpunit \
  tests/Feature \
  tests/Unit \
  --testdox

# Expected: ~947 tests; ~793 pass (83.8%); ~190 seconds
```

### Step 4: Verify Results

```bash
# Compare with documented metrics
# Expected results:
# - Enhanced tests: 43/43 pass (100%)
# - Feature tests: 737/887 pass (83%)
# - Unit tests: 17/17 pass (100%)
# - Total: 793/947 pass (83.8%)
```

---

## Docker Execution (Recommended)

### Using Docker Compose

```bash
# Build and run containers
docker compose up -d

# Execute tests in app container
docker compose exec -T app php ./vendor/bin/phpunit \
  tests/Feature/Api/AdminPrivilegeNegativePathTest.php \
  tests/Feature/Api/Integration/ReservationMutateTest.php \
  --testdox

# Full suite
docker compose exec -T app php ./vendor/bin/phpunit tests --testdox
```

### Docker Configuration

- **php-fpm:** Application runtime
- **nginx:** Web server
- **postgres:** PostgreSQL 18 database
- **frontend-dev:** Node.js/TypeScript build container

---

## Metrics Verification

### Verify Pass Rates

```bash
# Extract pass rate from logs
grep "tests passed" qa/final-improvements/testing/test-execution-full.log

# Expected:
# Tests: 793 passed, 112 failed, 36 errors, 5 risky
```

### Verify Enhanced Tests

```bash
# Count enhanced test assertions
grep -c "assert" tests/Feature/Api/AdminPrivilegeNegativePathTest.php
grep -c "assert" tests/Feature/Api/Integration/ReservationMutateTest.php

# Expected: 102+ assertions total
```

### Verify Execution Time

```bash
# Check performance baseline
grep "Time:" qa/final-improvements/testing/test-execution-full.log

# Expected: ~190 seconds for full suite
```

---

## Environment Validation

### Verify Framework Versions

```bash
php --version
composer show laravel/framework
composer show phpunit/phpunit
```

### Expected Versions

- PHP: 8.4.21
- Laravel: 13.x
- PHPUnit: 12.5.14

### Database Check

```bash
# SQLite (test)
sqlite3 :memory: ".tables"

# PostgreSQL (live)
psql -U postgres -h localhost -d kazutb
```

---

## Blockers & Known Issues

### PostgreSQL Schema Missing User Table

- **Impact:** 20 tests blocked
- **Workaround:** Tests use SQLite by default
- **Fix:** Requires PostgreSQL test schema migration
- **Status:** Tests remain in codebase, ready for execution

### Pre-existing Feature Failures

- **Count:** 112 tests
- **Impact:** Overall pass rate 83.8%
- **Status:** Not investigated (out of scope)

---

## Reproducibility Checklist

- ✅ Framework version: 13.x
- ✅ PHP version: 8.4.21
- ✅ PHPUnit version: 12.5.14
- ✅ Database: SQLite + PostgreSQL 18
- ✅ Test code: All in repository
- ✅ Execution logs: Captured and archived
- ✅ Metrics: CSV + JSON files provided
- ✅ Commands: Documented step-by-step

---

## Differences from Original

### Changes Made

- Added 43 enhanced tests (28 + 15)
- Fixed syntax error in ReservationMutateTest
- Corrected parameter names (bookId validation)
- Improved session setup

### Original State Preserved

- Framework versions unchanged
- Existing tests preserved
- No breaking changes
- Backward compatible

---

## Troubleshooting

### Issue: Tests timeout

```bash
# Solution: Increase timeout
php ./vendor/bin/phpunit tests --timeout=600
```

### Issue: PostgreSQL connection error

```bash
# Solution: Use SQLite (default)
# Tests automatically use :memory: database
```

### Issue: Missing dependencies

```bash
# Solution: Reinstall
composer install --no-cache
npm install
```

---

## Further Questions

Refer to:

- [QA Enhancement Methodology](qa-enhancement-methodology.md)
- [Evidence Strengthening Report](evidence-strengthening-report.md)
- [Threats to Validity](threats-to-validity-structured.md)
- [TRACEABILITY.md](../TRACEABILITY.md)
