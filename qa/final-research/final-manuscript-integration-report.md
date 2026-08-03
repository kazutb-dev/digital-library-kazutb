# Final Manuscript Integration Report

## Scope

This report records application of the lean final manuscript placement plan to the paper draft.

- Updated manuscript: qa/phase4/paper/full-paper-draft.md
- Placement plan source: qa/final-research/final-manuscript-placement-plan.md
- Table selection source: qa/final-research/final-table-selection.md

## Main-Text Figures Kept

The manuscript now references the lean main figure set only:

1. qa/final-improvements/figures-publication/fig-01-risk-inventory-heatmap.svg
2. qa/final-improvements/figures-publication/fig-02-coverage-vs-risk.svg
3. qa/final-improvements/figures-publication/fig-03-execution-time-comparison.svg
4. qa/final-improvements/figures-publication/fig-04-performance-bounds.svg
5. qa/final-improvements/figures-publication/fig-05-cicd-pipeline.svg
6. qa/final-improvements/figures-publication/fig-06-testing-architecture.svg
7. qa/final-improvements/figures-publication/fig-07-defect-detection-comparison.svg

## Main-Text Tables Kept

The manuscript now keeps the six lean main tables as direct table references:

1. qa/final-improvements/tables/risk-table.md
2. qa/final-improvements/tables/risk-test-mapping-table.md
3. qa/final-improvements/tables/quality-gates-table.md
4. qa/final-improvements/tables/environment-table.md
5. qa/final-improvements/tables/coverage-vs-risk-table.md
6. qa/final-improvements/tables/defect-detection-table.md

## Moved Out of Main Text (Supplementary Support)

The following artifacts are retained as supplementary/support material and are no longer used as main-text tables/figures:

- qa/final-improvements/figures-publication/fig-08-quality-gates-summary.svg
- qa/final-improvements/tables/execution-time-comparison-table.md
- qa/final-improvements/tables/performance-summary-table.md
- qa/final-improvements/tables/mutation-summary-table.md

## Prose and Reference Adjustments Applied

1. Replaced old figure-sources references with publication figure package references in Methods/Results sections.
2. Added explicit supplementary note for fig-08 to preserve quality-gate summary context without expanding the main figure set.
3. Rewrote performance subsection references to rely on main-text figures (fig-03, fig-04) and moved detailed timing/performance/mutation tables to supplementary notes.
4. Updated internal evidence inventory to label moved-out tables as supplementary.
5. Added an appendices note listing supplementary figure/table support items.

## Caveat Preservation Check

Caveat language was preserved and reinforced in-place:

- Local vs environment-specific validation boundaries remain explicit.
- Retained baseline evidence is still distinguished from newly validated evidence.
- Performance interpretation remains bounded and non-overclaiming.
- Governance closure is reported as process/evidence quality, not absolute product defect elimination.

## Remaining Human Review Points

1. Confirm journal/venue policy on whether supplementary artifacts should be referenced inline as notes or only in an appendix section.
2. Confirm caption numbering/order in final typeset manuscript matches the final figure order (fig-01 to fig-07 main, fig-08 supplementary).
3. Verify final manuscript style guide preference for phrasing: "Supplementary table note" labels vs narrative appendix cross-reference style.
4. Final author pass on claim-strength wording in Sections 4.3 to 4.5 to ensure consistency with submission tone.
