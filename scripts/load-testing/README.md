# Isolated HTTP load harness

`http-load.mjs` uses Node's built-in HTTP client and emits one JSON result. It
must target the isolated audit network, never `10.0.1.17:80`.

Example from a resource-limited generator container:

```bash
docker run --rm --network kazutb_audit_performance \
  --cpus 0.5 --memory 256m \
  -v "$PWD/scripts/load-testing:/work:ro" \
  -e LOAD_BASE_URL=http://perf-app \
  -e LOAD_SCENARIO=public-read \
  -e LOAD_CONCURRENCY=10 \
  -e LOAD_DURATION_SECONDS=60 \
  node:22-alpine node /work/http-load.mjs
```

Supported scenarios are `public-read`, `catalog-search`, and `public-shell`.
The result includes RPS, throughput, status distribution, error rate, and
p50/p90/p95/p99 latency. The generator is intentionally dependency-free so a
lock-file update cannot alter application dependencies.
