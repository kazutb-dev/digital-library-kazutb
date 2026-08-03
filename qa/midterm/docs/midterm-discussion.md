# Intermediate Empirical Review Discussion

## What worked

- Risk-based prioritization from baseline QA layer (Phase 1) correctly highlighted modules where automation and CI governance layer (Phase 2) found defects.
- Quality gate governance effectively surfaced release-blocking issues.
- Intermediate Empirical Review automation expansion delivered maintainable tests across Unit, Integration, and E2E levels with clean evidence logs.

## What did not work

- Public route reliability remained unresolved (/news 500).
- Integration endpoint behavior showed contract/path instability despite boundary middleware controls.
- Local coverage observability was blocked in measured environment, reducing detectability confidence.

## Unexpected findings

- Public news route 5xx was not predicted as a dominant defect signal in initial planning.
- SQLite-sensitive backend subset behavior introduced reproducibility friction in targeted suite outcomes.
- Coverage availability itself became a cross-cutting quality risk.

## Improvements for next baseline QA layer (Phase 1). Resolve /news runtime failure and add focused regression checks on that route.
2. Stabilize integration endpoint path/data behavior beyond middleware boundary success.
3. Increase admin/circulation high-risk coverage depth above 70%.
4. Add repeated-run stability tracking in CI to compute objective flaky-rate metrics.
5. Harmonize local and CI coverage tooling for comparable observability.
