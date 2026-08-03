# Docker Performance Notes

Date: 2026-05-13

## What Was Optimized

- Container resource limits were introduced to prevent noisy-neighbor effects.
- App and frontend bind mounts use cached mode for better host/container sync behavior on Windows.
- Frontend watcher polling is now opt-in by default (can be re-enabled via environment).
- Nginx/PHP cache and connection settings were tuned for Laravel request throughput.
- PostgreSQL runtime memory/cost settings were tuned for this local workload profile.

## Operational Caveats

- compose down/up based benchmarks include warmup variance from first app boot.
- If live-reload reliability is needed on specific host setups, set CHOKIDAR_USEPOLLING=true.
- deploy.resources in Compose is used here as a runtime intent and may be interpreted differently depending on Docker Compose mode/version.

## Recommended Command Set

- Start stack: docker compose up -d --build app frontend-dev
- Check state: docker compose ps
- Perf baseline: pwsh -File qa/performance/scripts/collect-performance-baseline.ps1 -Phase before
- Perf after: pwsh -File qa/performance/scripts/collect-performance-baseline.ps1 -Phase after
