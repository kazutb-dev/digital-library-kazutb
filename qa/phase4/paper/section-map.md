# Section Map - Prior Work to Paper Sections

Date: 2026-05-13

## Mapping Table

| Paper Section                  | Primary Phase Source               | Key Source Files                                                                                                                                                                              | Candidate Figures                                                                                                     | Risks/Limitations to Mention                                             |
| ------------------------------ | ---------------------------------- | --------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------ |
| Introduction                   | phase1                             | qa/phase1/docs/phase1-risk-register.md; qa/phase1/docs/phase1-test-strategy.md                                                                                                                | phase1_manual_vs_automated_inventory.png                                                                              | Coverage metrics blocked in phase1 host context                          |
| Methodology                    | phase1 + phase2 + Intermediate Empirical Review + phase3 | qa/phase1/docs/phase1-baseline-metrics-methodology.md; qa/phase2/TRACEABILITY.md; qa/midterm/docs/midterm-methodology.md; qa/phase3/docs/phase3-performance-methodology.md                    | summary_quality_progression_across_phases.png                                                                         | Mixed tool contexts; bounded synthetic constraints in phase3             |
| Results - Automation/Gates     | phase2                             | qa/phase2/docs/phase2-metrics-report.md; qa/phase2/metrics/phase2-automation-coverage.csv; qa/phase2/metrics/phase2-quality-gate-results.csv                                                  | phase2_automation_coverage_by_module.png                                                                              | Runtime acceptable but stability blockers remain                         |
| Results - Intermediate Empirical Review Reassessment | Intermediate Empirical Review                            | qa/midterm/docs/midterm-final-report.md; qa/midterm/metrics/midterm-required-metrics.csv; qa/midterm/metrics/midterm-quality-gate-evaluation.csv                                              | midterm_defects_vs_risk.png                                                                                           | Flaky rate not quantitatively measurable                                 |
| Results - Experimental Layer   | phase3                             | qa/phase3/docs/phase3-experimental-final-report.md; qa/phase3/metrics/phase3-performance-results.csv; qa/phase3/metrics/phase3-mutation-score.csv; qa/phase3/metrics/phase3-chaos-metrics.csv | phase3_performance_response_times.png; phase3_mutation_score_by_module.png; phase3_chaos_availability_by_scenario.png | Sequential local load and synthetic faults limit external generalization |
| Discussion                     | Intermediate Empirical Review + phase3                   | qa/midterm/docs/midterm-comparative-analysis.md; qa/phase3/docs/phase3-observed-vs-expected.md; qa/phase3/docs/phase3-final-summary.md                                                        | summary_quality_progression_across_phases.png                                                                         | Integration boundary remains unresolved risk concentration               |
| Conclusions                    | phase3 + archive                   | qa/phase3/docs/phase3-final-summary.md; qa/archive-preparation-report.md                                                                                                                      | summary_quality_progression_across_phases.png                                                                         | Conclusions must remain bounded to measured context                      |

## Section Build Priority

1. Methodology and Results sections first.
2. Discussion section second, after claim-strength review.
3. Introduction, abstract, and conclusion final.

---

KazUTB Digital Library - research synthesis layer (Phase 4) Part 1
