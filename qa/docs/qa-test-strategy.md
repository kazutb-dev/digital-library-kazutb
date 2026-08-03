# QA Test Strategy v0.1 — KazUTB Digital Library Platform

> **Version:** 0.1 (Draft)
> **Date:** 2026-05-13
> **Project:** `kazutb-dev/digital-library-kazutb`
> **Author:** (your name)
> **Scope:** Advanced QA baseline through experimental QA program layers + Research Paper
> **Review:** Update at each assignment milestone

---

## 1. OBJECTIVES

1. Establish a reproducible, evidence-based QA process for the KazUTB Digital Library platform
2. Identify and mitigate the highest-risk functional areas before production
3. Build an automated regression baseline covering P0/P1 risks
4. Capture quantitative metrics to support the research paper
5. Demonstrate progressive quality improvement across Weeks 2–7

---

## 2. SCOPE

### In Scope

| Area | Rationale |
|------|-----------|
| All REST API endpoints (`/api/v1/*`, `/integration/v1/*`) | Core system contracts |
| Authentication flows (login, session, logout, RBAC) | Highest-risk P0 area |
| Circulation lifecycle (reserve → checkout → renew → return) | Core library operation |
| Public catalog (search, filter, sort, book detail) | Primary user-facing feature |
| Member dashboard (loans, reservations, shortlist, history) | Key member value |
| Librarian operations (copy mgmt, review queues, circulation) | Operational staff workflows |
| Admin operations (user/role mgmt, news, external resources) | Admin control surface |
| Integration API contracts | External system stability |
| Digital materials access control | Security-sensitive feature |
| Data integrity (migrations, null safety) | Data quality |
| Error handling (HTTP codes, exception classes) | Production resilience |
| Performance baseline (catalog, login, circulation) | Non-functional requirement |

### Out of Scope

| Area | Reason |
|------|--------|
| WCAG accessibility audit | Separate assignment |
| Full security penetration testing | Beyond program scope |
| Mobile/tablet responsiveness | UI not primary focus |
| Legacy MARC-SQL data migration validation | Separate data ops concern |
| 21st-dev AI assistant deep testing | External service, experimental |
| Payment processing | Not present in system |
| Production infrastructure/DevOps | Infrastructure team scope |

---

## 3. TEST LEVELS

### 3.1 Unit Testing

- **Framework:** PHPUnit 12
- **Target:** Service classes (`app/Services/Library/`, `app/Services/Admin/`), exception classes, utility classes
- **Key targets:** `BibliographyFormatterTest`, `IsbnService`, `CatalogReadService` methods, `CirculationLoanWriteService` validation
- **Command:** `php artisan test --testsuite Unit`
- **Coverage goal:** 80% line coverage for service layer

### 3.2 Integration Testing (API)

- **Framework:** PHPUnit 12 + Laravel `TestCase` (HTTP client)
- **Target:** All API controllers, middleware enforcement, database interactions
- **Key targets:** Auth flows, RBAC boundary (all 8 middleware), circulation state machine, integration API contracts
- **Command:** `php artisan test --testsuite Feature`
- **Coverage goal:** 60% line coverage for controllers and services

### 3.3 End-to-End Testing (Browser)

- **Framework:** Playwright 1.59 (Chromium)
- **Target:** Critical user journeys (member: login→search→reserve→view, librarian: issue→return, admin: create news)
- **Command:** `npm run test:e2e`
- **Environment:** SQLite :memory: with demo auth enabled

### 3.4 Contract Testing

- **Approach:** PHPUnit API tests validate JSON response schemas against expected shape
- **Target:** `/integration/v1/*` endpoints (reservation read/mutate, document management)
- **Priority:** High (external systems depend on stable contracts)

### 3.5 Performance Testing

- **Tool:** k6 (to be installed)
- **Scenarios:**
  - `catalog-load.js`: GET /api/v1/catalog-db with search params, 1/5/10/25 VUs
  - `login-stress.js`: POST /login, concurrent auth, 1/5/10 VUs
  - `circulation-load.js`: POST /internal/circulation/checkouts, sequential, 1/5 VUs
- **Baseline:** Capture P50, P95, P99 response times at each VU level
- **Command:** `k6 run performance/scripts/catalog-load.js`

### 3.6 Mutation Testing

- **Tool:** Infection (configured in `composer.json`)
- **Target:** Service layer (`app/Services/`)
- **Command:** `./vendor/bin/infection --threads=4 --min-msi=50 --min-covered-msi=60`
- **Goal:** MSI ≥ 50% (automation and quality governance layer milestone)

