# automation and CI governance layer (Phase 2) Metrics Report

## Purpose

This report consolidates quantitative automation and CI governance layer (Phase 2) outcomes so they can be used directly in program deliverable submission and research-paper results sections.

## Data sources

- qa/phase2/metrics/phase2-automation-coverage.csv
- qa/phase2/metrics/phase2-execution-time.csv
- qa/phase2/metrics/phase2-defects-vs-risk.csv
- qa/phase2/metrics/phase2-test-execution-log.csv
- qa/phase2/evidence/logs/phase2-automation-summary.log
- qa/phase2/evidence/logs/phase2-quality-gates.log

## Metric summary table

| Metric Category | Key Value(s) | Interpretation |
| --- | --- | --- |
| Automation coverage (weighted high-risk checks) | 27/36 = 75.0 percent | High-risk automation exists across all modules, but depth remains uneven. |
| Module-level automation presence | 7/7 modules with at least partial automation | No high-risk module is fully uninstrumented. |
| API smoke runtime | 38.435s total, 3.844s avg/case | Runtime is below current threshold and suitable for frequent CI execution. |
| UI smoke runtime | 54.885s total, 4.989s avg/case | UI suite is bounded, with one slow failing /news case. |
| Targeted PHPUnit runtime | 61.258s total, 1.494s avg/test | Runtime is acceptable; stability remains the key issue. |
| Defects-vs-risk total | 9 observed defects/issues across 7 modules | Defects concentrate in previously high-risk areas from baseline QA layer (Phase 1). |
| Current enforced quality gates | pass=5, fail=3, warn=1 | Governance model is active and currently blocks release confidence. |

## Coverage details

- Strongest modules: Authentication and Shortlist/Member account (100 percent each).
- Weakest modules: Integration API and Admin operations (50 percent each).
- Weighted check coverage highlights that module presence alone overstates true readiness.

## Execution-time details

- Combined measured runtime: 154.578s across API, UI, and PHPUnit suites.
- Slowest single explicit case in log dataset: P2-UI-CAT-004 at approximately 36.9s.
- Current runtime gates pass and are not the blocking factor.

## Defect concentration details

- Highest observed defect counts: Authorization/RBAC (3) and Integration API (2).
- Public route reliability defect (/news HTTP 500) remains a critical externally visible issue.
- Coverage readiness defect persists due local coverage driver limitations.

## Research-ready chart sources

- qa/phase2/metrics/charts/phase2-automation-coverage-chart.csv
- qa/phase2/metrics/charts/phase2-execution-time-chart.csv
- qa/phase2/metrics/charts/phase2-defects-vs-risk-chart.csv
