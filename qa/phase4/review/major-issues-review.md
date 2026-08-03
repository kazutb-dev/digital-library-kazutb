# Major Issues Review

Date: 2026-05-13
Scope: Internal mock review of phase4 draft package

## Major Issues

| Issue ID | Major Issue                                            | Why It Is Major                                                                                 | Evidence                                                                                      | Affected Section(s)                                                                        | Recommended Fix                                                                     |
| -------- | ------------------------------------------------------ | ----------------------------------------------------------------------------------------------- | --------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------------------ | ----------------------------------------------------------------------------------- |
| MAJ-01   | High dependency on citation placeholders               | Weakens academic legitimacy for publication-style review                                        | Widespread `[citation needed]` markers in literature/methodology/discussion/full paper        | literature-review-draft.md; methodology-draft.md; discussion-draft.md; full-paper-draft.md | Insert real references and replace placeholders with citation-supported claims      |
| MAJ-02   | Contribution novelty not yet sharply differentiated    | Reviewer may classify contribution as strong project documentation rather than research insight | Contribution wording is broad in abstract/final insight/conclusion                            | abstract-draft.md; final-insight-draft.md; final-conclusion-draft.md                       | Add explicit novelty boundary: what is new vs what is standard QA practice          |
| MAJ-03   | Threats-to-validity section lacks formal framing depth | May be challenged during defense/publication-style scrutiny                                     | Validity caveats exist but are not structured against recognized empirical-SE threat taxonomy | discussion-draft.md; methodology-draft.md; full-paper-draft.md                             | Add a dedicated validity framework subsection and align each threat with mitigation |
| MAJ-04   | Some high-level claims need stronger caution wording   | Risk of perceived overclaiming from case-specific evidence                                      | Statements on confidence/maturity could be interpreted beyond repository context              | preliminary-conclusions-draft.md; final-conclusion-draft.md                                | Add explicit scope qualifiers and repository-context boundaries                     |
| MAJ-05   | Figure-to-argument coupling is still placeholder-level | Weakens defensibility of visual evidence in oral defense                                        | Figure references are placeholders without final caption-argument anchoring                   | full-paper-draft.md; figures-and-tables-map.md                                             | Write final captions with claim linkage and expected takeaway per figure            |
| MAJ-06   | Concluding sections have partial semantic overlap      | Reduces rhetorical precision and may confuse evaluators                                         | Similar ideas repeated across preliminary/final insight/final conclusion                      | preliminary-conclusions-draft.md; final-insight-draft.md; final-conclusion-draft.md        | Enforce strict role boundaries for each conclusion layer                            |

## Severity Summary

1. High severity: MAJ-01, MAJ-02, MAJ-03
2. Medium-high severity: MAJ-04, MAJ-05
3. Medium severity: MAJ-06

## program Defense vs Publication Risk

1. For program defense: MAJ-01 and MAJ-03 are manageable with transparent caveat disclosure.
2. For publication-style rigor: MAJ-01 to MAJ-05 should be resolved before submission-style claims.

---

KazUTB Digital Library - research synthesis layer (Phase 4) Part 3
