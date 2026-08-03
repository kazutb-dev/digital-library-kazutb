# baseline QA layer (Phase 1) Environment Setup & Validation Report

**Author:** Murat Almas
**Date:** 2026-05-13

## 1. Toolchain Validation

| Tool           | Version/Status | Validation Evidence Log                 |
| -------------- | -------------- | --------------------------------------- |
| PHP            | 8.4.19 (host)  | evidence/logs/phase1-env-validation.log |
| Composer       | 2.8.6          | evidence/logs/phase1-env-validation.log |
| Node.js        | v25.9.0        | evidence/logs/phase1-env-validation.log |
| npm            | 11.13.0        | evidence/logs/phase1-env-validation.log |
| Playwright     | 1.59.1         | evidence/logs/phase1-env-validation.log |
| Docker         | 29.2.1         | evidence/logs/phase1-env-validation.log |
| Docker Compose | v5.0.2         | evidence/logs/phase1-env-validation.log |
| PHPUnit        | 12.5.14        | evidence/logs/phase1-env-validation.log |

## 2. Setup Steps

- All tools installed and available in PATH
- Docker containers build and start successfully
- Composer and npm dependencies install without error
- Playwright browsers installed
- PHPUnit and Playwright tests can be executed

## 3. Validation Output

See `evidence/logs/phase1-env-validation.log` for full command outputs and version details.

## 4. Issues

- Host-side Composer install is fragile due missing zip/unzip tooling; container-based composer install was used to restore a clean vendor state.
- Coverage extensions (`pcov`/`xdebug`) are not enabled on host runtime, so line coverage metrics remain blocked.

## 5. Summary

The QA environment is validated for baseline QA layer (Phase 1) execution. Runtime and smoke test failures were resolved, metrics were captured, and remaining work is limited to enabling a coverage driver for line coverage reporting.

---

_All data above is based on real command outputs and logs. See evidence for details._



