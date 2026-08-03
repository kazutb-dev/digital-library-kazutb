# Intermediate Empirical Review Risk Reassessment

## Re-evaluated high-risk modules

| Module | Original Risk Score | Observed Issues (A2) | Updated Risk Score | Justification |
| --- | --- | --- | --- | --- |
| Catalog/Public (R2) | 16 | /news returned HTTP 500; fatal public route gate failed | 20 | Public 5xx raises both likelihood and impact for reader-facing availability |
| Integration API (R4,R6) | 10 | Two integration smoke failures (404) and API gate failure | 20 | External integration contract instability is frequent and high-impact |
| Circulation/Operations (R3) | 15 | Ops guard mismatch + module coverage 66.7% (<70%) | 18 | Critical workflow with reduced detectability due weak coverage depth |
| Admin Operations (R5) | 8 | Admin route mismatch in smoke + 50% coverage | 15 | Low detectability and observed defect increase operational risk |
| Coverage Readiness (R13) | 16 | Coverage tooling blocked in local measured environment | 18 | Detectability risk increased due observability gap |

## Evidence basis

- qa/phase1/docs/phase1-risk-register.md
- qa/phase2/metrics/phase2-test-execution-log.csv
- qa/phase2/metrics/phase2-defects-vs-risk.csv
- qa/phase2/metrics/phase2-automation-coverage.csv
- qa/phase2/metrics/phase2-quality-gate-results.csv
- qa/phase2/evidence/logs/phase2-coverage.log

Detailed machine-readable table: qa/midterm/metrics/midterm-risk-reassessment.csv.
