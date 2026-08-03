# Intermediate Empirical Review Metrics Report

## Required metric outcomes

1. Coverage
- High-risk module presence: 7/7 modules (100%).
- Weighted high-risk check coverage: 75.0% (27/36).
- Modules below 70%: Circulation/Operations (66.7), Integration API (50), Admin operations (50).

2. Defect detection
- Observed defects/issues: 9.
- Failed high-risk modules: 6/7.
- Defect concentration aligns with high-priority risk areas (catalog/public, integration, authorization/ops).

3. Efficiency
- Phase2 combined measured runtime: 154.578s.
- Intermediate Empirical Review newly added test execution: 24.15s total (11.75s PHPUnit + 12.4s Playwright).
- Pipeline runtime aggregate value was not extracted as a single numeric run total from available local artifacts.

4. Stability
- Confirmed flaky rate: not measurable from available repeated-run history.
- Instability candidates are tracked with classification and explicit evidence limitations.

5. Governance
- Current enforced gate distribution: pass=5, fail=3, warn=1.

## Source tables

- qa/midterm/metrics/midterm-required-metrics.csv
- qa/midterm/metrics/midterm-flaky-tests.csv
- qa/midterm/metrics/midterm-quality-gate-evaluation.csv
