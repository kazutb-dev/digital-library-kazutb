# experimental evaluation layer (Phase 3) Part 1 — Performance Testing: Methodology

**Project:** KazUTB Digital Library
**Phase:** 3 — Performance & Scalability
**Document:** Methodology
**Date:** 2026-05-13

---

## 1. Tool Selection

### Primary Tool: PowerShell `Invoke-WebRequest`

**Reason for choice:** k6 (the preferred industry tool for HTTP load testing) is not available in the current environment. `Invoke-WebRequest` is a built-in Windows PowerShell cmdlet capable of measuring HTTP request latency, status codes, and response body size with sub-millisecond timing resolution via .NET `System.Diagnostics.Stopwatch`.

**Limitations acknowledged:**

- Maximum concurrency = 1 VU (sequential requests only)
- No scripted think-time injection
- No distributed execution across multiple agents
- No per-second metric streaming
- Timing includes PowerShell overhead (~1–5ms per request)

### Alternative Considered: PHP Artisan Serve

The `php artisan serve` development server was trialled but rejected: it timed out on the first request (>10s latency) and produced unreliable results. Nginx on localhost:80 was confirmed stable and used for all tests.

### Resource Observation: PowerShell CIM / Get-Process

Host CPU and memory metrics were captured via:

```powershell
Get-CimInstance Win32_OperatingSystem  # available_memory, total_memory
Get-Process php                         # PHP-FPM worker count, working-set RSS
```

These are point-in-time snapshots taken before and after each scenario. No continuous monitoring agent (Prometheus, Grafana) was available.

---

## 2. Test Model: Bounded Synthetic

All scenarios are labelled `dataset_type = bounded_synthetic` to indicate:

- Request counts are reduced (10–40 per scenario) compared to real k6 load profiles
- All requests are sequential (1 VU), not concurrent
- Results describe worst-case single-user sequential latency, not maximum throughput under concurrent load

### Why this is valid for baseline documentation

A bounded synthetic baseline serves three legitimate academic and engineering purposes:

1. **Threshold regression detection**: If a deployment change causes p95 to jump from 3 500ms to 7 000ms, the bounded synthetic baseline catches it.
2. **Bottleneck surface identification**: Even at 1 VU, consistent 3.5s response times identify PHP-FPM / DB cold-path issues that would be amplified under real concurrency.
3. **Reproducibility**: Scripts are parameterised and deterministic; any reviewer can re-run and get comparable results.

---

## 3. Latency Measurement

Each request is measured using .NET Stopwatch:

```powershell
$sw = [System.Diagnostics.Stopwatch]::StartNew()
$response = Invoke-WebRequest -Uri $url -TimeoutSec 30 -UseBasicParsing -ErrorAction Stop
$sw.Stop()
$elapsed_ms = $sw.Elapsed.TotalMilliseconds
```

All times are recorded to two decimal places in milliseconds.

Computed statistics per scenario:

- `avg_ms` = arithmetic mean of all sample latencies
- `median_ms` = 50th percentile (sorted array middle value)
- `p95_ms` = 95th percentile (sorted array index = ceil(0.95 \* n) - 1)
- `min_ms`, `max_ms` = first and last of sorted array
- `throughput_rps` = total_requests / total_elapsed_sec

---

## 4. Response Body Measurement

For web UI scenarios (S05–S07), response body size is also recorded:

```powershell
$body_bytes = $response.Content.Length
```

This measures raw HTML bytes as received by the PowerShell HTTP client (no HTTP compression applied at client level unless Nginx sends pre-compressed). Results are recorded as `avg_body_bytes` in result JSON.

---

## 5. Integration Boundary Measurement

S08 uses two sub-scenarios to probe the integration API middleware rejection pathway:

- **S08A**: No authentication headers → expects HTTP 401
- **S08B**: Invalid bearer token (`Bearer invalid_test_token_abc123`) → expects HTTP 400 or 401

The `middleware_overhead_avg_ms` metric is computed as the average of the two sub-scenario averages:

```
middleware_overhead_avg_ms = (avg_A + avg_B) / 2
```

This measures how long the PHP middleware chain takes to reject an unauthenticated request. Threshold = 2 000ms (reasonable for a rejection that should short-circuit early).

---

## 6. Result Storage

| Format           | Location                                            |
| ---------------- | --------------------------------------------------- |
| Raw JSON per run | `qa/phase3/performance/results/*.json`              |
| Execution log    | `qa/phase3/evidence/logs/*.log`                     |
| Aggregated CSV   | `qa/phase3/metrics/phase3-performance-results.csv`  |
| Aggregated JSON  | `qa/phase3/metrics/phase3-performance-results.json` |

Run IDs follow format `YYYYMMDD-HHMMSS` (UTC-aware local time, Windows PowerShell `Get-Date`).

---

## 7. Disclosure Statement

> **All performance metrics in this report were collected using a 1-VU sequential
> PowerShell test harness on a Windows 11 developer workstation running Nginx + PHP 8.4.
> Metrics represent single-user sequential latency, not concurrent throughput.
> Full concurrent load testing with k6 or Locust on a Linux staging environment is
> required before production capacity planning.**

---

_KazUTB Digital Library — QA experimental evaluation layer (Phase 3) Part 1 — 2026-05-13_
