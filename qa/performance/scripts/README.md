# Performance: k6 Scripts

k6 load test scripts for KazUTB Digital Library performance baseline.

## Scripts
- catalog-load.js — GET /api/v1/catalog-db with search params
- login-stress.js — POST /login concurrent auth
- circulation-load.js — POST /internal/circulation/checkouts

## Running
```bash
# Install k6: https://k6.io/docs/get-started/installation/
k6 run performance/scripts/catalog-load.js
k6 run --vus 10 --duration 30s performance/scripts/catalog-load.js
```
