# Paper Integration Report

Date: 2026-05-14
Repository: kazutb-dev/digital-library-kazutb
Integration basis: Canonicalized qa/final-improvements package
Primary manuscript updated: qa/phase4/paper/full-paper-draft.md

## 1. What Was Updated in the Paper

The main draft was updated to integrate canonical QA evidence and remove non-canonical emphasis.

Updated sections:

1. Abstract: replaced phase-centric narrative with canonical evidence narrative.
2. Introduction: aligned problem framing with risk-based QA governance.
3. Research Questions: added explicit RQ1-RQ3.
4. Methodology: added risk prioritization, layered workflow, environment separation, quality-gate governance, and evidence-class taxonomy.
5. Results: integrated canonical outcomes and Session 5 improvements with local-scope caveats.
6. Discussion: strengthened breadth-vs-depth interpretation and governance-versus-quality distinction.
7. Threats to Validity: structured as Internal, External, Construct, Conclusion validity.
8. Reproducibility/Replication: added package-structure replication note.
9. Conclusion: bounded and defensible final claims.

## 2. Evidence Integrated

Canonical evidence used:

- qa/final-improvements/SUMMARY.md
- qa/final-improvements/TRACEABILITY.md
- qa/final-improvements/tables/risk-table.md
- qa/final-improvements/tables/risk-test-mapping-table.md
- qa/final-improvements/tables/quality-gates-table.md
- qa/final-improvements/tables/environment-table.md
- qa/final-improvements/tables/coverage-vs-risk-table.md
- qa/final-improvements/tables/defect-detection-table.md
- qa/final-improvements/tables/execution-time-comparison-table.md
- qa/final-improvements/figure-sources/figure-index.md
- qa/final-improvements/figure-sources/figure-00-cicd-pipeline.md
- qa/final-improvements/figure-sources/figure-02-testing-architecture.md
- qa/final-improvements/figure-sources/figure-08-coverage-vs-risk-matrix.md

Supplementary evidence referenced with limits:

- qa/final-improvements/metrics/qa-rerun-overview-session5.md (supplementary, local-only)
- qa/final-improvements/metrics/qa-metrics-session5.csv/json (supplementary)

## 3. Caveats Preserved in the Paper

The updated manuscript explicitly preserves all required caveats:

1. Local-only Session 5 findings are labeled local (SQLite) and not production-proven.
2. Retained baseline evidence is labeled retained (not newly rerun in this step).
3. Mutation tooling is not presented as freshly rerun; assertion-depth is treated as proxy evidence.
4. Chaos/resilience is explicitly excluded from new main-result claims.
5. Pgsql-dependent blocked tests are documented as pending environment validation.
6. Quality-gate pass status is not interpreted as full product-quality proof.

## 4. Safe-Validation Checks Performed

1. Canonical evidence priority maintained over ambiguous files.
2. Session-only files used only as supplementary context.
3. No overclaim language added.
4. Local vs retained vs pending scope separated in Methods, Results, and Threats sections.
5. Internal consistency maintained across claims in Abstract, Results, Discussion, and Conclusion.
6. No conflict markers introduced.

## 5. What Remains for Final Human Review

1. External scholarly citations are still placeholder items.
2. Final publication style editing (journal/conference formatting) is still needed.
3. Optional final pgsql rerun can be added later as a post-integration evidence update.
4. Figure rendering/export from Mermaid sources to target publication format remains a formatting task.

## 6. Integration Outcome

Status: COMPLETE

The manuscript is now near-final for empirical evidence integration and is defensible under conservative interpretation rules.
