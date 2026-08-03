# Final Manuscript Placement Plan

Date: 2026-05-14
Scope: Placement planning only. No new figures or tables created.

## 1. Main Manuscript Figures

| Figure # | Filename                                      | Section / Subsection                             | Purpose                                                                   | Why It Belongs in the Main Manuscript                                                                              |
| -------- | --------------------------------------------- | ------------------------------------------------ | ------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------ |
| Figure 1 | fig-01-risk-heatmap.png / .svg                | Methodology, Risk-Based Prioritization           | Shows how the 12 canonical risks distribute across likelihood and impact. | It provides a fast visual entry point to the risk model and supports the paper's risk-first narrative.             |
| Figure 2 | fig-02-coverage-vs-risk.png / .svg            | Results, Risk Alignment Outcomes                 | Summarizes coverage by risk ID and priority.                              | It is a compact visual companion to the coverage table and makes the risk gaps immediately visible.                |
| Figure 3 | fig-03-execution-time-comparison.png / .svg   | Results, Runtime Profile                         | Compares runtime across the major test categories and suite totals.       | It gives a concise runtime view without forcing the reader to parse the full execution-time table.                 |
| Figure 4 | fig-04-performance-bounds.png / .svg          | Results / Discussion, Performance Interpretation | Presents the bounded p95 proxy and throughput baseline.                   | It is the only compact visual that can communicate retained performance evidence with the caveat clearly attached. |
| Figure 5 | fig-05-cicd-pipeline.png / .svg               | Methodology, Layered Workflow                    | Shows the end-to-end commit-to-evidence pipeline.                         | It is a clean process diagram that supports the governance story without adding text-heavy detail.                 |
| Figure 6 | fig-06-testing-architecture.png / .svg        | Methodology, Test Layers and Infrastructure      | Summarizes the application, test, and runtime layers.                     | It clarifies the execution environment and helps readers understand how the evidence was produced.                 |
| Figure 7 | fig-07-defect-detection-comparison.png / .svg | Results, Defect Evidence                         | Compares manual versus automated defect detections.                       | It adds a compact high-level comparison that complements, but does not replace, the detailed defect table.         |

## 2. Main Manuscript Tables

| Table # | Source File                                             | Section / Subsection                                 | Purpose                                                      | Why It Belongs in the Main Manuscript                                                                            |
| ------- | ------------------------------------------------------- | ---------------------------------------------------- | ------------------------------------------------------------ | ---------------------------------------------------------------------------------------------------------------- |
| Table 1 | qa/final-improvements/tables/risk-table.md              | Methodology, Risk-Based Prioritization               | Defines the canonical risk inventory and priority classes.   | It is the base reference for the entire risk-driven argument and should stay in the core paper.                  |
| Table 2 | qa/final-improvements/tables/risk-test-mapping-table.md | Methodology, Selection Logic / Results, Traceability | Maps risks to tests and evidence outputs.                    | It is the main traceability bridge between risk and implementation.                                              |
| Table 3 | qa/final-improvements/tables/quality-gates-table.md     | Methodology, Governance / Results, Gate Outcomes     | Lists the detailed gate thresholds and statuses.             | It is the authoritative governance table and should remain in the main paper even though Figure 8 summarizes it. |
| Table 4 | qa/final-improvements/tables/environment-table.md       | Methodology, Environment and Scope                   | Captures runtime, database, and tooling assumptions.         | It is necessary for interpreting the local-vs-Docker evidence split and caveats.                                 |
| Table 5 | qa/final-improvements/tables/coverage-vs-risk-table.md  | Results, Risk Alignment Outcomes                     | Shows coverage changes by risk and mitigation status.        | It is the detailed numeric complement to Figure 2 and carries the exact values.                                  |
| Table 6 | qa/final-improvements/tables/defect-detection-table.md  | Results, Defect Evidence                             | Records defect counts, environment errors, and fix outcomes. | It remains the authoritative defect record; Figure 7 is only a summary comparison.                               |

## 3. Appendix / Supplementary Items

