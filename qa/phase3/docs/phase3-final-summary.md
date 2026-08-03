# experimental evaluation layer (Phase 3) — Final Integrated Summary

Project: KazUTB Digital Library
Date: 2026-05-13
Prepared by: QA Automation (GitHub Copilot Agent)

---

## Executive Outcome

experimental evaluation layer (Phase 3) is complete across all required parts:

1. Part 1 — Performance testing: executed and analyzed.
2. Part 2 — Mutation testing: executed and scored.
3. Part 3 — Chaos/fault-injection testing: executed with bounded synthetic faults and measured recovery.

The system shows strong recovery behavior under bounded chaos and good mutation resistance, but integration-boundary latency and assertion-depth gaps remain the dominant risks.

---

## Consolidated Results

### Performance (Part 1)

1. Scenarios: 9
2. Pass: 8
3. Fail: 1 (`S08-BND-INTEGRATION`)
4. Latency profile: endpoints mostly in 3.1s-3.8s range

### Mutation (Part 2)

1. Mutants executed: 14
2. Killed: 12
3. Survived: 2
4. Overall mutation score: 85.71%

### Chaos (Part 3)

1. Scenarios executed: 4
2. Fault-phase availability: 50.00%
3. Recovery-phase availability: 100.00%
4. Mean MTTR proxy: 3391.02 ms
5. Propagation: 4 isolated, 0 cascading

---

## Primary Findings

1. Integration boundary remains the highest operational and quality risk (performance outlier and mutation survivors in related context assertions).
2. Recovery behavior after bounded faults is deterministic and stable in current test scope.
3. No cascading behavior was observed in tested chaos scenarios.
4. Existing baseline latency leaves limited resilience headroom under real concurrent load.

---

## QA Layer Readiness

### Ready for research synthesis layer

1. Performance, mutation, and chaos datasets are complete and reproducible.
2. Observed-vs-expected synthesis is documented and traceable.
3. Paper-ready PNG figures are generated from factual measured CSV sources.

### Required Caveats

1. Chaos faults are synthetic and bounded; they are not production-scale disruption tests.
2. Sequential local-load performance results cannot replace full concurrent staging load tests.
3. Surviving mutants indicate unclosed assertion-depth gaps in integration payload semantics.

---

## Artifact Pointers

1. Integrated experimental report: `qa/phase3/docs/phase3-experimental-final-report.md`
2. Observed vs expected: `qa/phase3/docs/phase3-observed-vs-expected.md`
3. Chaos reports: `qa/phase3/docs/phase3-chaos-*.md`
4. Paper figure index: `qa/paper-assets/figures/figure-index.md`

---

## Final Recommendation Set

1. Prioritize integration boundary optimization and fast-fail paths.
2. Strengthen integration controller assertions for context/metadata fields.
3. Introduce nightly bounded mutation + chaos jobs in CI with artifact retention.
4. Execute concurrent load tests in Linux staging before release decisions.

---

KazUTB Digital Library — QA experimental evaluation layer (Phase 3) Final
