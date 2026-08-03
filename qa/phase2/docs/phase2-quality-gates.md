# automation and CI governance layer (Phase 2) Quality Gates

## governance format Pass/Fail Criteria

| Quality Gate ID | Metric / Criterion | Threshold / Requirement | Importance | Notes |
| --- | --- | --- | --- | --- |
| QG01 | Code coverage for critical modules | Current enforceable: coverage driver availability must be detected in measured environment. Target next stage: >=80 percent line coverage for critical modules. | High | Local automation and CI governance layer (Phase 2) host run is blocked for line coverage; CI backend job uses pcov for coverage generation. |
| QG02 | Critical defects | Zero unresolved critical defects in enforced critical workflows. | High | Enforced through fail-level gates on critical smoke and fatal public errors. |
| QG03 | Test execution time (TTE) | API smoke <=60s; UI smoke <=90s; targeted PHPUnit subset <=90s. | Medium | Current measured runtimes are below thresholds. |
| QG04 | Regression test success for critical workflows | Current enforceable: >=90 percent pass-rate per critical suite. Target next stage: 100 percent for critical workflows. | High | Threshold is set to current maturity to avoid false certainty while still blocking major regressions. |
| QG05 | Linting / static analysis | Proposed next stage: zero major static analysis violations before merge. | Medium | Not currently implemented in automation and CI governance layer (Phase 2) CI workflow; tracked as proposed gate. |

## Production Engineering Gate Matrix

| Gate ID | Gate Tier | Metric / Criterion | Threshold / Requirement | Importance | Enforcement Level | Measurement Source | Current Result | Status | Rationale / Notes | Implemented in CI |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| P2-QG-001 | current_enforced | API smoke critical endpoint success | >=90 percent pass-rate | High | Fail | qa/phase2/evidence/logs/phase2-api-tests.log | 70.00 percent (7/10) | Fail | Critical API checks currently expose integration and admin boundary failures. | Yes |
| P2-QG-002 | current_enforced | Critical UI/public regression success | >=90 percent pass-rate | High | Fail | qa/phase2/evidence/logs/phase2-playwright.log | 90.91 percent (10/11) | Pass | Overall UI smoke is above gate threshold. | Yes |
| P2-QG-003 | current_enforced | Critical workflow regression subset (PHPUnit) | >=90 percent pass-rate excluding skips | High | Fail | qa/phase2/evidence/logs/phase2-automation-summary.log | 83.78 percent (31/37) | Fail | Authentication/authorization and DB-shape-sensitive tests are still unstable. | Yes |
| P2-QG-004 | current_enforced | Fatal error/crash detection on public routes | 0 unexpected HTTP 5xx in critical public smoke | High | Fail | qa/phase2/evidence/logs/phase2-playwright.log | /news returned HTTP 500 | Fail | Protects production-facing reader experience. | Yes |
| P2-QG-005 | current_enforced | Role/access protection validation | >=85 percent pass-rate across role-boundary smoke checks | High | Fail | qa/phase2/metrics/phase2-test-execution-log.csv | 88.89 percent (8/9) | Pass | Keeps RBAC regressions visible while current suite expands. | Yes |
| P2-QG-006 | current_enforced | Coverage availability status in measured environment | Coverage driver available (pcov or xdebug) | Medium | Warn | qa/phase2/evidence/logs/phase2-coverage.log | blocked | Warn | Local host measurements cannot yet emit reliable line coverage. | Yes |
| P2-QG-007 | current_enforced | Test execution time thresholds | API <=60s; UI <=90s; PHPUnit <=90s | Medium | Warn | qa/phase2/metrics/phase2-execution-time.csv | API 38.435s; UI 54.885s; PHPUnit 61.258s | Pass | Runtime is currently within bounded gate limits. | Yes |
| P2-QG-008 | current_enforced | CI artifact generation completeness | Required automation and CI governance layer (Phase 2) docs and gate/result logs present | Medium | Fail | qa/phase2/evidence/logs/phase2-ci-local-validation.log | required artifacts present | Pass | Prevents silent CI success without QA evidence artifacts. | Yes |
| P2-QG-009 | proposed_next_stage | Critical module line coverage | >=80 percent line coverage for critical modules | High | Informational | build/test-results/clover.xml (CI), coverage trend reporting | Not measured in current local dataset | Proposed | Promote to Fail after stable coverage baseline and tooling parity. | No |
| P2-QG-010 | proposed_next_stage | Regression success for critical workflows | 100 percent pass-rate on critical auth, catalog, and circulation paths | High | Informational | Consolidated CI test matrix reports | Not yet achieved | Proposed | Final production target; current enforceable threshold is staged at >=90 percent. | No |
| P2-QG-011 | proposed_next_stage | Static analysis major violations | 0 major violations | Medium | Informational | Future lint/static-analysis job artifacts | Not implemented | Proposed | Add once tooling and baseline suppression policy are agreed. | No |
| P2-QG-012 | proposed_next_stage | Flaky-test control | <=2 percent flaky rerun rate over rolling 10 CI runs | Medium | Informational | CI rerun analytics and historical run metadata | Not measured | Proposed | Needed before tightening to strict 100 percent regression gates. | No |

## Enforcement notes

- Current CI gate evaluation enforces rows where gate_tier is current_enforced.
- proposed_next_stage gates remain roadmap visibility and do not fail current runs.
- Cross-reference for measured support metrics: docs/phase2-metrics-report.md and docs/phase2-metrics-interpretation.md.
