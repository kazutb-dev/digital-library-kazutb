# QA Workspace — KazUTB Digital Library Platform

> Repository: `kazutb-dev/digital-library-kazutb`
> QA Owner: (your name)
> Audit Date: 2026-05-13
> Framework: Laravel 13 · PHP 8.4 · React 19 · Playwright · PHPUnit 12

---

## Directory Map

| Folder | Purpose |
|--------|---------|
| `docs/` | Strategy, risk register, environment setup report, full audit |
| `test-cases/` | Manual + exploratory test case specifications (by module) |
| `automation/ui/` | Playwright E2E scripts (beyond built-in tests) |
| `automation/api/` | API test scripts (Postman collections, Bruno, or additional Playwright) |
| `performance/` | k6 / Artillery scripts + baseline results |
| `test-data/` | Fixtures, seed SQL, CSV payloads for repeatable test runs |
| `evidence/` | Screenshots, logs, and screen recordings captured during testing |
| `metrics/` | CSV/JSON metric snapshots + chart data for research paper |
| `ci/` | Pipeline YAML snippets and gate configuration |
| `final-research/` | Research paper drafts, graphs, comparative data |

---

## Quick Navigation

- **Full Audit Report** → `docs/00-full-audit-report.md`
- **Risk Register** → `docs/risk-register.md`
- **Test Strategy** → `docs/qa-test-strategy.md`
- **Environment Setup** → `docs/qa-environment-setup-report.md`
- **Baseline Metrics** → `metrics/baseline-metrics.csv`
- **Weekly Progress** → `weekly-progress-log.md`

---

## Essential Commands

```bash
# Run all PHP tests
composer test

# Run CI quality gates (Pint + critical-path tests + frontend build)
composer qa:ci

# Run only critical-path tests
composer test:critical-paths

# Run Playwright E2E smoke suite
npm run test:e2e

# Run internal circulation tests
composer test:internal

# Run reservation + circulation core
composer test:reservation-core

# Run integration reservation tests
composer test:integration-reservations

# Run stewardship metrics tests
composer test:stewardship
```

---

## Status

- [x] QA Folder Structure Created
- [x] Full Audit Report Generated
- [x] Risk Register Drafted
- [x] Test Strategy v0.1 Written
- [x] Environment Setup Report Created
- [x] Baseline Metrics CSV Created
- [ ] Manual Test Cases Written (baseline risk-based QA strategy)
- [ ] Automation Scripts Created (automation and quality governance layer)
- [ ] Performance Baselines Captured (experimental evaluation layer)
