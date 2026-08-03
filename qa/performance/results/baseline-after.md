# Performance Baseline (after)

Timestamp: 2026-05-13T04:10:55+05:00

## Core Metrics

| Metric | Value | Unit | Notes |
|---|---:|---|---|
| docker_startup_time | 33.703 | s | compose down + up postgres,app,frontend-dev until app healthy/running |
| home_cold_ttfb | 3.8372 | s | first request after stack restart |
| home_cold_total | 3.8374 | s | first request after stack restart |
| home_warm_ttfb_avg | 1.8289 | s | average of 8 sequential requests |
| home_warm_total_avg | 1.829 | s | average of 8 sequential requests |
| api_catalog-db_p50 | 1.7552 | s | http://localhost/api/v1/catalog-db?limit=20&sort=popular |
| api_catalog-db_p95 | 2.1286 | s | http://localhost/api/v1/catalog-db?limit=20&sort=popular |
| api_catalog-db_avg | 1.5767 | s | http://localhost/api/v1/catalog-db?limit=20&sort=popular |
| api_subjects_p50 | 0.3117 | s | http://localhost/api/v1/subjects |
| api_subjects_p95 | 1.6979 | s | http://localhost/api/v1/subjects |
| api_subjects_avg | 0.8033 | s | http://localhost/api/v1/subjects |
| api_landing_p50 | 1.8672 | s | http://localhost/api/v1/landing |
| api_landing_p95 | 1.9625 | s | http://localhost/api/v1/landing |
| api_landing_avg | 1.7472 | s | http://localhost/api/v1/landing |
| db_query_top1 | 11.367 | ms | SELECT document_id, title_display FROM app.document_detail_v WHERE LOWER(COALESCE(title_display, title_raw, '')) LIKE '%data%' LIMIT 30; |
| db_query_top2 | 4.797 | ms | SELECT document_id, title_display FROM app.document_detail_v ORDER BY publication_year DESC NULLS LAST LIMIT 30; |
| db_query_top3 | 2.457 | ms | SELECT COUNT(*) FROM app.document_detail_v; |
| container_cpu_kazutb-library-frontend-dev-1 | 0.00% | percent | docker stats snapshot |
| container_mem_kazutb-library-frontend-dev-1 | 203.5MiB / 1GiB | raw | docker stats snapshot |
| container_cpu_kazutb-library-app-1 | 3.51% | percent | docker stats snapshot |
| container_mem_kazutb-library-app-1 | 74.75MiB / 1GiB | raw | docker stats snapshot |
| container_cpu_kazutb-library-postgres-1 | 26.51% | percent | docker stats snapshot |
| container_mem_kazutb-library-postgres-1 | 89.12MiB / 768MiB | raw | docker stats snapshot |
| container_cpu_reverent_goodall | 0.00% | percent | docker stats snapshot |
| container_mem_reverent_goodall | 109.3MiB / 7.383GiB | raw | docker stats snapshot |
| container_cpu_silly_moser | 0.00% | percent | docker stats snapshot |
| container_mem_silly_moser | 49.47MiB / 7.383GiB | raw | docker stats snapshot |
| container_cpu_recursing_bell | 0.00% | percent | docker stats snapshot |
| container_mem_recursing_bell | 137MiB / 7.383GiB | raw | docker stats snapshot |
| laravel_telescope_available | 0 | bool | vendor/laravel/telescope presence |
| laravel_debugbar_available | 0 | bool | vendor/barryvdh/laravel-debugbar presence |
