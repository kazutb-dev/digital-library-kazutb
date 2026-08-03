# Intermediate Empirical Review Methodology

## Risk-based testing approach

This Intermediate Empirical Review follows a risk-reassessment loop:

1. Start from baseline QA layer (Phase 1) baseline risk scores.
2. Extract empirical evidence from automation and CI governance layer (Phase 2) measured outcomes.
3. Re-score high-risk modules using observed defects, gate status, and coverage depth.
4. Expand automation for weak/high-risk areas with new tests across Unit, Integration, and E2E levels.

## Re-scoring logic

- Original score basis: baseline QA layer (Phase 1) score (probability x impact, 1-25 scale).
- Updated score basis:
  - Increase if repeated failures or fail-level gate breaches are observed.
  - Increase if user-facing critical flows fail (impact growth).
  - Increase if detectability degrades (coverage gaps, blocked coverage tooling).

## Risk dimensions mapping

Likelihood, impact, and detectability were evaluated before/after for each re-assessed module using factual evidence from:

- phase2-test-execution-log.csv
- phase2-defects-vs-risk.csv
- phase2-automation-coverage.csv
- phase2-quality-gate-results.csv
- phase2-coverage.log

## Automation strategy

- Unit: formatter boundary/edge checks.
- Integration: boundary authentication, required headers, rapid repeat requests.
- E2E: repeated unauthorized navigation and parallel public-query requests.

## Constraints and handling

- Flakiness could not be confirmed quantitatively due limited repeated-run history.
- Such cases are marked as suspected instability or insufficient repeated-run evidence.
- Pipeline screenshot evidence was not generated in this session; factual references are provided instead.
