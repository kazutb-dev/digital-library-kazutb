# CHANGELOG — QA experimental evaluation layer (Phase 3)

All notable changes to QA experimental evaluation layer (Phase 3) artifacts are documented here.

---

## [experimental evaluation layer (Phase 3) Part 3 + Final Synthesis] — 2026-05-13

### Added

Chaos campaign automation:

- `chaos/scripts/run-phase3-chaos.ps1` — bounded chaos scenarios with fault/recovery phase collection and propagation probe

Chaos raw results:

- `chaos/results/phase3-chaos-summary-20260513-142323.csv`
- `chaos/results/phase3-chaos-summary-20260513-142323.json`
- `chaos/results/phase3-chaos-requests-20260513-142323.csv`
- `chaos/results/phase3-chaos-requests-20260513-142323.json`

Chaos metrics:

- `metrics/phase3-chaos-scenarios.csv`
- `metrics/phase3-chaos-scenarios.json`
- `metrics/phase3-chaos-results.csv`
- `metrics/phase3-chaos-results.json`
- `metrics/phase3-chaos-metrics.csv`
- `metrics/phase3-chaos-metrics.json`

Observed-vs-expected package:

- `metrics/phase3-observed-vs-expected.csv`
- `metrics/phase3-observed-vs-expected.json`

Chaos and synthesis charts:

- `charts/phase3-chaos-availability-chart.csv`
- `charts/phase3-chaos-recovery-chart.csv`
- `charts/phase3-chaos-error-propagation-chart.csv`
- `charts/phase3-chaos-chart-instructions.md`

Part 3 and synthesis documentation:

- `docs/phase3-chaos-test-plan.md`
- `docs/phase3-chaos-execution-report.md`
- `docs/phase3-chaos-metrics-report.md`
- `docs/phase3-chaos-lessons-learned.md`
- `docs/phase3-experimental-setup.md`
- `docs/phase3-observed-vs-expected.md`
- `docs/phase3-experimental-final-report.md`

Evidence references and transparency notes:

- `evidence/references/phase3-chaos-command-log.md`
- `evidence/screenshots/README.md`

Paper-ready publication assets:

- `qa/paper-assets/README.md`
- `qa/paper-assets/generate-paper-figures.py`
- `qa/paper-assets/figures/figure-index.md`
- `qa/paper-assets/figures/phase1/phase1_manual_vs_automated_inventory.png`
- `qa/paper-assets/figures/phase2/phase2_automation_coverage_by_module.png`
- `qa/paper-assets/figures/midterm/midterm_defects_vs_risk.png`
- `qa/paper-assets/figures/phase3/phase3_performance_response_times.png`
- `qa/paper-assets/figures/phase3/phase3_mutation_score_by_module.png`
- `qa/paper-assets/figures/phase3/phase3_chaos_availability_by_scenario.png`
- `qa/paper-assets/figures/summary/summary_quality_progression_across_phases.png`
- `qa/paper-assets/figures/summary/summary_quality_progression_source.csv`

Archive preparation:

- `qa/archive-preparation-report.md`

### Updated

- `README.md` — now includes Part 3 and complete package status.
- `TRACEABILITY.md` — now includes full chaos and synthesis traceability.
- `docs/phase3-final-summary.md` — upgraded to integrated final summary.

### Part 3 Result Summary

| Metric                          |           Value |
| ------------------------------- | --------------: |
| Chaos run ID                    | 20260513-142323 |
| Scenarios executed              |               4 |
| Fault-phase availability        |          50.00% |
| Recovery-phase availability     |         100.00% |
| Mean MTTR proxy                 |      3391.02 ms |
| Isolated propagation scenarios  |               4 |
| Cascading propagation scenarios |               0 |

### Synthesis Notes

- All newly generated figures are based on factual measured CSV data.
- Synthetic fault models are explicitly documented in Part 3 reports.
- No fabricated screenshots were introduced.

---

## [experimental evaluation layer (Phase 3) Part 2] — 2026-05-13

### Added

Mutation campaign automation:

- `mutation/plans/run-phase3-mutation.ps1` — controlled manual mutation executor with source restoration and per-mutant targeted tests

Mutation run artifacts:

- `mutation/results/mutation-run-20260513-140552.json`

Mutation metrics:

- `metrics/phase3-mutants.csv`
- `metrics/phase3-mutants.json`
- `metrics/phase3-mutation-results.csv`
- `metrics/phase3-mutation-results.json`
- `metrics/phase3-mutation-score.csv`
- `metrics/phase3-mutation-score.json`
- `metrics/phase3-mutation-gaps.csv`
- `metrics/phase3-mutation-gaps.json`

Mutation charts:

- `charts/phase3-mutation-score-chart.csv`
- `charts/phase3-mutant-status-chart.csv`
- `charts/phase3-mutation-chart-instructions.md`

Mutation documentation:

- `docs/phase3-mutation-plan.md`
- `docs/phase3-mutation-execution-report.md`
- `docs/phase3-mutation-score-report.md`
- `docs/phase3-mutation-gap-analysis.md`
- `docs/phase3-mutation-recommendations.md`
- `docs/phase3-mutation-final-summary.md`

Evidence references:

- `evidence/references/phase3-mutation-command-log.md`
- `evidence/logs/phase3-mutation-*.log` (14 logs)

### Mutation Result Summary

| Metric                 |           Value |
| ---------------------- | --------------: |
| Run ID                 | 20260513-140552 |
| Mutants Created        |              14 |
| Mutants Killed         |              12 |
| Mutants Survived       |               2 |
| Inconclusive           |               0 |
| Overall Mutation Score |          85.71% |

