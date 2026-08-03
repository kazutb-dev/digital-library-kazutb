# baseline QA layer (Phase 1) Defect Log

**Author:** Murat Almas
**Date:** 2026-05-13

| ID  | Severity | Impact | Evidence File(s)                    | Current Status | Notes                                                                                           |
| --- | -------- | ------ | ----------------------------------- | -------------- | ----------------------------------------------------------------------------------------------- |
| D1  | S2       | High   | evidence/logs/phase1-phpunit.log    | Resolved       | Vendor/autoload issue fixed via clean container-based composer install                          |
| D2  | S2       | High   | evidence/logs/phase1-playwright.log | Resolved       | Outdated smoke assertions updated to current canonical selectors                                |
| D3  | S3       | Medium | evidence/logs/phase1-metrics.log    | Open           | Coverage drivers (`pcov`/`xdebug`) missing on host; line coverage metrics not captured          |
| D4  | S3       | Medium | evidence/logs/phase1-phpunit.log    | Resolved       | Windows composer script quoting issue bypassed using direct `php artisan test --filter` command |

**Legend:**

- **Severity:**
    - S1: System down/data loss
    - S2: Major function broken
    - S3: Minor function broken
    - S4: Cosmetic/typo
- **Impact:**
    - High: Critical user flow affected
    - Medium: Secondary flow affected
    - Low: Minor issue

---

_All data above is based on repository inspection and logs._



