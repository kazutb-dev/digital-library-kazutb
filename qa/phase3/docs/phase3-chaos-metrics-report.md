# experimental evaluation layer (Phase 3) Part 3 — Chaos Metrics Report

Project: KazUTB Digital Library
Run ID: 20260513-142323
Date: 2026-05-13

## Scenario-Level Metrics

| Scenario ID          | Fault Availability % | Recovery Availability % | Fault Avg (ms) | Recovery Avg (ms) | MTTR Proxy (ms) | Propagation |
| -------------------- | -------------------: | ----------------------: | -------------: | ----------------: | --------------: | ----------- |
| CHS-001-API-DOWN     |                 0.00 |                  100.00 |        2049.68 |           3335.45 |         2987.83 | isolated    |
| CHS-002-DB-SLOWDOWN  |                 0.00 |                  100.00 |        1010.79 |           3509.12 |         3522.52 | isolated    |
| CHS-003-NET-LATENCY  |               100.00 |                  100.00 |        3439.30 |           3453.52 |         3425.76 | isolated    |
| CHS-004-CPU-PRESSURE |               100.00 |                  100.00 |        3429.24 |           3583.66 |         3627.96 | isolated    |

## Aggregate Chaos Metrics

| Metric                              |   Value | Unit    |
| ----------------------------------- | ------: | ------- |
| Scenarios executed                  |       4 | count   |
| Overall fault-phase availability    |   50.00 | percent |
| Overall recovery-phase availability |  100.00 | percent |
| Mean MTTR proxy                     | 3391.02 | ms      |
| Min MTTR proxy                      | 2987.83 | ms      |
| Max MTTR proxy                      | 3627.96 | ms      |
| Isolated failures                   |       4 | count   |
| Cascading failures                  |       0 | count   |

## Metric Notes

1. MTTR is a bounded proxy measured as time from fault removal to first successful recovery probe.
2. Fault availability dropped to 0% only in explicit outage/timeout scenarios, as expected.
3. Recovery availability reached 100% in all scenarios, indicating successful bounded recovery.
4. Propagation remained isolated in every scenario (probe endpoint reachable).

## Data Sources

1. `qa/phase3/metrics/phase3-chaos-results.csv`
2. `qa/phase3/metrics/phase3-chaos-results.json`
3. `qa/phase3/metrics/phase3-chaos-metrics.csv`
4. `qa/phase3/metrics/phase3-chaos-metrics.json`

---

KazUTB Digital Library — QA experimental evaluation layer (Phase 3) Part 3
