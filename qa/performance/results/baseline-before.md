# Performance Baseline (before)

Timestamp: 2026-05-13T04:04:00+05:00

## Core Metrics

| Metric | Value | Unit | Notes |
|---|---:|---|---|
| docker_startup_time | 42.087 | s | compose down + up postgres,app,frontend-dev until app healthy/running |
| home_cold_ttfb | 4.3838 | s | first request after stack restart |
| home_cold_total | 4.3839 | s | first request after stack restart |
| home_warm_ttfb_avg | 2.1041 | s | average of 8 sequential requests |
| home_warm_total_avg | 2.1042 | s | average of 8 sequential requests |
| api_catalog-db_p50 | 3.0913 | s | http://localhost/api/v1/catalog-db?limit=20&sort=popular |
| api_catalog-db_p95 | 3.1796 | s | http://localhost/api/v1/catalog-db?limit=20&sort=popular |
| api_catalog-db_avg | 3.0867 | s | http://localhost/api/v1/catalog-db?limit=20&sort=popular |
| api_subjects_p50 | 1.7846 | s | http://localhost/api/v1/subjects |
| api_subjects_p95 | 1.8399 | s | http://localhost/api/v1/subjects |
| api_subjects_avg | 1.777 | s | http://localhost/api/v1/subjects |
| api_landing_p50 | 3.0182 | s | http://localhost/api/v1/landing |
| api_landing_p95 | 3.2234 | s | http://localhost/api/v1/landing |
| api_landing_avg | 3.0423 | s | http://localhost/api/v1/landing |
| db_query_top1 | 9.665 | ms | SELECT document_id, title_display FROM app.document_detail_v WHERE LOWER(COALESCE(title_display, title_raw, '')) LIKE '%data%' LIMIT 30; |
| db_query_top2 | 5.606 | ms | SELECT document_id, title_display FROM app.document_detail_v ORDER BY publication_year DESC NULLS LAST LIMIT 30; |
| db_query_top3 | 1.974 | ms | SELECT COUNT(*) FROM app.document_detail_v; |
| container_cpu_kazutb-library-frontend-dev-1 | 55.68% | percent | docker stats snapshot |
| container_mem_kazutb-library-frontend-dev-1 | 174.8MiB / 7.383GiB | raw | docker stats snapshot |
| container_cpu_kazutb-library-app-1 | 0.02% | percent | docker stats snapshot |
| container_mem_kazutb-library-app-1 | 72.46MiB / 7.383GiB | raw | docker stats snapshot |
| container_cpu_kazutb-library-postgres-1 | 0.01% | percent | docker stats snapshot |
| container_mem_kazutb-library-postgres-1 | 83.11MiB / 7.383GiB | raw | docker stats snapshot |
| container_cpu_reverent_goodall | 0.00% | percent | docker stats snapshot |
| container_mem_reverent_goodall | 109.3MiB / 7.383GiB | raw | docker stats snapshot |
| container_cpu_silly_moser | 0.00% | percent | docker stats snapshot |
| container_mem_silly_moser | 49.47MiB / 7.383GiB | raw | docker stats snapshot |
| container_cpu_recursing_bell | 0.00% | percent | docker stats snapshot |
| container_mem_recursing_bell | 137MiB / 7.383GiB | raw | docker stats snapshot |
| laravel_telescope_available | 0 | bool | vendor/laravel/telescope presence |
| laravel_debugbar_available | 0 | bool | vendor/barryvdh/laravel-debugbar presence |
