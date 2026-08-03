# Intermediate Empirical Review Traceability Matrix

| Intermediate Empirical Review Requirement | Artifact | Evidence |
| --- | --- | --- |
| Task 1.1 risk reassessment | docs/midterm-risk-reassessment.md; metrics/midterm-risk-reassessment.csv | phase1-risk-register + phase2 failed tests/gates |
| Task 1.2 failed tests extraction | metrics/midterm-failed-tests.csv | qa/phase2/metrics/phase2-test-execution-log.csv |
| Task 1.2 flaky/instability analysis | metrics/midterm-flaky-tests.csv | qa/phase2/metrics/phase2-test-execution-log.csv; qa/midterm/evidence/logs/midterm-new-playwright.log |
| Task 1.2 coverage gaps | metrics/midterm-coverage-gaps.csv | qa/phase2/metrics/phase2-automation-coverage.csv; qa/phase2/evidence/logs/phase2-coverage.log |
| Task 1.2 unexpected behavior | metrics/midterm-unexpected-behavior.csv | phase2 execution logs and gate results |
| Task 1.3 risk dimensions | docs/midterm-methodology.md; metrics/midterm-risk-dimensions.csv | reassessment + coverage + failures |
| Task 2.1 automation expansion | docs/midterm-automation-expansion.md; metrics/midterm-new-tests.csv | tests/* new files + Intermediate Empirical Review new logs |
| Task 2.2 all test levels | docs/midterm-automation-expansion.md | Unit/Integration/E2E execution evidence |
| Task 2.3 CI/CD execution | docs/midterm-ci-cd-execution.md | .github/workflows/ci.yml + phase2 artifacts |
| Task 2.4 quality gate evaluation | docs/midterm-quality-gates-evaluation.md; metrics/midterm-quality-gate-evaluation.csv | qa/phase2/metrics/phase2-quality-gate-results.csv |
| Task 3 metrics collection | docs/midterm-metrics-report.md; metrics/midterm-required-metrics.csv | phase2 metrics + Intermediate Empirical Review execution logs |
| Task 4 comparative analysis | docs/midterm-comparative-analysis.md; metrics/midterm-planned-vs-actual.csv | phase1 plan vs phase2/midterm outcomes |
| Task 5 final report package | docs/midterm-final-report.md; reports/midterm-report-summary.md | All Intermediate Empirical Review docs/metrics/charts/evidence |
