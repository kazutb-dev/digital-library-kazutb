# experimental evaluation layer (Phase 3) — Traceability Matrix

Project: KazUTB Digital Library
Date: 2026-05-13

---

## Part 3 Chaos Traceability

| Chaos Requirement                          | Artifact                                                                                | Evidence                                                 |
| ------------------------------------------ | --------------------------------------------------------------------------------------- | -------------------------------------------------------- |
| Define bounded synthetic chaos scenarios   | `metrics/phase3-chaos-scenarios.csv` / `metrics/phase3-chaos-scenarios.json`            | `docs/phase3-chaos-test-plan.md`                         |
| Execute chaos campaign                     | `chaos/scripts/run-phase3-chaos.ps1`                                                    | `evidence/logs/phase3-chaos-*.log`                       |
| Preserve raw request and scenario payloads | `chaos/results/phase3-chaos-requests-20260513-142323.csv` and summary files             | `chaos/results/phase3-chaos-summary-20260513-142323.csv` |
| Derive scenario-level resilience metrics   | `metrics/phase3-chaos-results.csv` / `metrics/phase3-chaos-results.json`                | `docs/phase3-chaos-execution-report.md`                  |
| Derive aggregate resilience metrics        | `metrics/phase3-chaos-metrics.csv` / `metrics/phase3-chaos-metrics.json`                | `docs/phase3-chaos-metrics-report.md`                    |
| Verify error propagation behavior          | `charts/phase3-chaos-error-propagation-chart.csv`                                       | `docs/phase3-chaos-lessons-learned.md`                   |
| Provide chart-ready chaos datasets         | `charts/phase3-chaos-availability-chart.csv` / `charts/phase3-chaos-recovery-chart.csv` | `charts/phase3-chaos-chart-instructions.md`              |
| Record execution command evidence          | `evidence/references/phase3-chaos-command-log.md`                                       | Run IDs and command list                                 |

---

## Experimental Consolidation Traceability

| Consolidation Requirement                    | Artifact                                            | Evidence                                  |
| -------------------------------------------- | --------------------------------------------------- | ----------------------------------------- |
| Document final setup and runtime context     | `docs/phase3-experimental-setup.md`                 | Version and environment table             |
| Compare observed vs expected outcomes        | `metrics/phase3-observed-vs-expected.csv` / `.json` | `docs/phase3-observed-vs-expected.md`     |
| Produce integrated final experimental report | `docs/phase3-experimental-final-report.md`          | Cross-part findings and recommendations   |
| Publish paper-ready figures                  | `qa/paper-assets/figures/` PNG assets               | `qa/paper-assets/figures/figure-index.md` |

---

## Part 1 Performance Traceability

| Requirement                             | Artifact                                 | Evidence                               |
| --------------------------------------- | ---------------------------------------- | -------------------------------------- |
| Design scenarios and bounded load model | `metrics/phase3-scenarios.csv`           | `docs/phase3-performance-test-plan.md` |
| Execute performance scripts             | `performance/scripts/*.ps1`              | `evidence/logs/perf-*.log`             |
| Collect measured metrics                | `metrics/phase3-performance-results.csv` | `performance/results/*.json`           |
| Analyze bottlenecks                     | `metrics/phase3-bottlenecks.csv`         | `docs/phase3-bottleneck-analysis.md`   |
| Provide recommendations                 | `docs/phase3-recommendations.md`         | Recommendation table and priorities    |

---

## Part 2 Mutation Traceability

| Mutation Requirement                       | Artifact                                                                           | Evidence                                       |
| ------------------------------------------ | ---------------------------------------------------------------------------------- | ---------------------------------------------- |
| Select critical modules with bounded scope | `docs/phase3-mutation-plan.md`                                                     | Module selection table + bounded rationale     |
| Define mutant inventory                    | `metrics/phase3-mutants.csv` / `metrics/phase3-mutants.json`                       | 14-mutant registry                             |
| Execute tests against each mutant          | `metrics/phase3-mutation-results.csv` / `metrics/phase3-mutation-results.json`     | `evidence/logs/phase3-mutation-*.log`          |
| Preserve raw mutation run payload          | `mutation/results/mutation-run-20260513-140552.json`                               | Script-generated run object                    |
| Compute module and overall mutation score  | `metrics/phase3-mutation-score.csv` / `metrics/phase3-mutation-score.json`         | `docs/phase3-mutation-score-report.md`         |
| Analyze surviving mutants and gaps         | `metrics/phase3-mutation-gaps.csv` / `metrics/phase3-mutation-gaps.json`           | `docs/phase3-mutation-gap-analysis.md`         |
| Produce actionable improvements            | `docs/phase3-mutation-recommendations.md`                                          | P1/P2/P3 recommendations                       |
| Provide chart-ready mutation data          | `charts/phase3-mutation-score-chart.csv` / `charts/phase3-mutant-status-chart.csv` | `charts/phase3-mutation-chart-instructions.md` |

---

## Mutation Run Mapping

| Run ID          | Mutants | Killed | Survived | Result File                           |
| --------------- | ------: | -----: | -------: | ------------------------------------- |
| 20260513-140552 |      14 |     12 |        2 | `metrics/phase3-mutation-results.csv` |

## Chaos Run Mapping

| Run ID          | Scenarios | Fault Availability (%) | Recovery Availability (%) | Cascading Failures | Result File                        |
| --------------- | --------: | ---------------------: | ------------------------: | -----------------: | ---------------------------------- |
| 20260513-142323 |         4 |                  50.00 |                    100.00 |                  0 | `metrics/phase3-chaos-results.csv` |

---

## Surviving Mutants to Gap Artifacts

| Mutant ID   | Status   | Gap Artifact Row                   | Recommendation                                         |
| ----------- | -------- | ---------------------------------- | ------------------------------------------------------ |
| MUT-MUT-004 | Survived | `metrics/phase3-mutation-gaps.csv` | Strengthen `ReservationMutateTest` context assertions  |
| MUT-DOC-004 | Survived | `metrics/phase3-mutation-gaps.csv` | Strengthen `DocumentManagementTest` context assertions |

---

KazUTB Digital Library — QA experimental evaluation layer (Phase 3)
