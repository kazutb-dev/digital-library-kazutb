# Performance Gate Proposal

Date: 2026-05-13

## Goal

Prevent performance regressions on critical runtime paths during CI.

## Proposed Gate Inputs

- Script: qa/performance/scripts/collect-performance-baseline.ps1
- Output CSV from current branch (candidate)
- Reference CSV from baseline target branch (main)

## Gate Metrics and Thresholds

Use relative regression thresholds (fail if slower than baseline by more than threshold):

- docker_startup_time: +20%
- home_cold_ttfb: +20%
- home_warm_ttfb_avg: +15%
- api_catalog-db_p50: +15%
- api_subjects_p50: +15%
- api_landing_p50: +15%

Optional warning-only metrics:

- api_catalog-db_p95: +20%
- api_subjects_p95: +20%
- api_landing_p95: +20%

## Suggested CI Workflow

1. Start stack in CI runner.
2. Collect candidate metrics.
3. Compare against reference CSV.
4. Fail job if any hard-threshold regression detected.
5. Publish CSV + markdown artifacts in CI for traceability.

## Why Relative Thresholds

- Absolute timings fluctuate across CI hosts.
- Relative thresholds are more robust and still protect against regressions.
