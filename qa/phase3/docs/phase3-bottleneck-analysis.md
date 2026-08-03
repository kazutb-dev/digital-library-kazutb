# experimental evaluation layer (Phase 3) Part 1 — Performance Testing: Bottleneck Analysis

**Project:** KazUTB Digital Library
**Phase:** 3 Part 1 — Performance Testing
**Document:** Bottleneck Analysis
**Date:** 2026-05-13

---

## 1. Overview

Of the 9 scenarios executed, **1 scenario FAILS its threshold (S08)**; **8 scenarios PASS**. Despite passing their thresholds, all 8 passing scenarios exhibit latency 6–8× above typical university-grade production targets (target ≤ 500ms for public read API). Eight distinct bottlenecks are identified below.

---

## 2. Bottleneck Catalogue

### BN-001 — CRITICAL: Uniform High Latency (3.1–3.8s avg)

**Affected scenarios:** ALL
**Metric:** avg_ms across all endpoints: 3 077ms – 3 784ms
**Target reference:** University public API ≤ 500ms typical; national library API ≤ 1 000ms

**Evidence:**
| Endpoint | Avg (ms) | vs 500ms target |
|---|---|---|
| /api/v1/external-resources | 3 077.66 | 6.2× |
| /api/v1/subjects | 3 359.51 | 6.7× |
| /api/v1/landing | 3 443.66 | 6.9× |
| /api/v1/catalog-db | 3 497.38 | 7.0× |
| /catalog (web) | 3 784.74 | 7.6× |

**Root cause hypothesis:**

1. PHP-FPM cold-start bootstrap path executed on each request (no OPcache or warm process reuse configured in dev)
2. Database connection established fresh per request (no persistent connection pool)
3. Windows NTFS I/O overhead vs Linux ext4 (dev environment penalty, not production representative)
4. Laravel service container full-boot per request including unused service providers

**Severity: CRITICAL** — Latency level would be unacceptable to users and would fail any production SLA audit.

---

### BN-002 — CRITICAL: Integration Middleware Rejection Overhead (3 237ms — FAIL)

**Affected scenario:** S08-BND-INTEGRATION
**Metric:** middleware_overhead_avg_ms = 3 237.12ms; threshold = 2 000ms; overage = +62%

**Evidence:**

```
S08A (no-auth):   avg=3331.68ms  p95=3661.04ms
S08B (bad-token): avg=3142.56ms  p95=3329.11ms
overhead_avg:    3237.12ms  FAIL (threshold 2000ms)
```

**Root cause hypothesis:**
The middleware chain processes the full PHP-FPM bootstrap, kernel service container resolution, route loading, and potentially DB query preparation before the authentication check fires. There is no evidence of short-circuit logic that halts processing before the first service provider or database connection is needed.

**Severity: CRITICAL** — If the integration API is used by partner institutions, a 3.2s rejection for every invalid token is a denial-of-service vector in addition to a performance defect.

---

### BN-003 — HIGH: Web Catalog HTML Payload (130KB per request)

**Affected scenarios:** S06-NL-WEBCATALOG, S07-PL-WEBCATALOG
**Metric:** avg_body_bytes = 130 180; avg_ms = 3 784.74 (slowest passing endpoint)

**Evidence:**
The `/catalog` Blade view returns a full server-side rendered HTML page containing catalog data, navigation, footer, and presumably inline JavaScript. At 130KB, the page is 10× larger than the external-resources API response (12.7KB) and takes 707ms longer on average.

**Root cause hypothesis:**

- All catalog data is embedded in a single Blade render pass without fragment caching
- CSS/JS asset pipeline adds rendering overhead
- No server-side pagination reduces document size (all records per render)
- No Gzip/Brotli compression configured in test environment

**Severity: HIGH** — 130KB HTML on every catalog page visit will be a major bottleneck for mobile users and slow connections in Kazakhstan's regional network infrastructure.

---

### BN-004 — HIGH: Throughput Ceiling (0.26–0.33 rps)

**Affected scenarios:** ALL
**Metric:** throughput_rps across all scenarios: min 0.264 (S06), max 0.325 (S05), avg 0.292

