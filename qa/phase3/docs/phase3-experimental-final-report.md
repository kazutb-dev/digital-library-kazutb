# experimental evaluation layer (Phase 3) — Experimental Testing Final Report

Project: KazUTB Digital Library
Date: 2026-05-13
Coverage: Performance (Part 1), Mutation (Part 2), Chaos (Part 3)

## 1. System and Tested High-Risk Modules

High-risk focus domains:

1. Catalog/Public API and web catalog response path.
2. Integration boundary and integration controllers.
3. Mutation-sensitive controller guard/context logic.
4. Runtime resilience behavior under bounded injected faults.

## 2. Performance Testing Methodology and Results

Method:

1. Bounded synthetic PowerShell request timing.
2. Scenario-driven endpoint tests with threshold checks.

Results:

1. 9 scenarios executed.
2. 8 scenarios passed, 1 failed (integration boundary latency threshold).
3. Persistent high-latency profile around 3.0-3.8s remained visible.

## 3. Mutation Testing Methodology and Results

Method:

1. Controlled manual mutation, scripted and reproducible.
2. One mutant applied at a time with source restoration.
3. Targeted tests executed per module.

Results:

1. 14 mutants executed.
2. 12 killed, 2 survived, 0 inconclusive.
3. Overall mutation score: 85.71%.
4. Survivors concentrated in context payload assertion weakness.

## 4. Chaos Testing Methodology and Results

Method:

1. Four bounded scenarios (API downtime simulation, timeout proxy, synthetic latency, CPU pressure).
2. Fault and recovery phases measured per scenario.
3. Propagation probe used to classify isolated vs cascading impact.

Results:

1. Overall fault-phase availability: 50%.
2. Recovery-phase availability: 100%.
3. Mean MTTR proxy: 3391.02 ms.
4. 4 isolated scenarios, 0 cascading propagation.

## 5. Comparative Analysis (Observed vs Expected)

Matches:

1. Performance pass-rate target reached (8/9).
2. Mutation score exceeded bounded target.
3. Chaos recovery behavior matched expectation.

Deviations:

1. Integration boundary latency exceeded expected threshold.
2. Mutation survivors indicate test-depth gap.

## 6. Lessons Learned

Strengths:

1. Strong guard/validation test sensitivity.
2. Deterministic bounded recovery after fault removal.

Weaknesses:

1. Integration path remains dominant risk concentration.
2. High baseline latency reduces resilience headroom.
3. Context semantics are under-asserted in some integration tests.

## 7. Recommendations

1. Prioritize integration boundary optimization and fast-fail architecture.
2. Strengthen context argument assertions in integration controller tests.
3. Add bounded mutation/chaos CI jobs with artifact retention.
4. Expand resilience observability (health probes and error-budget monitoring).

## 8. Reproducibility and Evidence

Primary evidence artifacts:

1. `qa/phase3/metrics/` (performance, mutation, chaos, observed-vs-expected)
2. `qa/phase3/evidence/logs/` (run logs)
3. `qa/phase3/chaos/results/` and `qa/phase3/mutation/results/` (raw run outputs)
4. `qa/paper-assets/figures/` (paper-ready PNG assets)

---

KazUTB Digital Library — QA experimental evaluation layer (Phase 3)
