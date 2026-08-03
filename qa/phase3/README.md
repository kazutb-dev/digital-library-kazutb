# QA experimental evaluation layer (Phase 3) — experimental evaluation layer Final Package

Project: KazUTB Digital Library
Status: Complete (Part 1, Part 2, Part 3)
Date: 2026-05-13

---

## Overview

This directory now contains the complete experimental evaluation layer (Phase 3) package:

1. Part 1: Performance testing baseline and bottleneck analysis.
2. Part 2: Mutation campaign and mutation score analysis.
3. Part 3: Bounded chaos/fault-injection testing and resilience analysis.

Key phase outcomes:

1. Performance: 8/9 scenarios PASS, 1 FAIL (integration boundary latency threshold).
2. Mutation: 14 mutants executed, 12 killed, 2 survived, overall score 85.71%.
3. Chaos: 4 scenarios executed, fault-phase availability 50%, recovery-phase availability 100%, zero cascading failures.

---

## Directory Structure

```
qa/phase3/
├── README.md
├── TRACEABILITY.md
├── CHANGELOG.md
│
├── docs/
│   ├── phase3-performance-test-plan.md
│   ├── phase3-performance-methodology.md
│   ├── phase3-execution-report.md
│   ├── phase3-metrics-report.md
│   ├── phase3-bottleneck-analysis.md
│   ├── phase3-recommendations.md
│   ├── phase3-mutation-plan.md
│   ├── phase3-mutation-execution-report.md
│   ├── phase3-mutation-score-report.md
│   ├── phase3-mutation-gap-analysis.md
│   ├── phase3-mutation-recommendations.md
│   ├── phase3-mutation-final-summary.md
│   ├── phase3-chaos-test-plan.md
│   ├── phase3-chaos-execution-report.md
│   ├── phase3-chaos-metrics-report.md
│   ├── phase3-chaos-lessons-learned.md
│   ├── phase3-experimental-setup.md
│   ├── phase3-observed-vs-expected.md
│   ├── phase3-experimental-final-report.md
│   └── phase3-final-summary.md
│
├── metrics/
│   ├── phase3-performance-results.csv
│   ├── phase3-performance-results.json
│   ├── phase3-mutation-results.csv
│   ├── phase3-mutation-results.json
│   ├── phase3-mutation-score.csv
│   ├── phase3-mutation-score.json
│   ├── phase3-chaos-scenarios.csv
│   ├── phase3-chaos-scenarios.json
│   ├── phase3-chaos-results.csv
│   ├── phase3-chaos-results.json
│   ├── phase3-chaos-metrics.csv
│   ├── phase3-chaos-metrics.json
│   ├── phase3-observed-vs-expected.csv
│   └── phase3-observed-vs-expected.json
│
├── charts/
│   ├── phase3-response-time-chart.csv
│   ├── phase3-mutation-score-chart.csv
│   ├── phase3-chaos-availability-chart.csv
│   ├── phase3-chaos-recovery-chart.csv
│   ├── phase3-chaos-error-propagation-chart.csv
│   └── phase3-chaos-chart-instructions.md
│
├── performance/
│   ├── scripts/
│   └── results/
│
├── mutation/
│   ├── plans/
│   └── results/
│
├── chaos/
│   ├── scripts/
│   └── results/
│
└── evidence/
    ├── logs/
    ├── references/
    └── screenshots/
```

---

## Quick Results

### Part 1 Performance

| Area      | Result                  |
| --------- | ----------------------- |
| Scenarios | 9                       |
| Pass      | 8                       |
| Fail      | 1 (S08-BND-INTEGRATION) |

### Part 2 Mutation

| Area              | Result |
| ----------------- | -----: |
| Mutants executed  |     14 |
| Killed            |     12 |
| Survived          |      2 |
| Overall score (%) |  85.71 |

### Part 3 Chaos

| Area                            |  Result |
| ------------------------------- | ------: |
| Chaos scenarios                 |       4 |
| Fault-phase availability (%)    |   50.00 |
| Recovery-phase availability (%) |  100.00 |
| Mean MTTR proxy (ms)            | 3391.02 |
| Cascading failures              |       0 |

---

## Re-Run Commands

### Performance (Part 1)

```powershell
pwsh -File "performance/scripts/run-phase3-performance.ps1" -BaseUrl "http://localhost"
```

### Mutation (Part 2)

```powershell
pwsh -File "mutation/plans/run-phase3-mutation.ps1"
```

### Chaos (Part 3)

```powershell
pwsh -File "chaos/scripts/run-phase3-chaos.ps1"
```

---

## Methodology Disclosure

1. Performance uses bounded synthetic sequential HTTP timing on local Windows environment.
2. Mutation uses controlled manual mutation with scripted source restoration and targeted tests.
3. Chaos uses bounded synthetic faults (proxy timeout, endpoint unavailability simulation, injected latency, CPU pressure) with explicit fault/recovery phase reporting.
4. No fabricated screenshots are included; evidence is log, metric, and generated chart based.

---

KazUTB Digital Library — QA experimental evaluation layer (Phase 3)
