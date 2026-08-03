# Figure Index

## Overview

This is the authoritative index for final-improvements figure assets.

- Source diagrams: `qa/final-improvements/figure-sources/*.md`
- Rendered PNG outputs: `qa/final-improvements/figures/*.png`
- Rendering status for all listed figures: rendered successfully

## Authoritative Figure Mapping

| Figure | Title                           | Source Markdown                                                              | Rendered PNG Output                                                                                  | Purpose                                    | Evidence Class                            | Paper Priority |
| ------ | ------------------------------- | ---------------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------------- | ------------------------------------------ | ----------------------------------------- | -------------- |
| 00     | Multi-Layer CI/CD Pipeline      | [figure-00-cicd-pipeline.md](figure-00-cicd-pipeline.md)                     | [../figures/figure-00-cicd-pipeline.png](../figures/figure-00-cicd-pipeline.png)                     | End-to-end QA pipeline context             | Evidence-based/supporting architecture    | High           |
| 01     | QA Pipeline Diagram             | [figure-01-qa-pipeline.md](figure-01-qa-pipeline.md)                         | [../figures/figure-01-qa-pipeline.png](../figures/figure-01-qa-pipeline.png)                         | Campaign process overview                  | Evidence-based                            | Medium         |
| 02     | Testing Architecture (Enhanced) | [figure-02-testing-architecture.md](figure-02-testing-architecture.md)       | [../figures/figure-02-testing-architecture.png](../figures/figure-02-testing-architecture.png)       | Test stack and infrastructure architecture | Evidence-based/supporting architecture    | High           |
| 03     | Risk Heatmap                    | [figure-03-risk-heatmap.md](figure-03-risk-heatmap.md)                       | [../figures/figure-03-risk-heatmap.png](../figures/figure-03-risk-heatmap.png)                       | Risk distribution and coverage posture     | Analysis-based                            | High           |
| 04     | Test Coverage Map               | [figure-04-test-coverage-map.md](figure-04-test-coverage-map.md)             | [../figures/figure-04-test-coverage-map.png](../figures/figure-04-test-coverage-map.png)             | Functional coverage concentration          | Analysis-based                            | Medium         |
| 05     | Quality Gates Dashboard         | [figure-05-quality-gates-dashboard.md](figure-05-quality-gates-dashboard.md) | [../figures/figure-05-quality-gates-dashboard.png](../figures/figure-05-quality-gates-dashboard.png) | Governance gate outcomes                   | Analysis-based                            | High           |
| 06     | Risk Distribution Chart         | [figure-06-risk-distribution-chart.md](figure-06-risk-distribution-chart.md) | [../figures/figure-06-risk-distribution-chart.png](../figures/figure-06-risk-distribution-chart.png) | Risk composition by severity/component     | Analysis-based                            | Medium         |
| 07     | Test Execution Timeline         | [figure-07-test-execution-timeline.md](figure-07-test-execution-timeline.md) | [../figures/figure-07-test-execution-timeline.png](../figures/figure-07-test-execution-timeline.png) | Execution sequence and timing              | Evidence-based                            | Medium         |
| 08     | Coverage vs Risk Matrix         | [figure-08-coverage-vs-risk-matrix.md](figure-08-coverage-vs-risk-matrix.md) | [../figures/figure-08-coverage-vs-risk-matrix.png](../figures/figure-08-coverage-vs-risk-matrix.png) | Mitigation status by risk class            | Analysis-based                            | High           |
| 09     | Defect Detection Flow           | [figure-09-defect-detection-flow.md](figure-09-defect-detection-flow.md)     | [../figures/figure-09-defect-detection-flow.png](../figures/figure-09-defect-detection-flow.png)     | Defect classification process              | Conceptual-supporting                     | High           |
| 10     | Performance Metrics Dashboard   | [figure-10-performance-dashboard.md](figure-10-performance-dashboard.md)     | [../figures/figure-10-performance-dashboard.png](../figures/figure-10-performance-dashboard.png)     | Performance baseline interpretation        | Evidence-based (retained-baseline caveat) | High           |

## Paper-Facing Priority Set

Strongest paper-facing figures (minimum set):

1. Figure 00: CI/CD Pipeline
2. Figure 02: Testing Architecture
3. Figure 03: Risk Heatmap
4. Figure 05: Quality Gates Dashboard
5. Figure 08: Coverage vs Risk Matrix
6. Figure 09: Defect Detection Flow (conceptual-supporting)
7. Figure 10: Performance Dashboard (retained-baseline caveat)

## Caveat Notes

- Figure 09 is conceptual-supporting and should not be labeled as a direct measured-results chart.
- Figure 10 reflects retained baseline performance evidence and should not be framed as a newly rerun benchmark in this step.
- No chaos-engineering figure is included as new main-result evidence for this package.

## Validation Notes

- PNG outputs were rendered from the current final-improvements source markdown files.
- Outputs are isolated under `qa/final-improvements/figures/` to avoid legacy-phase confusion.
- Detailed render audit is documented in `qa/final-improvements/figures/rendering-report.md`.
