# experimental evaluation layer (Phase 3) — Observed vs Expected Analysis

Project: KazUTB Digital Library
Date: 2026-05-13

## Comparison Summary

The full comparison dataset is stored in:

1. `qa/phase3/metrics/phase3-observed-vs-expected.csv`
2. `qa/phase3/metrics/phase3-observed-vs-expected.json`

## Key Matches

1. Performance campaign met planned pass-rate expectation (8/9).
2. Mutation campaign exceeded bounded quality target (85.71% >= 80%).
3. Chaos recovery availability met expectation (100%).
4. Chaos propagation remained isolated in all scenarios (0 cascading).
5. Intermediate Empirical Review prediction of elevated integration risk remains valid.

## Key Deviations

1. Integration boundary latency remained above threshold in performance tests (3237.12 ms vs 2000 ms expected).
2. Mutation campaign retained 2 surviving mutants where preferred target is zero survivors.

## Root-Cause Perspective

1. Integration boundary path remains an outlier across performance and risk dimensions.
2. Surviving mutants indicate assertion-depth gaps in context mapping tests.
3. Chaos scenarios show recovery behavior is stable, but baseline latency remains high enough to constrain resilience margin.

## Implications for Next baseline QA layer (Phase 1). research synthesis layer should prioritize integration-path hardening and observability.
2. Add stricter context assertions in integration controller tests.
3. Introduce bounded resilience gates in CI for early regression detection.

---

KazUTB Digital Library — QA experimental evaluation layer (Phase 3)
