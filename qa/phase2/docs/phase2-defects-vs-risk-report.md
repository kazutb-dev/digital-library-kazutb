# automation and CI governance layer (Phase 2) Defects vs Expected Risk Report

Source references:

- qa/phase1/docs/phase1-risk-register.md
- qa/phase2/evidence/logs/phase2-automation-summary.log
- qa/phase2/metrics/phase2-defects-vs-risk.csv

| Module/Feature | High-Risk Level | Expected Defects | Defects Found | Pass/Fail | Evidence | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| Authentication | P0 | Low-to-medium | 0 | Pass | phase2-api-tests.log | auth guard smoke checks passed |
| Authorization/RBAC | P1 | Medium | 3 | Fail | phase2-phpunit.log | targeted PHPUnit access-boundary assertions failed |
| Catalog/Public routes | P0 | Medium | 1 | Fail | phase2-playwright.log | /news returned 500 in UI smoke |
| Circulation/Operations | P0 | Medium | 1 | Fail | phase2-api-tests.log | operations guard expectation mismatch surfaced |
| Integration API | P1 | Medium | 2 | Fail | phase2-api-tests.log | integration endpoints returned 404 |
| Admin operations | P1 | Medium | 1 | Fail | phase2-api-tests.log | admin integrations route returned 404 for anonymous request |
| Data/Coverage readiness | P0 (R13) | Medium | 1 | Fail | phase2-coverage.log | coverage driver unavailable in local measured run |

Summary:

- Total defects/issues observed: 9.
- Failed risk areas: 6/7 modules.
- Risk forecast alignment: high-risk concentration from baseline QA layer (Phase 1) is confirmed in authorization, public/catalog, operations, and integration paths.
