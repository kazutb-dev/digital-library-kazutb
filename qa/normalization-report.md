# QA Terminology Normalization Report

Date: 2026-05-13
Repository: kazutb-dev/digital-library-kazutb

## 1. Objective

This pass normalized course-oriented and internal staging wording into professional QA and empirical software engineering language across research-facing QA artifacts.

## 2. Terminology Patterns Normalized

Primary wording shifts applied in narrative prose:

1. Assignment 1 -> baseline risk-based QA strategy.
2. Assignment 2 -> automation and quality governance layer.
3. Assignment 3 -> experimental evaluation layer.
4. Assignment 4 -> research synthesis layer.
5. Midterm -> intermediate empirical review.
6. Phase 1/2/3/4 (in prose) -> professional layer naming while preserving historical phase labels in parentheses where needed.
7. Course/coursework phrasing -> project/program QA wording.
8. Submission/instructional phrasing -> deliverable/governance language.

## 3. Main Content Areas Updated

Normalization was applied to research-facing and QA-facing content under:

1. qa/README.md and top-level QA summaries.
2. qa/phase1, qa/phase2, qa/midterm, qa/phase3, qa/phase4 narrative documentation.
3. qa/phase4/paper drafts, maps, and synthesis sections.
4. qa/phase4/review package documents and readiness checklists.
5. qa/paper-assets README, figure index, and figure-generation script labels/titles.
6. qa/archive-preparation-report.md and related traceability/report artifacts.

## 4. What Was Intentionally Preserved

To preserve repository integrity and traceability:

1. Historical folder paths were preserved (for example qa/phase1, qa/phase2, qa/midterm, qa/phase3, qa/phase4).
2. File-path references inside documentation were kept intact for reproducibility.
3. Measured metrics, evidence statements, and limitations disclosures were preserved.
4. Existing artifact structure was not destructively reorganized.

## 5. Historical Naming Preservation Status

Historical top-level folder names and path references were intentionally preserved.

Reason:

1. They are part of existing traceability chains.
2. Renaming would create high refactoring risk and possible reference drift.
3. The objective was terminology normalization in presentation and narrative language, not repository history rewrite.

## 6. Residual Terms Kept for Traceability

Some terms may still appear in limited contexts where retention is justified:

1. File/folder paths and artifact identifiers (for example qa/phase2/_, qa/midterm/_).
2. Legacy labels inside raw evidence snapshots or immutable historical logs.
3. Explicit historical mapping statements where phase identity is required for auditability.

## 7. Validation Notes

Post-edit validation checks were run for:

1. changed-file existence,
2. path/reference integrity,
3. unresolved conflict marker scan,
4. commit scope review to avoid unrelated workspace changes.
