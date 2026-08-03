# baseline QA layer (Phase 1) Baseline Metrics Methodology

**Author:** Murat Almas
**Date:** 2026-05-13

## 1. Metrics to Collect

- **baseline QA layer (Phase 1) Manual Test Design Coverage:**
    - Number of manual test case files by folder
    - Total number of manual test case files
    - Number of manual-design modules covered
- **Repository Test Inventory:**
    - Unit test file count
    - Feature test file count
    - API-identifiable test file count
    - E2E test file count
- **Risk and CI Baseline:**
    - Number of high-risk (P0) items in risk register
    - Number of current CI workflow files
- **Toolchain Validation Baseline:**
    - Current validated tool versions from allowed commands
- **Blocked Metrics:**
    - Unit/Feature/E2E line coverage metrics when coverage driver is unavailable

## 2. Collection Methods

- **Commands:**
    - `Get-ChildItem ... | Measure-Object` (file and directory counts)
    - `Select-String ... | Measure-Object` (risk register P0 count)
    - `php --version`
    - `php vendor/bin/phpunit --version`
    - `npx playwright --version`
    - `docker compose ps`
- **Evidence Sources:**
    - `evidence/logs/phase1-metrics-collection.log`
    - `docs/phase1-risk-register.md`
    - `.github/workflows/*`
    - Repository tree under `tests/` and `qa/phase1/test-cases/`

## 3. Current Status

| Metric Group                     | Status                       |
| -------------------------------- | ---------------------------- |
| Manual test case counts          | Measured                     |
| Repository test inventory counts | Measured                     |
| High-risk register count         | Measured                     |
| CI workflow file count           | Measured                     |
| Toolchain version baseline       | Measured                     |
| Unit and feature line coverage   | Blocked (`blocked`)          |
| E2E coverage metric              | Blocked (`not measured yet`) |

## 4. Next Steps

- Enable `pcov` or `xdebug` and rerun allowed coverage commands to replace blocked values.
- Keep command output snapshots in `evidence/logs/phase1-metrics-collection.log` for reproducibility.

## 5. Blocked Metrics Policy

- If a metric cannot be measured in the current allowed scope, set value to `blocked` or `not measured yet`.
- Do not infer or estimate blocked values.
- Record the exact blocking reason in notes.

---

_All data above is based on repository inspection and logs._



