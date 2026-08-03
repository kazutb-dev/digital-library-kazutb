# Full Paper Draft

Working title:
Risk-Based QA Governance and Evidence Integration in the KazUTB Digital Library

## Abstract

This paper presents a repository-level QA case study for the KazUTB Digital Library, grounded in the canonical evidence package in qa/final-improvements. The study integrates risk-prioritized test design, layered automation, and explicit quality-gate governance under bounded evidence interpretation.

The canonical baseline reports 947 executed tests (793 pass, 112 pre-existing failures, 36 environment errors) and 43 enhanced tests with a 100% pass rate. A supplementary Session 5 focused rerun (20 tests, local SQLite context) validated local removal of the A1 blocker and strengthened 10 tests, increasing assertion density by 236%. Risk evidence covers 12 risks; local coverage is reported as 83% (10/12), with full local coverage of high-priority risks and improved reservation-path medium risks. These counts are drawn from the canonical summary and traceability artifacts.

The primary contribution is an evidence-classification model for defensible reporting: local findings are labeled local, prior-run evidence is labeled retained baseline, and blocked or excluded evidence classes are not promoted as new empirical results. In this integration step, performance remains retained baseline evidence, mutation evidence is treated as assertion-strength proxy evidence, and chaos testing is excluded from new main-result claims.

## 1. Introduction

Digital-library systems combine operationally critical access control, transactional reservation behavior, and API integration boundaries. In such settings, QA value depends not only on test volume, but also on whether evidence is prioritized by risk and interpreted within explicit scope constraints.

This paper consolidates final QA evidence from the canonical package in qa/final-improvements into a single empirical narrative. The objective is not to broaden claims, but to tighten claim-to-evidence alignment and preserve methodological traceability.

The study is organized around three principles:

1. risk-prioritized evidence selection,
2. layered governance through quality gates and traceability,
3. conservative interpretation across local, retained-baseline, and pending evidence classes.

These principles motivate the three research questions and structure the results reporting that follows.

## 2. Research Questions

The paper addresses three research questions aligned to the final canonical evidence set:

- RQ1: Can risk-based prioritization improve alignment between QA effort and operationally critical repository components?
- RQ2: How effective are layered automation and quality gates as measurable governance mechanisms under mixed execution environments?
- RQ3: What additional evidence is obtained from bounded targeted validation and assertion strengthening after the baseline rerun?

## 3. Methodology

### 3.1 Risk-Based Prioritization

Risk prioritization follows the canonical risk inventory and risk-to-test mapping artifacts. The framework covers 12 risks (critical, high, medium) and allocates emphasis to authorization boundaries, reservation/account API paths, and data-validation behavior.

[Table reference: Risk Inventory Table, qa/final-improvements/tables/risk-table.md]
[Table reference: Risk-to-Test Mapping Table, qa/final-improvements/tables/risk-test-mapping-table.md]
[Figure reference: Risk inventory heatmap, qa/final-improvements/figures-publication/fig-01-risk-inventory-heatmap.svg]

### 3.2 Layered QA Workflow and Environment Separation

The workflow combines three layers:

1. retained canonical baseline evidence,
2. bounded supplementary Session 5 validation,
3. integrated gate and traceability review.

Execution environments are treated as analytically distinct: local SQLite results support local claims, while pgsql-dependent outcomes remain explicitly pending where Docker/PostgreSQL validation is required.

[Figure reference: CI/CD pipeline and evidence flow, qa/final-improvements/figures-publication/fig-05-cicd-pipeline.svg]
[Figure reference: Testing architecture overview, qa/final-improvements/figures-publication/fig-06-testing-architecture.svg]
[Table reference: Environment Table, qa/final-improvements/tables/environment-table.md]

### 3.3 Quality-Gate Governance and Evidence Classes

Governance is operationalized through 14 quality gates (10 baseline gates and 4 Session 5 gates). To prevent scope inflation, evidence is classified into four classes:

- validated: directly validated in the current scope,
- retained baseline: canonical prior-run evidence reused without rerun,
- blocked/pending: environment-dependent evidence not yet validated,
- excluded: evidence classes explicitly out of scope for new main claims.

[Table reference: Quality Gates Table, qa/final-improvements/tables/quality-gates-table.md]

## 4. Results

### 4.1 Core Outcome Synthesis

The baseline demonstrates stable enhanced-test behavior (43/43 passing). Session 5 adds bounded local evidence by validating local elimination of the A1 blocker and strengthening 10 tests in high-impact authentication and validation paths.

### 4.2 Risk Alignment

Targeted reservation-path metrics improved in local scope after A1 remediation:

- R-RESV-001: 30% to 57% (local)
- R-RESV-002: 33% to 67% (local)

Local aggregate risk coverage is reported as 83% (10/12). These improvements are interpreted as local, not as full environment revalidation.

[Table reference: Coverage vs Risk Table, qa/final-improvements/tables/coverage-vs-risk-table.md]
[Figure reference: Coverage vs Risk chart, qa/final-improvements/figures-publication/fig-02-coverage-vs-risk.svg]

### 4.3 Governance Results

All 14 gates are marked passing in the integrated package. This indicates process discipline, evidence traceability, and controlled interpretation boundaries; it is not treated as equivalent to complete product-level defect elimination.

[Table reference: Quality Gates Table, qa/final-improvements/tables/quality-gates-table.md]
[Supplementary figure note: Quality gate count summary visual is available as qa/final-improvements/figures-publication/fig-08-quality-gates-summary.svg and is intentionally kept outside the main figure set.]

### 4.4 Defect and Blocker Evidence

