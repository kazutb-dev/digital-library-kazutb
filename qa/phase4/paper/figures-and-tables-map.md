# Figures and Tables Map

Date: 2026-05-13

## Existing Figure Inventory from qa/paper-assets/figures

| Figure ID | Figure Path                                                                   | Paper Section                       | Analytical Purpose                             | Caption Idea                                                         | Supporting Dataset(s)                                                  |
| --------- | ----------------------------------------------------------------------------- | ----------------------------------- | ---------------------------------------------- | -------------------------------------------------------------------- | ---------------------------------------------------------------------- |
| F1        | qa/paper-assets/figures/phase1/phase1_manual_vs_automated_inventory.png       | Introduction / Methodology Baseline | Show initial manual-to-automation distribution | Baseline test inventory at baseline QA layer (Phase 1) entry                             | qa/phase1/metrics/phase1-baseline-metrics.csv                          |
| F2        | qa/paper-assets/figures/phase2/phase2_automation_coverage_by_module.png       | Results (automation and CI governance layer (Phase 2))                   | Show uneven depth across modules               | automation and CI governance layer (Phase 2) automation coverage by high-risk module                      | qa/phase2/metrics/phase2-automation-coverage.csv                       |
| F3        | qa/paper-assets/figures/midterm/midterm_defects_vs_risk.png                   | Results (Intermediate Empirical Review)                   | Compare planned and observed risk              | Intermediate Empirical Review shift between planned and observed risk profile              | qa/midterm/charts/midterm-planned-vs-actual-chart.csv                  |
| F4        | qa/paper-assets/figures/phase3/phase3_performance_response_times.png          | Results (experimental evaluation layer (Phase 3) Performance)       | Show latency shape across scenarios            | experimental evaluation layer (Phase 3) performance: average and P95 response times by scenario      | qa/phase3/charts/phase3-response-time-chart.csv                        |
| F5        | qa/paper-assets/figures/phase3/phase3_mutation_score_by_module.png            | Results (experimental evaluation layer (Phase 3) Mutation)          | Compare mutation effectiveness by module       | experimental evaluation layer (Phase 3) mutation score distribution across targeted modules          | qa/phase3/charts/phase3-mutation-score-chart.csv                       |
| F6        | qa/paper-assets/figures/phase3/phase3_chaos_availability_by_scenario.png      | Results (experimental evaluation layer (Phase 3) Chaos)             | Show fault vs recovery behavior                | experimental evaluation layer (Phase 3) chaos campaign: fault and recovery availability per scenario | qa/phase3/charts/phase3-chaos-availability-chart.csv                   |
| F7        | qa/paper-assets/figures/summary/summary_quality_progression_across_phases.png | Discussion / Conclusion             | Show cross-phase maturity trend                | Cross-phase quality indicator progression from baseline QA layer (Phase 1) to experimental evaluation layer (Phase 3)    | qa/paper-assets/figures/summary/summary_quality_progression_source.csv |

## Proposed Table Inventory (for drafting in Part 2)

| Table ID | Planned Section   | Purpose                                   | Source Files                                                                                                                                                                                 |
| -------- | ----------------- | ----------------------------------------- | -------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- |
| T1       | Methodology       | Summarize phase-by-phase method evolution | qa/phase1/docs/phase1-test-strategy.md; qa/midterm/docs/midterm-methodology.md; qa/phase3/docs/phase3-performance-methodology.md                                                             |
| T2       | Results           | Consolidate key metrics per phase         | qa/phase1/metrics/phase1-baseline-metrics.csv; qa/phase2/metrics/phase2-automation-coverage.csv; qa/midterm/metrics/midterm-required-metrics.csv; qa/phase3/metrics/phase3-chaos-metrics.csv |
| T3       | Claims Governance | Show claim strength and caveats           | qa/phase4/paper/claims-to-evidence-matrix.md                                                                                                                                                 |

## Part 2 Draft Usage Status

| Section Draft         | Figure/Table Placeholders Used |
| --------------------- | ------------------------------ |
| abstract-draft.md     | none                           |
| introduction-draft.md | F1, F7                         |
| methodology-draft.md  | F1, F7, T1                     |
| results-draft.md      | F2, F3, F4, F5, F6, F7, T2     |
| discussion-draft.md   | F7, T3                         |
| full-paper-draft.md   | F1-F7 and T1-T3 placeholders   |

## Missing Figures/Tables Identified

1. Missing table template for direct side-by-side phase KPI summary (to be authored in Part 2).
2. Optional missing figure: risk hot-spot persistence heatmap using phase2/midterm/phase3 integration-risk indicators.
3. Optional missing appendix table: threats-to-validity matrix.

## Proposed Figure Order in Paper

1. F1 - baseline QA layer (Phase 1) baseline inventory.
2. F2 - automation and CI governance layer (Phase 2) automation coverage by module.
3. F3 - Intermediate Empirical Review planned vs observed risk.
4. F4 - experimental evaluation layer (Phase 3) performance response times.
5. F5 - experimental evaluation layer (Phase 3) mutation score by module.
6. F6 - experimental evaluation layer (Phase 3) chaos availability by scenario.
7. F7 - Cross-phase quality progression summary.

---

KazUTB Digital Library - research synthesis layer (Phase 4) Part 1
