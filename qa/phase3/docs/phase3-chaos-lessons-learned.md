# experimental evaluation layer (Phase 3) Part 3 — Chaos Lessons Learned

Project: KazUTB Digital Library
Date: 2026-05-13

## What Handled Faults Well

1. Recovery behavior after bounded fault removal was consistently successful (100% recovery availability).
2. Faults remained isolated; no cascading propagation was observed in probe checks.
3. Timeout-driven failure mode produced explicit and bounded errors instead of hanging requests.

## Weaknesses and Gaps

1. Core API latency remains high even in non-fault windows (consistent with Part 1 baseline).
2. Integration domain still concentrates risk (Part 1 S08 fail + Part 2 survivors + chaos sensitivity evidence).
3. Resilience mechanisms are mostly implicit; explicit retry/backoff/circuit-breaker patterns are not visible in measured paths.

## Graceful vs Abrupt Degradation

1. Graceful degradation:

- CHS-003-NET-LATENCY (all requests succeeded under injected delay).
- CHS-004-CPU-PRESSURE (all requests succeeded under local pressure).

2. Abrupt degradation:

- CHS-001-API-DOWN (hard request failures by design of outage simulation).
- CHS-002-DB-SLOWDOWN (all fault-phase requests timed out under constrained timeout policy).

## Cross-Phase Linkage

1. Intermediate Empirical Review predicted integration and public-route stability risks; experimental evaluation layer (Phase 3) confirms residual sensitivity.
2. Performance testing identified integration boundary latency weakness; chaos tests reinforce need for faster fault-path handling.
3. Mutation survivors exposed context assertion weakness; this affects confidence in robustness validation depth.

## Recommendations

1. Implement resilient client/server timeout policy with explicit retry budgets and jittered backoff for critical integration operations.
2. Add lightweight health endpoint and error-budget SLO alerting for faster resilience diagnostics.
3. Add chaos regression suite in CI (nightly) using bounded synthetic scenarios only.
4. Improve test assertions on context propagation to reduce survivable logic regressions.
5. Prioritize integration boundary optimization before research synthesis layer synthesis.

---

KazUTB Digital Library — QA experimental evaluation layer (Phase 3) Part 3
