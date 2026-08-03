# App Performance Optimizations

Date: 2026-05-13

## Applied App-Level Change

### CatalogReadService search path optimization

- File: app/Services/Library/CatalogReadService.php
- Added optional flags to the search method:
    - includeTotal (default true)
    - includeLocations (default true)
- When disabled, the service skips:
    - full result count query
    - location aggregation query

### Landing API lightweight path

- File: app/Http/Controllers/Api/LandingController.php
- Landing preview now calls:
    - search(limit: 6, sort: 'popular', includeTotal: false, includeLocations: false)
- This removes unnecessary expensive work for landing cards.

## Why This Helps

- Landing endpoint needs preview cards, not full pagination metadata and full location map.
- Skipping extra count/location work reduces app-layer latency and improves p50/p95 in measured traffic.

## Behavioral Safety

- Existing search call sites remain unchanged due default values.
- API contracts outside landing remain compatible.