| Source                                                                            | Why It Should Not Be in the Main Manuscript                                                                               | Placement Recommendation                                                            |
| --------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------------------------------------- | ----------------------------------------------------------------------------------- |
| qa/final-improvements/figures-publication/fig-08-quality-gates-summary.png / .svg | Summary-only governance chart; the detailed gate table is the authoritative source.                                       | Appendix / supplementary figure or evidence-package-only.                           |
| qa/final-improvements/tables/execution-time-comparison-table.md                   | Too detailed for the lean main paper once Figure 3 already presents the runtime comparison.                               | Appendix / supplementary table.                                                     |
| qa/final-improvements/tables/performance-summary-table.md                         | Retained baseline snapshot is useful for auditability but not necessary in the main text.                                 | Appendix / evidence-package-only.                                                   |
| qa/final-improvements/tables/mutation-summary-table.md                            | Proxy-based mutation interpretation is valuable background but not central to the core narrative.                         | Appendix / evidence-package-only.                                                   |
| qa/final-improvements/paper-integration/claim-caveat-matrix.md                    | It is a planning/control document for claim discipline, not a result artifact.                                            | Appendix or internal author support only.                                           |
| qa/final-improvements/paper-integration/table-figure-placement-guide.md           | It explains placement logic rather than presenting evidence.                                                              | Appendix / author notes only.                                                       |
| qa/final-improvements/paper-integration/final-table-selection.md                  | It is a manuscript support map, not content to cite in the paper body.                                                    | Evidence-package support only; not for the paper body.                              |
| qa/final-improvements/figures-publication/rendering-report.md                     | It documents generation/rendering decisions and limitations, not scientific findings.                                     | Evidence-package-only.                                                              |
| qa/final-improvements/figures-publication/figure-mapping.md                       | It is a build-time source-to-figure map, not manuscript content.                                                          | Evidence-package-only.                                                              |
| qa/final-improvements/figures-publication/figure-captions.md                      | It is a caption inventory for production, not a paper artifact.                                                           | Evidence-package-only.                                                              |
| qa/final-improvements/figures-publication/manifest.csv                            | It is an asset index for packaging and validation.                                                                        | Evidence-package-only.                                                              |
| qa/final-improvements/metrics/qa-rerun-overview-session5.md                       | It is supplementary local-validation evidence with explicit scope limits.                                                 | Appendix, if Session 5 needs a bounded supplement; otherwise evidence-package-only. |
| qa/final-improvements/metrics/performance-rerun.csv and .json                     | They are retained baseline inputs and should not be expanded into extra manuscript narrative beyond Table 8 and Figure 4. | Evidence-package-only, with brief reference in Results / Discussion.                |
| qa/final-improvements/metrics/mutation-rerun.csv and .json                        | They are proxy evidence inputs, not tool-run mutation results.                                                            | Evidence-package-only, with brief reference in Results / Discussion.                |
| qa/final-improvements/metrics/chaos-rerun.csv and .json                           | They were explicitly excluded from new main claims and are not paper results.                                             | Evidence-package-only or future-work appendix only.                                 |

## 4. Redundancy Review

| Overlapping Pair                          | Redundancy Assessment                                                                                                  | Recommended Primary Item                                   | Why                                                                                                   |
| ----------------------------------------- | ---------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------- | ----------------------------------------------------------------------------------------------------- |
| Figure 2 vs Table 5                       | Moderate overlap. The figure is a compact summary; the table holds exact values and caveats.                           | Table 5 as primary; Figure 2 as visual companion.          | The table should carry the exact evidence because the coverage values and caveats matter.             |
| Figure 7 vs Table 6                       | Moderate overlap. The figure compresses defect detection into a comparison; the table holds the full defect breakdown. | Table 6 as primary; Figure 7 as visual companion.          | Defect counts and environment distinctions are detail-sensitive and belong in the table.              |
| Figure 4 vs Appendix performance table(s) | Moderate overlap. The figure summarizes retained performance; the appendix tables hold the exact retained values.      | Figure 4 as main-text performance visual; tables appended. | The paper only needs the performance story once; the detailed tables can move out of the core.        |
| Figure 3 vs Appendix execution-time table | Moderate overlap. The figure provides the readable runtime comparison; the appendix table preserves exact timings.     | Figure 3 as main-text representative; table appended.      | The lean paper benefits more from the compact figure than from an additional detailed runtime table.  |
| Figure 8 vs Table 3                       | Strong overlap in governance state, but the figure is intentionally status-only.                                       | Table 3 as primary; Figure 8 omitted from main paper.      | The paper needs the gate thresholds and exact enforcement stages in the table, not the summary chart. |

## 5. Caveat-Sensitive Placements

| Item               | Required Caveat in Caption or Surrounding Text                                                                                                 |
| ------------------ | ---------------------------------------------------------------------------------------------------------------------------------------------- |
| Figure 4 / Table 8 | Performance is retained baseline evidence; panel A is a bounded proxy derived from canonical critical-path timing, not a raw percentile trace. |
| Figure 7 / Table 6 | Detailed defect counts and environment errors stay in the table; the figure is only a manual-versus-automated comparison.                      |
| Figure 2 / Table 5 | Local-session improvements are local validation, not full production revalidation.                                                             |
| Table 4            | Local SQLite versus Docker/pgsql scope must remain explicit.                                                                                   |
| Table 9            | Mutation evidence is proxy-based because no new mutation-tool run was executed in this step.                                                   |

## 6. Lean Placement Verdict

The main manuscript should be trimmed to 7 figures and 6 tables. The lean core is strong enough if it keeps only the most informative and least redundant items:

1. Methodology: Tables 1–4 and Figures 1, 5, 6.
2. Results: Tables 5–6 and Figures 2, 3, 4, 7.
3. Appendix / support: the quality-gates summary figure, detailed runtime/performance/mutation tables, and package-control documents.

This keeps the main text balanced while preserving exact detail in the appendix and evidence package.
