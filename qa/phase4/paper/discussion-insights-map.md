# Discussion Insights Map

Date: 2026-05-13

## Purpose

Prepare evidence-backed discussion threads without overstating claims.

## Insight Threads

| Insight ID | Discussion Insight                                                                 | Supporting Evidence                                                                                                                         | Support Strength | Limitations/Caveats                                     |
| ---------- | ---------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------------------------- | ---------------- | ------------------------------------------------------- |
| DI-01      | Risk-based prioritization was directionally correct across phases                  | qa/phase1/docs/phase1-risk-register.md; qa/phase2/docs/phase2-metrics-interpretation.md; qa/midterm/docs/midterm-final-report.md            | High             | Depends on module-level aggregation granularity         |
| DI-02      | Automation breadth improved faster than depth                                      | qa/phase2/metrics/phase2-automation-coverage.csv; qa/phase2/docs/phase2-metrics-report.md                                                   | High             | Coverage quality cannot be inferred from presence alone |
| DI-03      | Governance surfaced release blockers early                                         | qa/phase2/metrics/phase2-quality-gate-results.csv; qa/phase2/TRACEABILITY.md                                                                | High             | Current gate design reflects local/project context      |
| DI-04      | Intermediate Empirical Review reassessment reduced uncertainty but did not eliminate critical weaknesses | qa/midterm/metrics/midterm-required-metrics.csv; qa/midterm/docs/midterm-comparative-analysis.md                                            | Medium-High      | Flaky-rate measurement remained limited                 |
| DI-05      | Phase3 experiments improved evidence depth for reliability and resilience claims   | qa/phase3/docs/phase3-experimental-final-report.md; qa/phase3/metrics/phase3-mutation-score.csv; qa/phase3/metrics/phase3-chaos-metrics.csv | High             | Bounded synthetic constraints apply                     |
| DI-06      | Integration boundary remains the most persistent cross-phase risk concentration    | qa/phase2/metrics/phase2-defects-vs-risk.csv; qa/phase3/metrics/phase3-performance-results.csv; qa/phase3/docs/phase3-final-summary.md      | High             | Requires future production-scale validation             |

## Discussion Figure Support

1. qa/paper-assets/figures/summary/summary_quality_progression_across_phases.png
2. qa/paper-assets/figures/phase3/phase3_performance_response_times.png
3. qa/paper-assets/figures/phase3/phase3_mutation_score_by_module.png
4. qa/paper-assets/figures/phase3/phase3_chaos_availability_by_scenario.png

## Discussion Writing Risks

1. Over-generalizing from local/bounded experiments.
2. Treating gate outcomes as universal quality definitions.
3. Ignoring blocked or unmeasured metrics from earlier phases.

---

KazUTB Digital Library - research synthesis layer (Phase 4) Part 1
