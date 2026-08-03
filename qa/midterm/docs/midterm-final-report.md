# Intermediate Empirical Review Final Report

## 1. System Description

KazUTB Digital Library is implemented as a Laravel-based modular monolith supporting public discovery, reader/member workflows, librarian/internal operations, and a guarded integration boundary for CRM access. Core technologies include PHP 8.4, Laravel, Playwright, PHPUnit, and GitHub Actions. Key business-critical functions include authentication/session integrity, catalog/news reliability, circulation/admin operations, and integration API contract stability.

## 2. Methodology

The Intermediate Empirical Review used a risk-based empirical loop: baseline QA layer (Phase 1) risk baseline -> automation and CI governance layer (Phase 2) evidence extraction -> risk re-scoring -> test expansion -> governance evaluation. Re-scoring was based on measurable failures, gate outcomes, coverage depth, and detectability constraints. Likelihood increased where repeated failures occurred; impact increased for critical user-facing disruptions; detectability decreased under low coverage or blocked coverage instrumentation.

## 3. Automation Implementation

CI/CD remains GitHub Actions-based with secret scanning, backend verification, browser smoke, artifact upload, and gate evaluation. Intermediate Empirical Review expanded automation by adding 8 new tests: 2 Unit, 4 Integration, 2 E2E. New cases explicitly covered failure scenarios, edge cases, concurrency-like pressure, and invalid user behavior. All new tests were executed with raw logs stored under qa/midterm/evidence/logs.

## 4. Results

Key measured outcomes:

- Weighted high-risk check coverage: 75.0% (27/36).
- Module-level high-risk automation presence: 100% (7/7 modules).
- Observed defects/issues: 9 (with concentration in catalog/public, integration, and access-boundary related modules).
- Current-enforced gate distribution: pass=5, fail=3, warn=1.
- Intermediate Empirical Review new tests execution: 8/8 passed (6 PHPUnit + 2 Playwright).
- Runtime: Phase2 measured total 154.578s; Intermediate Empirical Review new test run 24.15s.

Referenced visuals and chart datasets:

- qa/midterm/charts/midterm-coverage-chart.csv
- qa/midterm/charts/midterm-defects-chart.csv
- qa/midterm/charts/midterm-execution-time-chart.csv
- qa/midterm/charts/midterm-planned-vs-actual-chart.csv

## 5. Discussion

Evidence confirms that risk prioritization was directionally correct, but detectability and stability were overestimated in some critical modules. Coverage breadth alone did not prevent high-impact runtime defects. Integration boundary controls proved effective, while downstream endpoint stability remained insufficient. The next phase should prioritize resolving fail-level gate blockers, deepening module coverage in weak areas, and introducing repeated-run flaky-rate analytics for stronger empirical reliability.
