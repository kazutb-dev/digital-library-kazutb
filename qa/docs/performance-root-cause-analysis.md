# Performance Root Cause Analysis

Date: 2026-05-13
Project: kazutb-dev/digital-library-kazutb

## Evidence Summary

- Baseline startup time was 42.087s before optimization and 33.703s after optimization.
- Homepage cold TTFB was 4.3838s before and 3.8372s after.
- API endpoints showed strongest regressions in app runtime path, not in raw DB queries:
    - /api/v1/catalog-db p50: 3.0913s (before)
    - /api/v1/landing p50: 3.0182s (before)
    - /api/v1/subjects p50: 1.7846s (before)
- Representative DB query latency was already low in baseline (1.974-9.665 ms), so the major delay was above the DB layer.
- Frontend dev container consumed high background CPU in baseline snapshot (55.68%).

## Root Causes

1. Runtime overhead above database layer

- App/API latency was in seconds while DB queries were in milliseconds.
- This indicates most latency came from request lifecycle and repeated expensive app-level operations, not SQL execution itself.

2. Landing endpoint performed extra heavy work

- Landing path depended on catalog search path that computed full totals and location aggregation.
- That work is unnecessary for short landing previews and inflated response time.

3. Docker live-sync overhead on Windows bind mounts

- Bind-mounted source with polling watchers causes extra filesystem churn.
- Frontend watcher CPU was high before optimization, reducing host/container scheduling efficiency.

4. Startup instability window

- During stack start, repeated "Empty reply from server" events were observed before the app became responsive.
- This increases cold-start time and delays baseline measurement completion.

5. Conservative web/PHP defaults left throughput headroom unused

- Nginx and PHP cache-related defaults were not fully tuned for this workload.

## Conclusions

- The largest wins come from reducing unnecessary app runtime work and minimizing dev watcher overhead.
- DB itself is not the main bottleneck for the measured scenarios.
- Further gains are likely from endpoint-specific response caching and optional warmup/preload strategy.
