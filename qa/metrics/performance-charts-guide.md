# Performance Charts Guide

## Source

- Main source: qa/metrics/performance-timeseries.csv
- Full metric detail:
    - qa/metrics/performance-baseline-before.csv
    - qa/metrics/performance-baseline-after.csv

## Recommended Charts

1. Grouped bar chart (Before vs After)

- X-axis: metric
- Y-axis: seconds
- Metrics: docker_startup_time, home_cold_ttfb, home_warm_ttfb_avg, api_catalog-db_p50, api_subjects_p50, api_landing_p50

2. Delta percentage bar chart

- X-axis: metric
- Y-axis: percent change
- Formula: (after - before) / before \* 100

3. API latency panel

- Separate chart for API p50 and avg metrics to highlight request-path improvement.

## Interpretation Rules

- Lower is better for all time metrics.
- If p50 drops but p95 remains high, investigate tail latency (cold cache, occasional heavy path).
- For startup measurements, track at least 5 runs per branch for stable CI trend signals.
