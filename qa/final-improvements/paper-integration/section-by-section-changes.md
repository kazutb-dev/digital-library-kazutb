# Section-by-Section Changes

Date: 2026-05-14
Updated manuscript: qa/phase4/paper/full-paper-draft.md

## Abstract

Previous weakness:

- Relied on older phase-centric narrative and included evidence classes now out of scope for new main claims.

Update applied:

- Rewritten to use canonical final-improvements evidence.
- Added explicit retained/local/excluded framing.

Evidence source used:

- qa/final-improvements/SUMMARY.md
- qa/final-improvements/CANONICALIZATION-NOTE.md

Remaining caveat:

- External citations still pending.

## Introduction

Previous weakness:

- Broad framing without explicit evidence-integration objective.

Update applied:

- Reframed around risk-based governance and claims discipline for this repository.

Evidence source used:

- qa/final-improvements/README.md
- qa/final-improvements/TRACEABILITY.md

Remaining caveat:

- Repository-specific scope limits generalization.

## Research Questions (New)

Previous weakness:

- No explicit RQ block.

Update applied:

- Added RQ1-RQ3 aligned to final evidence.

Evidence source used:

- qa/final-improvements/SUMMARY.md
- qa/final-improvements/tables/risk-test-mapping-table.md
- qa/final-improvements/tables/quality-gates-table.md

Remaining caveat:

- RQ3 evidence remains partly local-context bounded.

## Methodology

Previous weakness:

- Historic phase progression emphasized over current canonical evidence classes.

Update applied:

- Added explicit risk prioritization, layered workflow, environment separation, and evidence-class taxonomy (validated/retained/blocked/excluded).

Evidence source used:

- qa/final-improvements/tables/risk-table.md
- qa/final-improvements/tables/environment-table.md
- qa/final-improvements/tables/quality-gates-table.md
- qa/final-improvements/figure-sources/figure-00-cicd-pipeline.md
- qa/final-improvements/figure-sources/figure-02-testing-architecture.md

Remaining caveat:

- Docker/pgsql-dependent validations remain pending for blocked tests.

## Results

Previous weakness:

- Overweighted older experimental framing not aligned with final canonicalization constraints.

Update applied:

- Rebuilt around canonical outcomes: enhanced tests, risk alignment improvement, gate outcomes, defect detection, and retained performance baseline.
- Explicitly excluded chaos as new main-result evidence.
- Explicitly treated mutation as non-rerun in this step.

Evidence source used:

- qa/final-improvements/tables/coverage-vs-risk-table.md
- qa/final-improvements/tables/quality-gates-table.md
- qa/final-improvements/tables/defect-detection-table.md
- qa/final-improvements/tables/execution-time-comparison-table.md
- qa/final-improvements/figure-sources/figure-08-coverage-vs-risk-matrix.md
- qa/final-improvements/figure-sources/figure-05-quality-gates-dashboard.md

Remaining caveat:

- Local Session 5 gains are not equivalent to full environment revalidation.

## Discussion

Previous weakness:

- Strong but mixed framing of governance and quality outcomes.

Update applied:

- Added explicit breadth-vs-depth interpretation.
- Clarified governance signal vs product-quality state.
- Added A1 and assertion-strengthening implications with bounded scope wording.

Evidence source used:

- qa/final-improvements/SUMMARY.md
- qa/final-improvements/TRACEABILITY.md

Remaining caveat:

- Discussion still requires final external literature tie-in.

## Threats to Validity

Previous weakness:

- Validity concerns were present but not fully aligned to final integration constraints.

Update applied:

- Structured into Internal, External, Construct, and Conclusion validity.
- Added explicit notes for local SQLite constraints, blocked pgsql tests, retained baselines, excluded chaos, and no fresh mutation-tool execution.

Evidence source used:

- qa/final-improvements/docs/threats-to-validity-structured.md
- qa/final-improvements/SUMMARY.md

Remaining caveat:

- Formal citation support for validity framework still pending.

## Reproducibility / Replication (New explicit section)

Previous weakness:

- Reproducibility content was implicit and dispersed.

Update applied:

- Added dedicated section describing package structure and replication rules.

Evidence source used:

- qa/final-improvements/docs/replication-package-note.md
- qa/final-improvements/README.md

Remaining caveat:

- Reproducing pgsql-dependent items requires environment completion.

## Conclusion

Previous weakness:

- Included broader phrasing from pre-canonical narrative.

Update applied:

- Tightened to defensible near-final claims only.
- Explicitly states remaining dependency on editorial + citation + optional pgsql update.

Evidence source used:

- qa/final-improvements/CANONICALIZATION-VALIDATION-REPORT.md
- qa/final-improvements/SUMMARY.md

Remaining caveat:

- Not a final publication version until citation and style pass are done.
