# experimental evaluation layer (Phase 3) Part 1 — Performance Testing: Test Plan

**Project:** KazUTB Digital Library
**Phase:** 3 — Performance & Scalability
**Part:** 1 — Performance Testing
**Document:** Test Plan
**Date:** 2026-05-13
**Environment:** Local Nginx/PHP 8.4 on Windows 11 (developer workstation)
**Test Tool:** PowerShell `Invoke-WebRequest` (k6 not available)
**Test Model:** Bounded synthetic — 1 VU, sequential requests, explicit disclosure

---

## 1. Objectives

1. Establish baseline response-time and throughput metrics for the three priority modules under defined load scenarios.
2. Identify endpoints failing the configured per-scenario latency thresholds.
3. Produce evidence-backed bottleneck catalogue for experimental evaluation layer (Phase 3) Part 2 optimisation planning.
4. Document methodology limitations transparently to preserve academic integrity.

---

## 2. Scope

### In Scope

| Module                   | Endpoints                                                               |
| ------------------------ | ----------------------------------------------------------------------- |
| Catalog / Public API     | `GET /api/v1/landing`, `GET /api/v1/catalog-db`, `GET /api/v1/subjects` |
| External Resources       | `GET /api/v1/external-resources`                                        |
| Web Catalog UI           | `GET /catalog`                                                          |
| Integration API boundary | `GET /api/integration/v1/_boundary/ping` (rejection pathway only)       |

### Out of Scope

- Authenticated API flows (`/api/login` — confirmed timeout from automation and CI governance layer (Phase 2))
- News module (`/news` — confirmed HTTP 500 from automation and CI governance layer (Phase 2))
- POST/PUT/DELETE operations
- WebSocket or queue-based operations
- Concurrent/multi-VU load (k6 not available in this environment)

---

## 3. Scenarios

| ID                  | Name                                | Module             | Load Type     | VU  | Requests | Threshold          |
| ------------------- | ----------------------------------- | ------------------ | ------------- | --- | -------- | ------------------ |
| S01-NL-CATALOG      | Normal Load — Landing               | Catalog API        | normal_load   | 1   | 20       | p95 ≤ 5 000ms      |
| S02-PL-CATALOG      | Peak Load — Catalog DB              | Catalog API        | peak_load     | 1   | 30       | p95 ≤ 6 000ms      |
| S03-NL-SUBJECTS     | Normal Load — Subjects              | Catalog API        | normal_load   | 1   | 20       | p95 ≤ 5 000ms      |
| S04-SL-MIXED        | Spike — Mixed Endpoints             | Catalog API        | spike_load    | 1   | 20       | p95 ≤ 6 000ms      |
| S05-NL-EXTRES       | Normal Load — Ext Resources         | External Resources | normal_load   | 1   | 20       | p95 ≤ 5 000ms      |
| S06-NL-WEBCATALOG   | Normal Load — Web Catalog           | Web Catalog UI     | normal_load   | 1   | 10       | p95 ≤ 8 000ms      |
| S07-PL-WEBCATALOG   | Peak Load — Web Catalog             | Web Catalog UI     | peak_load     | 1   | 20       | p95 ≤ 10 000ms     |
| S08-BND-INTEGRATION | Boundary Auth — No-auth + Bad-token | Integration API    | boundary_auth | 1   | 10+10    | overhead ≤ 2 000ms |
| S09-END-CATALOG     | Endurance — Landing 40 req          | Catalog API        | endurance     | 1   | 40       | p95 ≤ 6 000ms      |

---

## 4. Environment

| Property     | Value                                  |
| ------------ | -------------------------------------- |
| OS           | Windows 11 (dev workstation)           |
| Web server   | Nginx (localhost:80)                   |
| PHP version  | 8.4.19                                 |
| Framework    | Laravel 13.2                           |
| Database     | Local (SQLite/PostgreSQL dev instance) |
| Test tool    | PowerShell `Invoke-WebRequest`         |
| Concurrency  | 1 VU (sequential)                      |
| Dataset type | bounded_synthetic                      |

---

## 5. Pass/Fail Criteria

A scenario **passes** when both conditions are met:

- `p95_ms` ≤ scenario threshold
- `error_rate_pct` = 0% (for non-auth scenarios)

S08 is measured as middleware overhead; FAIL indicates overhead exceeds 2 000ms ceiling.

---

## 6. Deliverables

- Performance scripts (4 `.ps1` files in `performance/scripts/`)
- Raw result JSON files (3 files in `performance/results/`)
- Execution logs (3 files in `evidence/logs/`)
- Metrics CSVs/JSONs (4 files in `metrics/`)
- Chart CSVs (4 files + instructions in `charts/`)
- Documentation (7 `.md` files in `docs/`)
- Traceability matrix (`TRACEABILITY.md`)
- Changelog (`CHANGELOG.md`)
- Module README (`README.md`)

---

## 7. Risks and Mitigations

| Risk                                         | Mitigation                                                                                |
| -------------------------------------------- | ----------------------------------------------------------------------------------------- |
| k6 not available                             | Use PowerShell `Invoke-WebRequest`; disclose as bounded synthetic                         |
| /api/login timeout                           | Exclude from scenarios; document as known issue                                           |
| /news HTTP 500                               | Exclude from scenarios; document as known issue                                           |
| Integration API requires institutional token | Measure rejection pathway only; document test limitation                                  |
| Dev vs. production latency gap               | All thresholds set conservatively (5–10s); note dev baseline may not represent production |

---

_KazUTB Digital Library — QA experimental evaluation layer (Phase 3) Part 1 — 2026-05-13_
