# Intermediate Empirical Review Comparative Analysis

## Planned vs Actual (Phase1 vs Phase2/Midterm)

| Aspect | Planned (A1) | Actual (A2/A3 Intermediate Empirical Review) | Gap | Interpretation | Next action |
| --- | --- | --- | --- | --- | --- |
| Catalog public stability | High-risk but expected stable public routes after smoke coverage | /news produced HTTP 500 in measured run | Reliability gap despite broad coverage | Coverage breadth did not prevent runtime defect | Add route diagnostics + strict no-5xx enforcement |
| Integration boundary/API contract | Middleware boundary expected to keep contract stable | Integration smoke cases failed with 404; boundary-focused Intermediate Empirical Review tests pass | Downstream route/data path instability | Boundary controls are strong, endpoint behavior remains fragile | Stabilize endpoint contract and add CI contract checks |
| Circulation/admin operations | High impact expected with guard protection | Guard mismatch observed, coverage depth low (50-66.7%) | Detectability and depth gap | Under-tested high-impact workflows | Raise module coverage depth beyond 70% |
| Coverage observability | Coverage reporting expected for high-risk validation | Local coverage blocked in measured run | Observability gap across environments | Detectability risk remains high | Align local and CI coverage tooling |
| Automation depth by test level | Multi-level automation intended | Intermediate Empirical Review adds 8 tests across Unit/Integration/E2E | Stability tracking still under-sampled | Better depth achieved, but flaky analytics absent | Add scheduled reruns and flaky-rate tracking |

Detailed dataset: qa/midterm/metrics/midterm-planned-vs-actual.csv.
