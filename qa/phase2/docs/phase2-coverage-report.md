# automation and CI governance layer (Phase 2) Automation Coverage Report

Source references:

- qa/phase1/docs/phase1-risk-register.md
- qa/phase2/docs/phase2-automated-test-cases.md
- qa/phase2/evidence/logs/phase2-automation-summary.log
- qa/phase2/metrics/phase2-automation-coverage.csv

Coverage rule used:

- Coverage percent per module = automated_checks / total_planned_high_risk_checks * 100.

| Module/Feature | High-Risk Function | Test Automated | Automation Layer | Automated Checks | Total Planned High-Risk Checks | Coverage % | Evidence | Notes |
| --- | --- | --- | --- | --- | --- | --- | --- | --- |
| Authentication | Session and protected endpoint guard behavior | Yes | API and Feature | 3 | 3 | 100.0 | phase2-api-tests.log | 3/3 checks automated and executed |
| Authorization/RBAC | Guest access to protected role routes | Partial | UI and API | 6 | 8 | 75.0 | phase2-playwright.log | 6/8 mapped checks automated; gaps remain |
| Catalog/Public | Public route and API stability | Partial | UI and API | 7 | 8 | 87.5 | phase2-playwright.log | one route currently failing |
| Circulation/Operations | Internal operations access boundaries | Partial | API and UI | 4 | 6 | 66.7 | phase2-api-tests.log | partial automated coverage only |
| Integration API | Boundary and reservations integration behavior | Partial | API | 2 | 4 | 50.0 | phase2-api-tests.log | automated checks exist but endpoint instability present |
| Admin operations | Admin route guard and integration access | Partial | API and UI | 2 | 4 | 50.0 | phase2-api-tests.log | 2/4 checks automated |
| Shortlist/Member account | Guest protection on member endpoints | Yes | API and UI | 3 | 3 | 100.0 | phase2-playwright.log | 3/3 checks automated and executed |

Derived totals:

- Module-level automation presence: 7/7 modules have at least partial automated coverage.
- Weighted high-risk check coverage: 27/36 = 75.0 percent.

This report captures factual execution state and intentionally distinguishes full from partial automation.
