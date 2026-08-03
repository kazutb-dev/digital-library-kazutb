# experimental evaluation layer (Phase 3) Part 1 — Performance Testing: Execution Report

**Project:** KazUTB Digital Library
**Phase:** 3 Part 1 — Performance Testing
**Document:** Execution Report
**Date:** 2026-05-13
**Executed by:** Automated PowerShell scripts on developer workstation (Windows 11)

---

## 1. Environment

| Property                    | Value                                             |
| --------------------------- | ------------------------------------------------- |
| Host OS                     | Windows 11 (dev workstation)                      |
| CPU cores                   | 8                                                 |
| Total RAM                   | 15 613 MB                                         |
| Available RAM at test start | 2 550 MB (83.7% used)                             |
| Web server                  | Nginx on localhost:80                             |
| PHP version                 | 8.4.19                                            |
| PHP-FPM workers active      | 4                                                 |
| Framework                   | Laravel 13.2                                      |
| Database                    | Local development instance                        |
| Test tool                   | PowerShell `Invoke-WebRequest` (sequential, 1 VU) |
| Dataset type                | bounded_synthetic                                 |

---

## 2. Execution Commands

### Run 1: Catalog API (S01–S04, S09)

```powershell
pwsh -NoLogo -NoProfile -File "qa/phase3/performance/scripts/perf-catalog-api.ps1" `
     -BaseUrl "http://localhost" `
     -OutputDir "qa/phase3/performance/results"
```

**Run ID:** `20260513-133438`
**Started:** 2026-05-13 13:34:38
**Completed:** 2026-05-13 13:42:22
**Result file:** `qa/phase3/performance/results/catalog-api-perf-20260513-133438.json`
**Log file:** `qa/phase3/evidence/logs/perf-catalog-api.log`

### Run 2: Web/Public (S05–S07)

```powershell
pwsh -NoLogo -NoProfile -File "qa/phase3/performance/scripts/perf-web-public.ps1" `
     -BaseUrl "http://localhost" `
     -OutputDir "qa/phase3/performance/results"
```

**Run ID:** `20260513-134213`
**Started:** 2026-05-13 13:42:13
**Completed:** 2026-05-13 13:45:11
**Result file:** `qa/phase3/performance/results/web-public-perf-20260513-134213.json`
**Log file:** `qa/phase3/evidence/logs/perf-web-public.log`

### Run 3: Integration Boundary (S08)

```powershell
pwsh -NoLogo -NoProfile -File "qa/phase3/performance/scripts/perf-integration-boundary.ps1" `
     -BaseUrl "http://localhost" `
     -OutputDir "qa/phase3/performance/results"
```

**Run ID:** `20260513-134513`
**Started:** 2026-05-13 13:45:13
**Completed:** 2026-05-13 13:46:37
**Result file:** `qa/phase3/performance/results/integration-boundary-perf-20260513-134513.json`
**Log file:** `qa/phase3/evidence/logs/perf-integration-boundary.log`

---

## 3. Results Summary

| Scenario ID       | Module             | Requests | Avg (ms) | p95 (ms) | Error % | Passed  |
| ----------------- | ------------------ | -------- | -------- | -------- | ------- | ------- |
| S01-NL-CATALOG    | Catalog API        | 20       | 3 443.66 | 3 553.05 | 0%      | ✅ PASS |
| S02-PL-CATALOG    | Catalog API        | 30       | 3 497.38 | 3 796.10 | 0%      | ✅ PASS |
| S03-NL-SUBJECTS   | Catalog API        | 20       | 3 359.51 | 3 969.37 | 0%      | ✅ PASS |
| S04-SL-MIXED      | Catalog API        | 20       | 3 464.90 | 3 659.68 | 0%      | ✅ PASS |
| S05-NL-EXTRES     | External Resources | 20       | 3 077.66 | 3 334.84 | 0%      | ✅ PASS |
| S06-NL-WEBCATALOG | Web Catalog UI     | 10       | 3 784.74 | 4 037.13 | 0%      | ✅ PASS |
| S07-PL-WEBCATALOG | Web Catalog UI     | 20       | 3 639.72 | 4 014.71 | 0%      | ✅ PASS |
| S08A-NO-AUTH      | Integration API    | 10       | 3 331.68 | 3 661.04 | 100%\*  | ❌ FAIL |
| S08B-BAD-TOKEN    | Integration API    | 10       | 3 142.56 | 3 329.11 | 100%\*  | ❌ FAIL |
| S09-END-CATALOG   | Catalog API        | 40       | 3 454.20 | 3 562.67 | 0%      | ✅ PASS |

> \*S08A/B: 100% "error" = 100% of requests correctly rejected (401/400). The FAIL status reflects middleware overhead (3 237ms avg) exceeding the 2 000ms latency threshold, not application errors.

**Overall: 8/9 scenarios PASS; 1 scenario FAIL (S08-BND-INTEGRATION, threshold exceeded)**

---

## 4. Key Observations

### 4.1 Uniform High Latency

All endpoints cluster in the 3 000–4 000ms range regardless of endpoint complexity. This strongly suggests a fixed overhead — PHP-FPM bootstrap, database connection establishment, or opcode cache miss — rather than query-specific latency.

### 4.2 Integration Middleware FAIL

The integration API correctly rejected unauthenticated and bad-token requests (as expected), but the rejection latency (avg 3 237ms) exceeded the configured 2 000ms threshold by 62%. This indicates the middleware chain does not short-circuit before a full PHP-FPM cycle completes.

### 4.3 Web Catalog Largest Payload

`GET /catalog` returned 130 180 bytes of HTML (approximately 127 KB) per request. This is the heaviest payload and slowest non-integration endpoint at 3 784ms average.

### 4.4 Endurance — Stable

S09 (40 requests, 138s) showed essentially the same average latency as S01 (20 requests, 69s): delta = 10ms. No memory leak observed (PHP working set returned to baseline).

### 4.5 Subjects Highest p95 Variance

S03 `/api/v1/subjects` showed the highest p95 relative to its median: p95 = 3 969ms vs median = 3 233ms (delta = 736ms). This variance suggests occasional slow queries or GC pauses.

---

## 5. Excluded Endpoints

| Endpoint                      | Reason for Exclusion                                              |
| ----------------------------- | ----------------------------------------------------------------- |
| `GET /news`                   | Returns HTTP 500 (confirmed automation and CI governance layer (Phase 2); server-side rendering error) |
| `POST /api/login`             | Times out >10s (confirmed automation and CI governance layer (Phase 2); Sanctum auth flow issue)       |
| Integration API (valid token) | Requires institutional bearer token unavailable in test env       |

---

_KazUTB Digital Library — QA experimental evaluation layer (Phase 3) Part 1 — 2026-05-13_
