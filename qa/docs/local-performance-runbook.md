# Local Performance Runbook

## Prerequisites

- Docker Desktop running
- Required env configured in project .env
- Free ports: 80, 5173, 5433

## 1) Collect baseline before changes

- Command:
    - pwsh -File qa/performance/scripts/collect-performance-baseline.ps1 -Phase before -OutputCsv qa/metrics/performance-baseline-before.csv -OutputMarkdown qa/performance/results/baseline-before.md

## 2) Apply performance changes

- Make Docker/runtime/app changes.
- Rebuild/restart:
    - docker compose up -d --build app
    - docker compose restart postgres frontend-dev

## 3) Collect baseline after changes

- Command:
    - pwsh -File qa/performance/scripts/collect-performance-baseline.ps1 -Phase after -OutputCsv qa/metrics/performance-baseline-after.csv -OutputMarkdown qa/performance/results/baseline-after.md

## 4) Build comparison artifacts

- Update:
    - qa/docs/performance-comparison-report.md
    - qa/metrics/performance-timeseries.csv

## 5) Health checks if startup is unstable

- docker compose ps
- docker compose logs --tail=200 app
- curl.exe -sS --retry 20 --retry-delay 1 --retry-all-errors -o NUL -w "http=%{http_code} ttfb=%{time_starttransfer} total=%{time_total}`n" http://localhost/

## 6) Acceptance criteria

- Before and after CSV/Markdown exist.
- Comparison report includes percentage deltas.
- No unresolved runtime errors in app logs during measurement window.