No new defects were introduced in the enhanced set. Session 5 evidence indicates local elimination of the A1 blocker in targeted paths. Pre-existing baseline failures remain documented outside the scope of this integration update.

[Table reference: Defect Detection Table, qa/final-improvements/tables/defect-detection-table.md]
[Figure reference: Defect detection comparison chart, qa/final-improvements/figures-publication/fig-07-defect-detection-comparison.svg]

### 4.5 Performance Interpretation

Performance is reported as retained baseline evidence (about 190 seconds for the full baseline campaign). Runtime comparison is presented through a compact main-text visual, and performance interpretation remains caveat-sensitive. Detailed execution-time and retained performance tables are provided as supplementary support rather than main-text tables.

[Figure reference: Execution time comparison chart, qa/final-improvements/figures-publication/fig-03-execution-time-comparison.svg]
[Figure reference: Performance bounds chart (retained baseline with bounded proxy), qa/final-improvements/figures-publication/fig-04-performance-bounds.svg]
[Supplementary table note: Detailed execution time table remains available at qa/final-improvements/tables/execution-time-comparison-table.md.]
[Supplementary table note: Retained performance summary table remains available at qa/final-improvements/tables/performance-summary-table.md.]
[Supplementary table note: Mutation summary table remains available at qa/final-improvements/tables/mutation-summary-table.md.]

## 5. Discussion

### 5.1 Interpreting RQ1

RQ1 is supported directionally: risk-prioritized targeting improved evidence depth in operationally sensitive reservation/account paths within local scope.

### 5.2 Interpreting RQ2

RQ2 is supported by consistent gate closure and traceability structure. The strongest result is governance clarity rather than broad new product-quality claims.

### 5.3 Interpreting RQ3

RQ3 is supported as an incremental gain: A1 remediation and assertion strengthening increased local confidence and failure-detection sensitivity in selected tests. The gain remains bounded and environment-specific.

### 5.4 Trade-Offs and Scope Constraints

The evidence profile reflects a deliberate breadth-depth trade-off: broad baseline coverage is retained, while supplementary depth is concentrated in high-risk areas. This improves interpretability while preserving uneven depth by design.

### 5.5 What the Results Mean in Practice

The study supports a practical reporting pattern for mixed-environment QA programs: classify evidence by validation scope, keep governance metrics explicit, and avoid promoting proxy or retained metrics as fresh empirical confirmation.

## 6. Threats to Validity

### 6.1 Internal Validity

Local execution may not expose all pgsql-dependent behaviors. Some findings derive from targeted reruns rather than full-suite reruns.

### 6.2 External Validity

This is a single-repository case study. Numeric outcomes are context-bound, while workflow and evidence-classification methods are more transferable.

### 6.3 Construct Validity

Gate completion measures governance quality, not full product quality. Assertion-density gains are proxy evidence and do not substitute for direct mutation-tool reruns.

### 6.4 Conclusion Validity

Performance is retained baseline evidence in this step, no new mutation-tool execution is included, and chaos testing remains excluded from new main results.

## 7. Reproducibility and Replication Note

Reproducibility is supported through the canonical package structure:

- canonical metrics: qa/final-improvements/metrics/
- canonical tables: qa/final-improvements/tables/
- figure sources: qa/final-improvements/figure-sources/
- traceability and summary: qa/final-improvements/TRACEABILITY.md and qa/final-improvements/SUMMARY.md

Replication should preserve evidence classification:

1. reproduce canonical baseline references as retained evidence,
2. report Session 5-style targeted outputs as local unless rerun in full environment,
3. report pgsql-dependent validations separately when Docker/PostgreSQL execution is available.

## 8. Conclusion

This draft provides a bounded, defensible synthesis of canonical QA evidence for the KazUTB Digital Library. The strongest supported findings are:

1. risk-based prioritization improved alignment between QA effort and high-impact components,
2. layered automation and quality gates improved governance traceability,
3. targeted local validation added incremental evidence through A1 remediation and stronger assertions.

The manuscript is near-final for submission-oriented editing. Remaining work is primarily bibliographic and formatting-oriented, with optional future pgsql validation updates.

## References

Internal evidence inventory:

- qa/final-improvements/SUMMARY.md
- qa/final-improvements/TRACEABILITY.md
- qa/final-improvements/CANONICALIZATION-NOTE.md
- qa/final-improvements/tables/risk-table.md
- qa/final-improvements/tables/risk-test-mapping-table.md
- qa/final-improvements/tables/quality-gates-table.md
- qa/final-improvements/tables/environment-table.md
- qa/final-improvements/tables/coverage-vs-risk-table.md
- qa/final-improvements/tables/defect-detection-table.md
- qa/final-improvements/tables/execution-time-comparison-table.md (supplementary)
- qa/final-improvements/tables/performance-summary-table.md (supplementary)
- qa/final-improvements/tables/mutation-summary-table.md (supplementary)

External references:

- To be finalized by the authors according to venue style (risk-based testing, CI/CD governance, and empirical software-engineering case-study reporting).

## Appendices / Evidence Notes

1. Figure mapping: qa/final-improvements/figure-sources/figure-index.md
2. Canonicalization status: qa/final-improvements/CANONICALIZATION-VALIDATION-REPORT.md
3. Reproducibility note: qa/final-improvements/docs/replication-package-note.md
4. Supplementary visual/table support:
    - qa/final-improvements/figures-publication/fig-08-quality-gates-summary.svg
    - qa/final-improvements/tables/execution-time-comparison-table.md
    - qa/final-improvements/tables/performance-summary-table.md
    - qa/final-improvements/tables/mutation-summary-table.md

---

KazUTB Digital Library - research synthesis layer (Phase 4) final evidence-integrated draft
