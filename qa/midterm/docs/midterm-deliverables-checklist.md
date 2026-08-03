# Intermediate Empirical Review Deliverables Checklist

| Deliverable | Description | File/Location | Status | Notes/Evidence |
| --- | --- | --- | --- | --- |
| Risk reassessment report | Re-scored top high-risk modules | qa/midterm/docs/midterm-risk-reassessment.md | Complete | Uses phase1 + phase2 evidence |
| Failed test extraction | Failure inventory with frequencies | qa/midterm/metrics/midterm-failed-tests.csv | Complete | Derived from phase2 execution log |
| Flaky/instability analysis | Flaky candidates with classification | qa/midterm/metrics/midterm-flaky-tests.csv | Complete | Explicitly marks insufficient repeated-run data |
| Coverage gaps analysis | Coverage gaps and <70% modules | qa/midterm/metrics/midterm-coverage-gaps.csv | Complete | Includes blocked coverage observability row |
| Unexpected behavior register | New failure patterns/anomalies | qa/midterm/metrics/midterm-unexpected-behavior.csv | Complete | Includes /news 500 and tooling anomalies |
| Risk dimensions mapping | Likelihood/impact/detectability before/after | qa/midterm/metrics/midterm-risk-dimensions.csv | Complete | Methodology-linked evidence |
| Automation expansion catalog | New tests with expected vs actual results | qa/midterm/metrics/midterm-new-tests.csv | Complete | 8 new tests across required categories |
| CI/CD execution analysis | Pipeline behavior and evidence mapping | qa/midterm/docs/midterm-ci-cd-execution.md | Complete | References workflow + logs |
| Quality gate evaluation | Thresholds, status, strictness analysis | qa/midterm/metrics/midterm-quality-gate-evaluation.csv | Complete | Includes root-cause interpretation |
| Required metrics report | Coverage, defects, efficiency, stability, governance | qa/midterm/docs/midterm-metrics-report.md | Complete | Numeric where derivable |
| Comparative analysis | Planned vs actual gap analysis | qa/midterm/metrics/midterm-planned-vs-actual.csv | Complete | Includes corrective actions |
| Final report | Full five-section Intermediate Empirical Review report | qa/midterm/docs/midterm-final-report.md | Complete | Submission-ready narrative |
| Chart datasets + instructions | Reproducible chart input files | qa/midterm/charts/* | Complete | CSV + generation guidance |
| Intermediate Empirical Review execution logs | New test execution evidence | qa/midterm/evidence/logs/* | Complete | Includes phpunit + playwright logs |
| Summary report | Concise review artifact | qa/midterm/reports/midterm-report-summary.md | Complete | Executive synthesis |

## Validation notes

- CSV/JSON parity and reference checks are executed in safe validation stage.
- No fabricated screenshots included; screenshot limitation documented explicitly.
