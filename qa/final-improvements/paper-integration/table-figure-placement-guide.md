# Table and Figure Placement Guide

Date: 2026-05-14
Purpose: Placement guidance for strongest canonical visual and tabular evidence.

## Tables

### 1) Risk Inventory Table

- Source: qa/final-improvements/tables/risk-table.md
- Recommended section: Methodology (Risk-Based Prioritization)
- Purpose in argumentation: Defines risk universe and priority classes used to allocate testing effort.
- Classification: Evidence-based (canonical table)

### 2) Risk-to-Test Mapping Table

- Source: qa/final-improvements/tables/risk-test-mapping-table.md
- Recommended section: Methodology (Selection Logic) and Results (Traceability)
- Purpose in argumentation: Shows direct alignment between risks and implemented tests.
- Classification: Evidence-based (canonical table)

### 3) Quality Gates Table

- Source: qa/final-improvements/tables/quality-gates-table.md
- Recommended section: Methodology (Governance) and Results (Gate Outcomes)
- Purpose in argumentation: Demonstrates measurable governance controls and gate completion.
- Classification: Evidence-based (canonical table)

### 4) Environment Table

- Source: qa/final-improvements/tables/environment-table.md
- Recommended section: Methodology (Environment and Scope)
- Purpose in argumentation: Separates local SQLite validation from Docker/pgsql-dependent scope.
- Classification: Evidence-based (canonical table)

### 5) Coverage vs Risk Table

- Source: qa/final-improvements/tables/coverage-vs-risk-table.md
- Recommended section: Results (Risk Alignment Outcomes)
- Purpose in argumentation: Quantifies mitigation coverage and targeted improvements.
- Classification: Evidence-based (canonical table)

### 6) Defect Detection Table

- Source: qa/final-improvements/tables/defect-detection-table.md
- Recommended section: Results (Defect Evidence)
- Purpose in argumentation: Distinguishes pre-existing failures, blocker elimination, and no-regression claims.
- Classification: Evidence-based (canonical table)

## Figures

### F1) Multi-layer CI/CD Pipeline and Test Environment

- Source: qa/final-improvements/figure-sources/figure-00-cicd-pipeline.md
- Recommended section: Methodology (Layered Workflow)
- Purpose in argumentation: Provides end-to-end process context for evidence generation and governance.
- Classification: Evidence-based/supporting architecture visualization

### F2) Testing Architecture

- Source: qa/final-improvements/figure-sources/figure-02-testing-architecture.md
- Recommended section: Methodology (Test Layers and Infrastructure)
- Purpose in argumentation: Shows test-layer composition, services, and execution architecture.
- Classification: Evidence-based/supporting architecture visualization

### F3) Coverage vs Risk Matrix

- Source: qa/final-improvements/figure-sources/figure-08-coverage-vs-risk-matrix.md
- Recommended section: Results (Risk Alignment Outcomes)
- Purpose in argumentation: Visual companion to coverage-vs-risk table.
- Classification: Analysis figure derived from canonical metrics

### F4) Quality Gates Dashboard

- Source: qa/final-improvements/figure-sources/figure-05-quality-gates-dashboard.md
- Recommended section: Results (Governance Outcomes)
- Purpose in argumentation: Summarizes gate status for quick interpretation.
- Classification: Analysis figure derived from canonical metrics

### F5) Defect Detection Flow

- Source: qa/final-improvements/figure-sources/figure-09-defect-detection-flow.md
- Recommended section: Results or Discussion (Defect handling process)
- Purpose in argumentation: Explains defect classification and reporting workflow.
- Classification: Conceptual-supporting (not a measured-results chart)

### F6) Performance Dashboard

- Source: qa/final-improvements/figure-sources/figure-10-performance-dashboard.md
- Recommended section: Results (Execution-time interpretation)
- Purpose in argumentation: Supports retained baseline performance discussion.
- Classification: Evidence-based with retained-baseline caveat

## Placement Safety Notes

1. Mark F5 (Defect Detection Flow) as conceptual-supporting in caption.
2. Mark F6 (Performance Dashboard) as retained baseline, not newly rerun evidence.
3. Do not add chaos figure as new primary result figure for this paper version.
4. Do not frame mutation-tool output as rerun result in this integration pass.
5. Keep local Session 5 metrics explicitly labeled local validation in all figure/table captions where referenced.
