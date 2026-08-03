# experimental evaluation layer (Phase 3) Part 3 — Chaos / Fault Injection Test Plan

Project: KazUTB Digital Library
Date: 2026-05-13
Scope: Controlled chaos testing for high-risk runtime paths

## Goal

Evaluate robustness, resilience, and fault tolerance by introducing bounded faults into high-risk modules and measuring availability, recovery, and propagation behavior.

## High-Risk Module Selection Basis

Selection was based on:

1. Intermediate Empirical Review risk reassessment (`qa/midterm/metrics/midterm-risk-reassessment.csv`).
2. experimental evaluation layer (Phase 3) Part 1 performance findings (integration boundary latency failure).
3. experimental evaluation layer (Phase 3) Part 2 mutation survivors (integration controller context-path weaknesses).
4. Safety and reproducibility in local bounded execution.

## Safety Principles

1. No destructive infrastructure shutdown.
2. Faults are bounded and isolated.
3. Synthetic fault models are explicitly labeled.
4. Recovery probes are always executed after fault removal.

## Planned Chaos Scenarios

| Scenario ID          | Target Module / Component                           | Fault Type                | Injection Method                                                           | Duration | Safety Level           | Expected Behavior                                         | Recovery Expectation                                           | Dataset Type          | Notes                                              |
| -------------------- | --------------------------------------------------- | ------------------------- | -------------------------------------------------------------------------- | -------: | ---------------------- | --------------------------------------------------------- | -------------------------------------------------------------- | --------------------- | -------------------------------------------------- |
| CHS-001-API-DOWN     | Catalog/Public API — `/api/v1/landing` availability | API downtime simulation   | Request to non-listening host/port (`127.0.0.1:65535`) to emulate downtime |   25.64s | Low-risk isolated      | Request errors should be explicit and bounded             | Service endpoint should return to HTTP 200 after fault removal | bounded synthetic     | No production service was stopped                  |
| CHS-002-DB-SLOWDOWN  | Catalog DB API — `/api/v1/catalog-db`               | Database slowdown proxy   | Force 1s client timeout against endpoint with known multi-second latency   |   20.10s | Low-risk timeout proxy | Timeouts should fail fast instead of hanging indefinitely | Endpoint should recover with normal timeout window             | bounded synthetic     | Proxy for dependency slowness/unavailability       |
| CHS-003-NET-LATENCY  | Landing endpoint network path                       | Network latency injection | Inject 1500ms delay in request wrapper before each call                    |   34.45s | Low-risk synthetic     | Throughput/latency degradation but successful responses   | Remove injected delay and validate normal path                 | synthetic fault model | Explicitly synthetic client-side model             |
| CHS-004-CPU-PRESSURE | Web/API runtime                                     | Resource pressure proxy   | Temporary local CPU stress background job during request window            |   34.91s | Medium-risk local only | Endpoint remains reachable with degraded timing           | Recovery probes should succeed after stress removal            | bounded synthetic     | Local-only pressure, no persistent process changes |

## Execution Command

`pwsh -File qa/phase3/chaos/scripts/run-phase3-chaos.ps1`

## Primary Outputs

1. `qa/phase3/metrics/phase3-chaos-scenarios.csv`
2. `qa/phase3/metrics/phase3-chaos-scenarios.json`
3. `qa/phase3/chaos/results/phase3-chaos-summary-20260513-142323.csv`
4. `qa/phase3/chaos/results/phase3-chaos-requests-20260513-142323.csv`
5. `qa/phase3/evidence/logs/phase3-chaos-CHS-*.log`

---

KazUTB Digital Library — QA experimental evaluation layer (Phase 3) Part 3
