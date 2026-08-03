# automation and CI governance layer (Phase 2) Execution Time Report

Measured from:

- qa/phase2/evidence/logs/phase2-api-tests.log
- qa/phase2/evidence/logs/phase2-phpunit.log
- qa/phase2/evidence/logs/phase2-playwright.log
- qa/phase2/metrics/phase2-execution-time.csv

| Module/Feature | Number of Test Cases | Average per Test Case (s) | Total Time (s) | Environment | Gate Mapping | Notes |
| --- | --- | --- | --- | --- | --- | --- |
| API smoke critical suites | 10 | 3.844 | 38.435 | local | P2-QG-007 | from phase2-api-tests.log runner_elapsed_ms=38435.05 |
| UI smoke critical routes | 11 | 4.989 | 54.885 | local | P2-QG-007 | from phase2-playwright.log elapsed=54.885s |
| Targeted PHPUnit high-risk subset | 41 | 1.494 | 61.258 | local | P2-QG-007 | from phase2-phpunit.log elapsed=61.258s |
| Combined automation run | 62 | 2.493 | 154.578 | local | Informational | sum of measured local suite runtimes |
| CI execution benchmark | n/a | n/a | not measured in current dataset | ci | Informational | keep as tracked gap for next iteration |

Bottleneck observations:

- Single-case outlier: P2-UI-CAT-004 (/news) at approximately 36.9s and failing with HTTP 500.
- API and UI suite totals remain below current warn-level thresholds.
- PHPUnit subset is bounded but still stability-sensitive due environment assumptions.

Operational interpretation:

- Runtime thresholds currently pass under P2-QG-007.
- Continue collecting CI runtime data before tightening thresholds.
