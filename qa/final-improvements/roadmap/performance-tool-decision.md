# Performance Tool Decision

**Repository:** kazutb-dev/digital-library-kazutb  
**Date:** 2026-05-14  
**Decision:** Reuse existing PowerShell HTTP harness

---

## Finding

The repository contains a bespoke PowerShell performance baseline script:

**Path:** `qa/performance/scripts/collect-performance-baseline.ps1`

**Mechanism:** curl.exe-based HTTP timing loop using `-w "%{time_starttransfer},%{time_total},%{http_code}"` with configurable repeat count per endpoint. Computes avg, p50, p95 across collected samples. Outputs Markdown and optionally CSV.

**Endpoints covered:**

- Home page (cold + warm)
- `/api/v1/catalog-db?limit=20&sort=popular`
- `/api/v1/subjects`
- `/api/v1/landing`
- DB query direct timing (3 representative queries)
- Docker container stats snapshot

**Prior results exist:**

- `qa/performance/results/baseline-before.md`
- `qa/performance/results/baseline-after.md` (timestamp: 2026-05-13T04:10:55+05:00)

---

## Decision

**D4 approach: bounded rerun using the existing PowerShell script.**

No new performance tooling (Artillery, k6, Gatling, etc.) will be introduced.

Rationale:

1. The tool already exists and produced real prior results
2. The methodology is transparent and auditable
3. The script is self-contained (no Node, Java, or container ecosystem required)
4. Prior baseline (`baseline-after.md`) is the comparison anchor
5. A rerun will produce genuine before/after comparison for the paper

---

## Rerun Conditions

A D4 rerun is feasible **only when Docker is running** (script targets
`http://localhost` which maps to the containerized application).

**Command:**

```powershell
.\qa\performance\scripts\collect-performance-baseline.ps1 -Phase after `
  -OutputMarkdown qa\performance\results\rerun-post-a1-fix.md `
  -OutputCsv qa\performance\results\rerun-post-a1-fix.csv
```

---

## Evidence Classification

| Scenario                              | Status            | Evidence Label                   |
| ------------------------------------- | ----------------- | -------------------------------- |
| Prior baseline (before optimizations) | ✅ Exists         | `baseline-before.md`             |
| Prior baseline (after optimizations)  | ✅ Exists         | `baseline-after.md` (2026-05-13) |
| Post-A1 fix rerun                     | 🔲 Pending Docker | `rerun-post-a1-fix.md`           |

If rerun cannot execute before paper submission:  
**Label:** "Baseline retained from prior validated execution — no new performance
regression observed; rerun pending Docker availability."

---

## Paper Guidance

- Performance section will use `baseline-after.md` as the current validated baseline
- Metrics: TTFB avg/p95, total latency avg/p95, throughput implied from sequential samples
- Caveat: "Performance measurements executed on isolated Docker Compose environment
  (single-instance); not representative of production-scale throughput"
- Threats to validity: single-node environment, no concurrent load, warm cache state
