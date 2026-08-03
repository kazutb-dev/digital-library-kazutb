# experimental evaluation layer (Phase 3) — Experimental Setup Documentation

Project: KazUTB Digital Library
Date: 2026-05-13

## Environment

1. OS: Windows 11 (local workstation)
2. Runtime model: local Nginx + PHP-FPM/Laravel
3. Hardware snapshot (from experimental evaluation layer (Phase 3) evidence):

- CPU cores: 8
- Total memory: 15613.1 MB
- Available memory during baseline snapshot: 2550.3 MB
- PHP process count observed: 4

## Tooling Versions

1. PHP: 8.4.19
2. Composer: 2.8.6
3. PowerShell: 7.6.1
4. Python: 3.13.2

## Scripts and Configurations Used

### Performance (Part 1)

1. `qa/phase3/performance/scripts/perf-catalog-api.ps1`
2. `qa/phase3/performance/scripts/perf-web-public.ps1`
3. `qa/phase3/performance/scripts/perf-integration-boundary.ps1`
4. `qa/phase3/performance/scripts/run-phase3-performance.ps1`

### Mutation (Part 2)

1. `qa/phase3/mutation/plans/run-phase3-mutation.ps1`

### Chaos (Part 3)

1. `qa/phase3/chaos/scripts/run-phase3-chaos.ps1`

## Dataset Inventory Used in experimental evaluation layer (Phase 3)

1. Performance: `qa/phase3/metrics/phase3-performance-results.csv`
2. Mutation: `qa/phase3/metrics/phase3-mutation-results.csv`, `qa/phase3/metrics/phase3-mutation-score.csv`
3. Chaos: `qa/phase3/metrics/phase3-chaos-results.csv`, `qa/phase3/metrics/phase3-chaos-metrics.csv`
4. Comparative synthesis: `qa/phase3/metrics/phase3-observed-vs-expected.csv`

## CI/CD Relevance

1. Existing repository CI focuses on lint/build/tests and does not yet enforce chaos metrics.
2. Mutation and chaos runs in this phase were executed locally in bounded mode with reproducible scripts and logs.
3. Recommended future integration: nightly bounded mutation + chaos checks with artifact upload.

## Reproducibility Notes

1. All scripts are versioned under `qa/phase3/`.
2. Each run produces timestamped output files and scenario logs.
3. Fault models are explicitly labeled (`bounded synthetic` or `synthetic fault model`) to avoid ambiguity.
4. No fabricated screenshots were produced; evidence is log-and-metrics driven.

---

KazUTB Digital Library — QA experimental evaluation layer (Phase 3)
