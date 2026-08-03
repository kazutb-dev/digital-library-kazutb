# Implementation Roadmap

**Repository:** kazutb-dev/digital-library-kazutb  
**Campaign:** Real QA Improvement Implementation  
**Date:** 2026-05-14  
**Status:** Phase A in progress

---

## Execution Order (Confirmed)

```
Phase A (Environment) → Phase B (Tests) → Targeted validation → Full rerun → Metrics → Visuals → Audit → Paper
```

---

## Phase A — Stabilize Execution Environment

| Step | Task                                                                                                                         | Status  | Notes                                                                                                                                                         |
| ---- | ---------------------------------------------------------------------------------------------------------------------------- | ------- | ------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| A1   | Fix `AccountController::resolveCrmUserId` — remove hardcoded `DB::connection('pgsql')->table('public.User')` existence check | ✅ DONE | Commit-ready; see blocker-resolution-report.md                                                                                                                |
| A2   | Verify `.env.testing` and phpunit.xml env consistency                                                                        | ✅ DONE | No `.env.testing` file needed; phpunit.xml uses `DB_CONNECTION=sqlite :memory:`. Routes use `web` middleware in api.php — session driver = array. Consistent. |
| A3   | Confirm deterministic test setup                                                                                             | ✅ DONE | SQLite `:memory:` migrations run fresh per test class. Factories available. No seeds required for feature API tests.                                          |

### A1 Fix Summary

**File changed:** `app/Http/Controllers/Api/AccountController.php`  
**Method:** `resolveCrmUserId(array $sessionUser): ?string`

**Before:** Performed two raw pgsql queries (`public.User` existence check + email fallback lookup) that required the `public.User` table to exist in the test PostgreSQL schema. When that table was absent, tests errored.

**After:** Trusts the session-resident UUID directly (same approach as `routes/web.php` `$resolveCrmUserId` closure). No DB lookup performed. The CRM user ID is authoritative from the CRM authentication response and is already stored in the signed server-side session.

---

## Phase B — Strengthen High-Risk Tests

| Step | Task                                           | Status     | Priority |
| ---- | ---------------------------------------------- | ---------- | -------- |
| B1   | Auth/authorization boundary tests              | 🔲 PENDING | High     |
| B2   | Reservation/circulation workflow tests         | 🔲 PENDING | High     |
| B3   | Integration boundary tests (payload contracts) | 🔲 PENDING | High     |
| B4   | Admin/privileged route forbidden-access tests  | 🔲 PENDING | High     |
| B5   | UI/E2E critical path (if Docker available)     | 🔲 PENDING | Medium   |

**Gate:** Phase B begins only after Phase A is validated in Docker environment.

---

## Phase C — Assertion Depth Improvement

| Step | Task                                             | Status     |
| ---- | ------------------------------------------------ | ---------- |
| C1   | Strengthen status-code-only tests in auth suites | 🔲 PENDING |
| C2   | Add field-by-field response structure assertions | 🔲 PENDING |
| C3   | Add database-state verification after mutations  | 🔲 PENDING |
| C4   | Add error payload assertions for negative paths  | 🔲 PENDING |

---

## Phase D — Automation Layer Broadening

| Layer          | Decision                                                        | Status                                        |
| -------------- | --------------------------------------------------------------- | --------------------------------------------- |
| D1 API         | Richer contract + negative-path tests                           | 🔲 PENDING                                    |
| D2 Feature     | Workflow correctness, role-driven nav                           | 🔲 PENDING                                    |
| D3 UI/E2E      | Smoke + critical path (Playwright, Docker required)             | 🔲 PENDING                                    |
| D4 Performance | Reuse `qa/performance/scripts/collect-performance-baseline.ps1` | 🔲 PENDING — see performance-tool-decision.md |
| D5 Chaos       | EXCLUDED — see chaos-scope-decision.md                          | ⛔ EXCLUDED                                   |

---

## Metrics & Evidence Generation

| Step   | Task                                      | Status     |
| ------ | ----------------------------------------- | ---------- |
| M1     | Full PHPUnit JUnit XML → CSV/JSON metrics | 🔲 PENDING |
| M2-M10 | Regenerate all tables post-implementation | 🔲 PENDING |

---

## Visuals Generation

| Figure                              | Status                                    |
| ----------------------------------- | ----------------------------------------- |
| F1 CI/CD pipeline (figure-00)       | ✅ DONE (prior remediation)               |
| F2 Testing architecture (figure-02) | ✅ DONE (prior remediation)               |
| F3–F10                              | 🔲 PENDING (post-rerun, real data needed) |

---

## Blocking Dependencies

| Dependency                 | Impact                           | Required For                  |
| -------------------------- | -------------------------------- | ----------------------------- |
| Docker Desktop running     | Cannot run pgsql-dependent tests | Phase A validation, B, D3, D4 |
| A1 fix validated in Docker | Cannot move to Phase B           | All subsequent phases         |