---

## 4. MANUAL vs AUTOMATION SPLIT

| Test Category | Total Cases | Manual | Automated | Automation % |
|--------------|------------|--------|-----------|-------------|
| Auth (login/logout/session/rate limit) | 20 | 4 | 16 | 80% |
| RBAC boundary (8 guards × scenarios) | 40 | 4 | 36 | 90% |
| Catalog search/filter | 25 | 8 | 17 | 68% |
| Reservation workflow | 20 | 4 | 16 | 80% |
| Circulation lifecycle | 20 | 4 | 16 | 80% |
| Integration API | 30 | 2 | 28 | 93% |
| Member dashboard | 15 | 5 | 10 | 67% |
| Librarian panel | 20 | 8 | 12 | 60% |
| Admin panel | 20 | 10 | 10 | 50% |
| Error handling | 15 | 2 | 13 | 87% |
| Performance | 10 | 0 | 10 | 100% |
| Exploratory | 15 | 15 | 0 | 0% |
| **Total** | **250** | **66** | **184** | **74%** |

---

## 5. PRIORITY FOR AUTOMATION (RISK-BASED)

### Tier 1 — Automate First (P0 risks, Week 2–3)

| # | Test Area | Existing? | Action |
|---|-----------|----------|--------|
| 1 | Login success/failure/rate-limiting | ✅ `LoginTest`, `AuthHardeningTest`, `AuthSessionLifecycleTest` | Expand |
| 2 | Session lifecycle (me, logout, expiry) | ✅ `AuthSessionMeTest` | Expand |
| 3 | RBAC: unauthorized access all 8 route groups | ✅ `InternalAccessBoundaryTest`, `ReaderAccessProtectionTest` | Expand |
| 4 | Circulation: checkout→return→renew | ✅ `InternalCirculationCheckoutReturnTest`, `InternalCirculationRenewalTest` | Expand |
| 5 | Integration API: valid/invalid token, rate limit | ✅ `IntegrationRateLimitTest`, `ReservationMutateTest` | Expand |
| 6 | Data integrity: null field handling in catalog | ⚠️ Partial | Create |

### Tier 2 — Automate Next (P1 risks, Week 3–4)

| # | Test Area | Existing? | Action |
|---|-----------|----------|--------|
| 7 | Catalog search: text, subject, language filter | ✅ `CatalogDbSearchTest`, `CatalogDbLanguageFilterTest` | Expand with edge cases |
| 8 | Reservation: create/cancel/duplicate prevention | ✅ `ReaderReservationTest`, `AccountReservationsTest` | Expand |
| 9 | Digital materials: auth gate, missing file | ✅ `DigitalMaterialTest` | Expand |
| 10 | Error responses: HTTP codes per exception | ⚠️ Partial | Create |
| 11 | Admin: user role assignment | ⚠️ Partial | Create |

### Tier 3 — Complete Last (P2 risks, Week 4+)

| # | Test Area | Existing? | Action |
|---|-----------|----------|--------|
| 12 | Shortlist: add/remove/export/session | ✅ `PersistentShortlistTest`, `ShortlistApiTest`, `ShortlistExportTest` | Expand |
| 13 | ISBN enrichment: valid/invalid | ⚠️ Partial in `CatalogEnrichmentTest` | Expand |
| 14 | Scientific repository: upload/metadata | ✅ `ScientificRepositoryFeatureTest` | Expand |
| 15 | Notifications: delivery/dedup | ❌ Not found | Create |

---

## 6. ENTRY / EXIT CRITERIA

### Entry Criteria (Before Each Test Phase)

- [ ] Application starts without errors (`php artisan serve` or Docker)
- [ ] All migrations applied (`php artisan migrate --status`)
- [ ] `composer test` baseline captured (all existing tests pass)
- [ ] Demo auth enabled for E2E (`APP_DEMO_LOGIN=true` in `.env`)
- [ ] `INTEGRATION_ALLOWED_TOKENS` set in `.env` for integration tests
- [ ] Test environment documented (`php -v`, `node -v`, DB version)

### Exit Criteria (baseline risk-based QA strategy — Risk-Based QA)

- [ ] 17 risk items documented with scores in `qa/docs/risk-register.md`
- [ ] 40+ manual test cases written (covering all P0 risks)
- [ ] Environment setup validated end-to-end (all commands in Runbook executed)
- [ ] Baseline metrics captured in `qa/metrics/baseline-metrics.csv`
- [ ] At least 3 defects or gaps documented

