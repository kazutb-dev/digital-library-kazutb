# experimental evaluation layer (Phase 3) Part 1 — Performance Testing: Metrics Report

**Project:** KazUTB Digital Library
**Phase:** 3 Part 1 — Performance Testing
**Document:** Metrics Report
**Date:** 2026-05-13

---

## 1. Response Time Metrics

### 1.1 By Scenario (ms)

| Scenario          | Avg      | Median   | p95      | Min      | Max      | Threshold | Status |
| ----------------- | -------- | -------- | -------- | -------- | -------- | --------- | ------ |
| S01-NL-CATALOG    | 3 443.66 | 3 462.75 | 3 553.05 | 3 068.42 | 3 592.31 | 5 000     | PASS   |
| S02-PL-CATALOG    | 3 497.38 | 3 584.38 | 3 796.10 | 2 742.78 | 4 014.69 | 6 000     | PASS   |
| S03-NL-SUBJECTS   | 3 359.51 | 3 233.23 | 3 969.37 | 2 831.09 | 4 417.13 | 5 000     | PASS   |
| S04-SL-MIXED      | 3 464.90 | 3 434.04 | 3 659.68 | 2 734.71 | 4 894.81 | 6 000     | PASS   |
| S05-NL-EXTRES     | 3 077.66 | 3 147.37 | 3 334.84 | 2 176.44 | 3 382.91 | 5 000     | PASS   |
| S06-NL-WEBCATALOG | 3 784.74 | 3 734.60 | 4 037.13 | 3 550.00 | 4 037.13 | 8 000     | PASS   |
| S07-PL-WEBCATALOG | 3 639.72 | 3 723.84 | 4 014.71 | 2 784.19 | 4 188.03 | 10 000    | PASS   |
| S08A-NO-AUTH      | 3 331.68 | 3 374.42 | 3 661.04 | 2 360.61 | 3 661.04 | 2 000     | FAIL   |
| S08B-BAD-TOKEN    | 3 142.56 | 3 230.91 | 3 329.11 | 2 227.53 | 3 329.11 | 2 000     | FAIL   |
| S09-END-CATALOG   | 3 454.20 | 3 464.23 | 3 562.67 | 3 163.47 | 3 713.70 | 6 000     | PASS   |

### 1.2 Cross-Scenario Summary

| Metric                                      | Value                                       |
| ------------------------------------------- | ------------------------------------------- |
| Overall min (any scenario)                  | 2 176.44ms (S05-NL-EXTRES)                  |
| Overall max (any scenario)                  | 4 894.81ms (S04-SL-MIXED spike)             |
| Overall avg (8 passing scenarios, excl S08) | 3 459.72ms                                  |
| Fastest endpoint (avg)                      | GET /api/v1/external-resources — 3 077.66ms |
| Slowest endpoint (avg)                      | GET /catalog — 3 784.74ms                   |
| Highest p95                                 | S03 /api/v1/subjects — 3 969.37ms           |
| S08 middleware overhead avg                 | 3 237.12ms (threshold 2 000ms — FAIL)       |

---

## 2. Throughput Metrics

| Scenario          | Requests | Elapsed (s) | RPS           |
| ----------------- | -------- | ----------- | ------------- |
| S01-NL-CATALOG    | 20       | 68.946      | 0.290         |
| S02-PL-CATALOG    | 30       | 104.958     | 0.286         |
| S03-NL-SUBJECTS   | 20       | 67.211      | 0.298         |
| S04-SL-MIXED      | 20       | 69.319      | 0.289         |
| S05-NL-EXTRES     | 20       | 61.613      | 0.325         |
| S06-NL-WEBCATALOG | 10       | 37.861      | 0.264         |
| S07-PL-WEBCATALOG | 20       | 72.819      | 0.275         |
| S08A-NO-AUTH      | 10       | 33.317      | 0.300         |
| S08B-BAD-TOKEN    | 10       | 31.426      | 0.318         |
| S09-END-CATALOG   | 40       | 138.209     | 0.289         |
| **Total**         | **200**  | **685.679** | **avg 0.292** |

> Note: RPS values are for 1-VU sequential testing. Actual production RPS under concurrent load would be substantially higher (or would degrade faster).

---

## 3. Error Rate Metrics

| Scenario       | Total Req | Success | Fail | Error % | Notes                            |
| -------------- | --------- | ------- | ---- | ------- | -------------------------------- |
| S01–S07, S09   | 200       | 200     | 0    | 0.00%   | All HTTP 200 responses           |
| S08A-NO-AUTH   | 10        | 0       | 10   | 100.00% | Expected 401 (auth rejection)    |
| S08B-BAD-TOKEN | 10        | 0       | 10   | 100.00% | Expected 400/401 (invalid token) |

> S08 "failures" are by design — the test validates middleware rejection, not endpoint success.

---

## 4. Response Body Size

| Scenario          | Endpoint                   | Avg Body (bytes) | Avg Body (KB) |
| ----------------- | -------------------------- | ---------------- | ------------- |
| S05-NL-EXTRES     | /api/v1/external-resources | 12 763           | 12.5          |
| S06-NL-WEBCATALOG | /catalog                   | 130 180          | 127.1         |
| S07-PL-WEBCATALOG | /catalog                   | 130 180          | 127.1         |

> Body sizes not captured for S01–S04, S08, S09 (API JSON payloads — typical 2–50 KB range).

---

## 5. Resource Utilisation (Best-Effort Observations)

| Observation Point        | Memory Used % | PHP Working Set (MB) | PHP Processes |
| ------------------------ | ------------- | -------------------- | ------------- |
| Pre-run baseline         | 83.7%         | 72.5                 | 4             |
| Peak (S04 spike)         | 84.3%         | 75.5                 | 4             |
| Post-run final           | 83.7%         | 72.5                 | 4             |
| Delta (peak vs baseline) | +0.6%         | +3.0                 | 0             |

> Resource metrics collected via PowerShell point-in-time snapshots. No memory leak detected.

---

## 6. Metrics Files

| File                                                  | Description                                             |
| ----------------------------------------------------- | ------------------------------------------------------- |
| `qa/phase3/metrics/phase3-performance-results.csv`    | Row per scenario — all latency + throughput + pass/fail |
| `qa/phase3/metrics/phase3-performance-results.json`   | JSON equivalent                                         |
| `qa/phase3/metrics/phase3-resource-observations.csv`  | Host resource snapshots                                 |
| `qa/phase3/metrics/phase3-resource-observations.json` | JSON equivalent                                         |
| `qa/phase3/metrics/phase3-bottlenecks.csv`            | Bottleneck catalogue (8 items)                          |
| `qa/phase3/metrics/phase3-bottlenecks.json`           | JSON equivalent                                         |

---

_KazUTB Digital Library — QA experimental evaluation layer (Phase 3) Part 1 — 2026-05-13_