Module-level scores:

- Integration Boundary Middleware: 100.00%
- Integration Reservations Read API: 100.00%
- Integration Reservations Mutate API: 75.00%
- Integration Document Management API: 75.00%

Surviving mutants:

- MUT-MUT-004
- MUT-DOC-004

Key gap class:

- Weak assertions on controller-to-service context payload fields.

---

## [experimental evaluation layer (Phase 3) Part 1] — 2026-05-13

### Added

**Scenarios (9 defined)**

- `metrics/phase3-scenarios.csv` — 9 load scenario definitions across 3 modules
- `metrics/phase3-scenarios.json` — JSON equivalent

**Performance Scripts (4 files)**

- `performance/scripts/perf-catalog-api.ps1` — S01-S04, S09 (Catalog/Public API)
- `performance/scripts/perf-web-public.ps1` — S05-S07 (External Resources + Web Catalog)
- `performance/scripts/perf-integration-boundary.ps1` — S08 (Integration API boundary)
- `performance/scripts/run-phase3-performance.ps1` — Master orchestrator

**Execution Results (3 run files)**

- `performance/results/catalog-api-perf-20260513-133438.json` — Run ID 20260513-133438
- `performance/results/web-public-perf-20260513-134213.json` — Run ID 20260513-134213
- `performance/results/integration-boundary-perf-20260513-134513.json` — Run ID 20260513-134513

**Execution Logs (3 files)**

- `evidence/logs/perf-catalog-api.log`
- `evidence/logs/perf-web-public.log`
- `evidence/logs/perf-integration-boundary.log`

**Metrics (6 files)**

- `metrics/phase3-performance-results.csv` — Row per scenario, all latency + throughput
- `metrics/phase3-performance-results.json`
- `metrics/phase3-resource-observations.csv` — 11 host resource snapshots
- `metrics/phase3-resource-observations.json`
- `metrics/phase3-bottlenecks.csv` — 8 bottleneck entries
- `metrics/phase3-bottlenecks.json`

**Charts (5 files)**

- `charts/phase3-response-time-chart.csv` — avg/median/p95/threshold per scenario
- `charts/phase3-throughput-chart.csv` — rps per scenario
- `charts/phase3-error-rate-chart.csv` — success/fail counts per scenario
- `charts/phase3-resource-usage-chart.csv` — host resource snapshots for line chart
- `charts/phase3-chart-instructions.md` — rendering guide for Excel/Google Sheets

**Documentation (7 docs + root files)**

- `docs/phase3-performance-test-plan.md`
- `docs/phase3-performance-methodology.md`
- `docs/phase3-execution-report.md`
- `docs/phase3-metrics-report.md`
- `docs/phase3-bottleneck-analysis.md`
- `docs/phase3-recommendations.md`
- `docs/phase3-final-summary.md`
- `README.md`
- `TRACEABILITY.md`
- `CHANGELOG.md` (this file)

### Test Results Summary

| Scenario            | Result   | Avg (ms)   | p95 (ms) |
| ------------------- | -------- | ---------- | -------- |
| S01-NL-CATALOG      | PASS     | 3 443.66   | 3 553.05 |
| S02-PL-CATALOG      | PASS     | 3 497.38   | 3 796.10 |
| S03-NL-SUBJECTS     | PASS     | 3 359.51   | 3 969.37 |
| S04-SL-MIXED        | PASS     | 3 464.90   | 3 659.68 |
| S05-NL-EXTRES       | PASS     | 3 077.66   | 3 334.84 |
| S06-NL-WEBCATALOG   | PASS     | 3 784.74   | 4 037.13 |
| S07-PL-WEBCATALOG   | PASS     | 3 639.72   | 4 014.71 |
| S08-BND-INTEGRATION | **FAIL** | 3 237.12\* | 3 661.04 |
| S09-END-CATALOG     | PASS     | 3 454.20   | 3 562.67 |

\*S08 avg = middleware_overhead_avg; threshold = 2 000ms; overage = 62%

### Bottlenecks Identified (8)

- BN-001 CRITICAL — Uniform high latency 3.1–3.8s (all modules)
- BN-002 CRITICAL — Integration middleware rejection overhead 3 237ms (S08 FAIL)
- BN-003 HIGH — Web catalog 130KB HTML payload
- BN-004 HIGH — Throughput ceiling 0.26–0.33 rps at 1 VU
- BN-005 MEDIUM — /api/v1/subjects p95 variance (3 969ms)
- BN-006 MEDIUM — /news HTTP 500 (untestable)
- BN-007 MEDIUM — /api/login timeout (untestable)
- BN-008 LOW — No OPcache warm-up benefit in endurance

### Recommendations Added (10)

- R-001 Enable PHP OPcache (P1)
- R-002 Add Redis response caching (P1)
- R-003 Refactor integration middleware short-circuit (P1)
- R-004 Enable Nginx Gzip compression (P2)
- R-005 Blade partial caching for /catalog (P2)
- R-006 PHP-FPM pool tuning (P2)
- R-007 Add index on subjects table (P2)
- R-008 Fix /news HTTP 500 (P3)
- R-009 Diagnose /api/login timeout (P3)
- R-010 Full concurrent k6 test on Linux staging (P3)

---

## Previous Phases

- automation and CI governance layer (Phase 2) Part 3 (commit ea80e55) — Integration and API functional testing complete
- automation and CI governance layer (Phase 2) Intermediate Empirical Review (commit 87ac5bb) — Intermediate Empirical Review QA report complete
- baseline QA layer (Phase 1) — Unit and feature test baseline

---

_KazUTB Digital Library — QA Team — 2026-05-13_