### Exit Criteria (automation and quality governance layer — Automation)

- [ ] Coverage ≥ 40% (line coverage from Clover report)
- [ ] All P0 automated test suites pass in CI
- [ ] Mutation score (MSI) ≥ 50% for service layer
- [ ] CI pipeline updated with new thresholds
- [ ] Playwright E2E: member flow + librarian flow both pass

### Exit Criteria (experimental evaluation layer — Performance)

- [ ] k6 baseline captured for catalog, login, circulation
- [ ] P95 response time documented at 1/5/10 VU levels
- [ ] At least one experiment (hypothesis → test → result) documented
- [ ] Performance charts generated for research paper

---

## 7. DEFECT SEVERITY / PRIORITY MODEL

### Severity Levels

| Severity | Code | Description | Example in This System |
|---------|------|------------|----------------------|
| Critical | S1 | System unavailable, data loss, security breach | Login broken, DB corruption, file path traversal |
| High | S2 | Core feature broken, no workaround | Cannot checkout books, reservation creates duplicate |
| Medium | S3 | Feature degraded, workaround exists | Filter returns wrong results, export format incorrect |
| Low | S4 | Minor UX/UI issue | Label typo, incorrect pagination count |

### Priority Levels

| Priority | Code | SLA | Action |
|---------|------|-----|--------|
| Immediate | P1 | Fix before next push to main | S1 defects always P1 |
| High | P2 | Fix in current sprint | S2 defects |
| Normal | P3 | Fix in next sprint | S3 defects |
| Low | P4 | Backlog | S4 defects |

### Defect Tracking

> Use GitHub Issues with labels: `bug`, `severity:s1/s2/s3/s4`, `priority:p1/p2/p3/p4`, `module:auth/catalog/circulation/etc.`

---

## 8. QUALITY GATES (CI/CD)

| Gate | Current | Target (A2) | Command / Config |
|------|---------|------------|-----------------|
| Secret scanning | ✅ Gitleaks | ✅ No change | `.github/workflows/ci.yml` |
| Code style (Pint) | ✅ 15 files checked | Expand to all `app/` | `scripts/dev/run-ci-gates.sh` |
| Critical path tests | ✅ ~18 test classes | All P0/P1 test classes | `composer test:critical-paths` |
| Coverage floor | ⚠️ 4% minimum | **40%** minimum | `composer qa:coverage-threshold` |
| Frontend build | ✅ `npm run build` | No change | `ci.yml: browser-smoke` |
| Browser smoke | ✅ Playwright Chromium | Add member+librarian flows | `npm run test:e2e` |
| Mutation testing | ❌ Not in CI | Add: MSI ≥ 50% | `./vendor/bin/infection` |
| Performance gate | ❌ Not in CI | Add: P95 < 500ms for catalog | `k6 run --vus 10 --duration 30s` |
| PostgreSQL CI job | ❌ Not in CI | Add parallel PG job | New job in `ci.yml` |

---

## 9. TEST ENVIRONMENT MATRIX

| Environment | DB | Auth | Queue | Cache | Use Case |
|------------|-----|------|-------|-------|---------|
| **Local dev** | PostgreSQL 18 (Docker) | CRM or Demo | Database | Database | Full stack dev |
| **Unit/Integration tests** | SQLite :memory: | Demo (disabled) | Sync | Array | Fast automated tests |
| **E2E tests (CI)** | SQLite :memory: | Demo (enabled) | Sync | Array | Playwright browser tests |
| **E2E tests (local)** | PostgreSQL or SQLite | Demo (enabled) | Sync | Array | Local Playwright dev |
| **Performance tests** | PostgreSQL 18 | Demo | Database | Database | Realistic load |
| **CI (GitHub Actions)** | SQLite :memory: | Demo | Sync | Array | All automated CI |

---

## 10. TOOLS & VERSIONS

| Tool | Version | Purpose |
|------|---------|---------|
| PHPUnit | 12.5.12 | PHP unit + feature tests |
| Playwright | 1.59.1 | E2E browser tests |
| Infection | * (latest) | Mutation testing |
| Laravel Pint | 1.27 | PHP code style enforcement |
| k6 | Latest (to install) | Performance load testing |
| Gitleaks | v2 | Secret scanning |
| pcov | PHP ext | Code coverage driver |
| Chromium | (Playwright-managed) | Browser for E2E |

---

## 11. REVISION HISTORY

| Version | Date | Author | Changes |
|---------|------|--------|---------|
| 0.1 | 2026-05-13 | (your name) | Initial draft from repository audit |
| | | | |
