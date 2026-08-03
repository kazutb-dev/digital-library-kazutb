# Performance Comparison Report

Date: 2026-05-13
Scope: Docker runtime and Laravel request-path optimization

## Before vs After (Core Metrics)

| Metric                  | Before |  After |   Delta |  Change |
| ----------------------- | -----: | -----: | ------: | ------: |
| docker_startup_time (s) | 42.087 | 33.703 |  -8.384 | -19.92% |
| home_cold_ttfb (s)      | 4.3838 | 3.8372 | -0.5466 | -12.47% |
| home_warm_ttfb_avg (s)  | 2.1041 | 1.8289 | -0.2752 | -13.08% |
| api_catalog-db_p50 (s)  | 3.0913 | 1.7552 | -1.3361 | -43.22% |
| api_catalog-db_avg (s)  | 3.0867 | 1.5767 | -1.5100 | -48.92% |
| api_subjects_p50 (s)    | 1.7846 | 0.3117 | -1.4729 | -82.53% |
| api_subjects_avg (s)    | 1.7770 | 0.8033 | -0.9737 | -54.79% |
| api_landing_p50 (s)     | 3.0182 | 1.8672 | -1.1510 | -38.14% |
| api_landing_avg (s)     | 3.0423 | 1.7472 | -1.2951 | -42.57% |

## DB Query Notes

- DB query latencies remain low (milliseconds), confirming bottleneck was mainly app/runtime layer.
- Query #2 improved (5.606ms -> 4.797ms, -14.43%).
- Query #1 and #3 fluctuated upward in this sample window; values are still low absolute latency.

## Container Snapshot Notes

- frontend-dev CPU reduced from 55.68% baseline snapshot to 0.00% in after snapshot.
- app/postgres CPU increased in after snapshot during active benchmark execution window, indicating work shifted to request and DB execution rather than idle watcher overhead.

## Reliability/Measurement Notes

- Startup still had transient early "Empty reply from server" events but reached stable completion and wrote artifacts in both phases.

## Conclusion

- Performance objective met with measurable gains across startup and all key API paths.
- Strongest wins: catalog/subjects/landing response times and reduced startup duration.
- Remaining optimization headroom: startup warmup stability and optional endpoint-level response caching.
