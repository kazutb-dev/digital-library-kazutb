# experimental evaluation layer (Phase 3) Part 3 — Chaos Execution Report

Project: KazUTB Digital Library
Run ID: 20260513-142323
Date: 2026-05-13

## Execution Method

Chaos scenarios were executed with:

- Script: `qa/phase3/chaos/scripts/run-phase3-chaos.ps1`
- Mode: bounded and isolated
- Fault model: mixed (`bounded synthetic` and `synthetic fault model`)

## Scenario Execution Log

| Scenario ID          | Command / Method                       | Start-End Window (UTC)                       | Observed Behavior                                               | Degradation Type                       | Recovery Result                          |
| -------------------- | -------------------------------------- | -------------------------------------------- | --------------------------------------------------------------- | -------------------------------------- | ---------------------------------------- |
| CHS-001-API-DOWN     | non-listening host (`127.0.0.1:65535`) | 2026-05-13T09:23:25Z to 2026-05-13T09:23:53Z | 6/6 fault requests returned request errors (connection refused) | Hard unavailability on target endpoint | 4/4 recovery probes succeeded (HTTP 200) |
| CHS-002-DB-SLOWDOWN  | timeout=1s on `/api/v1/catalog-db`     | 2026-05-13T09:23:54Z to 2026-05-13T09:24:16Z | 6/6 fault requests timed out (`TaskCanceledException`)          | Fail-fast timeout degradation          | 4/4 recovery probes succeeded (HTTP 200) |
| CHS-003-NET-LATENCY  | +1500ms client delay                   | 2026-05-13T09:24:21Z to 2026-05-13T09:25:02Z | 6/6 fault requests succeeded, but latency remained elevated     | Graceful latency degradation           | 4/4 recovery probes succeeded            |
| CHS-004-CPU-PRESSURE | local CPU stress background job        | 2026-05-13T09:25:06Z to 2026-05-13T09:25:41Z | 6/6 fault requests succeeded under pressure                     | Graceful performance degradation       | 4/4 recovery probes succeeded            |

## Error Handling and Logging Observations

1. API downtime and timeout scenarios produced explicit request errors rather than indefinite hanging.
2. Recovery probes returned deterministic success after fault removal in all scenarios.
3. No scenario produced cascading unreachability in propagation probes.

## Evidence

Logs:

1. `qa/phase3/evidence/logs/phase3-chaos-CHS-001-API-DOWN-20260513-142323.log`
2. `qa/phase3/evidence/logs/phase3-chaos-CHS-002-DB-SLOWDOWN-20260513-142323.log`
3. `qa/phase3/evidence/logs/phase3-chaos-CHS-003-NET-LATENCY-20260513-142323.log`
4. `qa/phase3/evidence/logs/phase3-chaos-CHS-004-CPU-PRESSURE-20260513-142323.log`

Raw outputs:

1. `qa/phase3/chaos/results/phase3-chaos-summary-20260513-142323.csv`
2. `qa/phase3/chaos/results/phase3-chaos-summary-20260513-142323.json`
3. `qa/phase3/chaos/results/phase3-chaos-requests-20260513-142323.csv`
4. `qa/phase3/chaos/results/phase3-chaos-requests-20260513-142323.json`

---

KazUTB Digital Library — QA experimental evaluation layer (Phase 3) Part 3