**Interpretation:**
At 1-VU sequential, the 3–4s avg latency caps throughput at ~0.29 rps. Under real concurrent load (100 simultaneous users), PHP-FPM workers would saturate quickly. With 4 active workers and 3.4s avg latency, maximum theoretical throughput = 4/3.4 ≈ 1.18 rps ≈ 70 requests/minute. A university library peak hour with 100 simultaneous browsers would overwhelm this.

**Severity: HIGH** — Capacity is critically insufficient for medium-scale university deployment without optimisation or infrastructure scaling.

---

### BN-005 — MEDIUM: /api/v1/subjects High p95 Variance

**Affected scenario:** S03-NL-SUBJECTS
**Metric:** p95 = 3 969ms vs median = 3 233ms (delta = 736ms; range = 2 831–4 417ms)

**Evidence:**
S03 has the highest p95/median delta of any scenario (736ms), suggesting occasional slow outliers. The max of 4 417ms is also the highest individual request time among all passing scenarios.

**Root cause hypothesis:**

- Subjects endpoint may perform aggregate/GROUP BY query without covering index
- Occasional full table scan when query plan cache is cold
- Row-level lock contention on subjects table if write operations run concurrently

**Severity: MEDIUM** — p95 is still within the 5 000ms threshold; however, this variance would increase under load.

---

### BN-006 — MEDIUM: /news HTTP 500 (Untestable)

**Affected scenario:** N/A (excluded)
**Metric:** HTTP 500 availability failure

The `/news` endpoint has returned HTTP 500 since automation and CI governance layer (Phase 2). The news module cannot be included in any performance scenario until the underlying server-side error is resolved. This represents a functional availability risk.

**Severity: MEDIUM** — News is a public-facing module; unavailability during experimental evaluation layer (Phase 3) indicates an unresolved defect from a prior phase.

---

### BN-007 — MEDIUM: /api/login Timeout (Untestable)

**Affected scenario:** N/A (excluded)
**Metric:** Response time > 10 000ms (timeout)

The `/api/login` endpoint timed out during automation and CI governance layer (Phase 2) testing and has not been resolved. Authentication is a critical path for member and librarian functionality; its timeout prevents any authenticated load scenario.

**Severity: MEDIUM** — All authenticated load testing is blocked until this is resolved.

---

### BN-008 — LOW: No Warm-Up Benefit (Endurance Flat)

**Affected scenario:** S09-END-CATALOG
**Metric:** S09 avg 3 454ms vs S01 avg 3 443ms (delta = 11ms over 138s/40 requests)

The endurance test shows no degradation (positive) but also no latency improvement from PHP process warm-up (flat). If OPcache were active and working, the first N requests would be slower with subsequent requests significantly faster. The flat profile indicates OPcache does not materially improve response time in this configuration.

**Severity: LOW** — No regression; but confirms OPcache is not delivering expected warm-up benefit.

---

## 3. Bottleneck Priority Matrix

| ID     | Severity | Impact Area                           | Blocked On               | Fix Complexity |
| ------ | -------- | ------------------------------------- | ------------------------ | -------------- |
| BN-001 | CRITICAL | All modules, user-facing latency      | PHP/DB config, OPcache   | Medium         |
| BN-002 | CRITICAL | Integration API, partner connectivity | Middleware refactor      | Medium         |
| BN-003 | HIGH     | Web catalog, mobile users             | Caching, compression     | Low–Medium     |
| BN-004 | HIGH     | Overall capacity                      | PHP-FPM tuning, caching  | Medium–High    |
| BN-005 | MEDIUM   | Subjects query, p95 SLA               | Index + query cache      | Low            |
| BN-006 | MEDIUM   | News availability                     | Bug fix                  | Low            |
| BN-007 | MEDIUM   | Auth load testing                     | Sanctum/bcrypt diagnosis | Low–Medium     |
| BN-008 | LOW      | Endurance predictability              | OPcache tuning           | Low            |

---

## 4. Conclusion

The system is functionally stable (8/9 scenarios pass thresholds); however, absolute latency is uniformly high across all modules, indicating a systemic PHP/DB cold-path issue that will compound severely under concurrent production load. experimental evaluation layer (Phase 3) Part 2 optimisation should prioritise BN-001 and BN-002 before any deployment.

---

_KazUTB Digital Library — QA experimental evaluation layer (Phase 3) Part 1 — 2026-05-13_
