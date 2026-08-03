# experimental evaluation layer (Phase 3) Chart Rendering Instructions

These CSV files can be imported into Excel, Google Sheets, LibreOffice Calc, or any
charting tool to produce the visualisations described below.

---

## Chart 1 — Response-Time Distribution

**File:** `phase3-response-time-chart.csv`

| Chart type        | Grouped Bar (clustered)                                         |
| ----------------- | --------------------------------------------------------------- |
| X-axis            | `scenario_id`                                                   |
| Y-axis            | Milliseconds (ms)                                               |
| Series            | `avg_ms`, `median_ms`, `p95_ms`                                 |
| Secondary markers | `threshold_ms` — draw as a horizontal stepped line per scenario |

**Excel steps:**

1. Open `phase3-response-time-chart.csv`
2. Select columns A, D, E, F, H (scenario_id, avg, median, p95, threshold)
3. Insert → Clustered Bar chart
4. Format threshold as a separate data series with a dashed red line (secondary axis optional)
5. Title: "Response Time by Scenario (ms)"

**Colour guide:**

- `avg_ms` → blue
- `median_ms` → orange
- `p95_ms` → green
- `threshold_ms` → dashed red

---

## Chart 2 — Throughput per Scenario

**File:** `phase3-throughput-chart.csv`

| Chart type  | Bar (horizontal or vertical)                          |
| ----------- | ----------------------------------------------------- |
| X-axis      | `scenario_id`                                         |
| Y-axis      | `throughput_rps` (requests per second)                |
| Colour code | Green if rps > 0.3; amber if 0.25–0.30; red if < 0.25 |

**Excel steps:**

1. Open `phase3-throughput-chart.csv`
2. Select columns A (scenario_id) and D (throughput_rps)
3. Insert → Bar chart
4. Add data labels showing `total_requests` on each bar
5. Title: "Throughput (rps) by Scenario"

---

## Chart 3 — Error Rate and Pass/Fail Status

**File:** `phase3-error-rate-chart.csv`

| Chart type | Stacked 100% Bar                               |
| ---------- | ---------------------------------------------- |
| X-axis     | `scenario_id`                                  |
| Y-axis     | Percent (0–100%)                               |
| Series     | `success_count` (green) and `fail_count` (red) |

**Excel steps:**

1. Open `phase3-error-rate-chart.csv`
2. Select columns A, D, E, F (scenario_id, total_requests, success_count, fail_count)
3. Compute pct columns: `=E2/D2*100` and `=F2/D2*100`
4. Insert → 100% Stacked Bar chart using pct columns
5. Annotate S08A and S08B bars with "FAIL (expected 401/400)"
6. Title: "Scenario Pass / Fail and Error Rate"

---

## Chart 4 — Resource Usage During Tests

**File:** `phase3-resource-usage-chart.csv`

| Chart type       | Line chart with two Y-axes               |
| ---------------- | ---------------------------------------- |
| X-axis           | `collection_time` or `observation_label` |
| Primary Y-axis   | `host_memory_used_pct` (%)               |
| Secondary Y-axis | `php_total_working_set_mb` (MB)          |

**Excel steps:**

1. Open `phase3-resource-usage-chart.csv`
2. Select columns C, D, H (collection_time, host_memory_used_pct, php_total_working_set_mb)
3. Insert → Line chart
4. Right-click php_total_working_set_mb series → Format → Secondary Axis
5. Title: "Host Memory and PHP Process RSS During Tests"

**Note on data quality:**
Resource observations were collected via PowerShell point-in-time snapshots
(not continuous monitoring). Slight interpolation was applied between known
pre/post-run baseline values. Graphs are indicative, not precise.

---

## Recommended Chart Order in Report

1. Response-Time Distribution (Chart 1) — shows latency problem clearly
2. Error Rate (Chart 3) — shows S08 FAIL status
3. Throughput (Chart 2) — shows capacity limitation
4. Resource Usage (Chart 4) — shows stable host state

---

_Generated: 2026-05-13 | experimental evaluation layer (Phase 3) Part 1 — Performance Testing_
