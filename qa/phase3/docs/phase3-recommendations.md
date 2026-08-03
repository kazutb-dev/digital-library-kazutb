# experimental evaluation layer (Phase 3) Part 1 — Performance Testing: Recommendations

**Project:** KazUTB Digital Library
**Phase:** 3 Part 1 — Performance Testing
**Document:** Recommendations
**Date:** 2026-05-13
**Based on:** 8 bottlenecks identified in phase3-bottleneck-analysis.md

---

## Priority 1 — CRITICAL (resolve before any production deployment)

### R-001: Enable and Tune PHP OPcache (addresses BN-001, BN-008)

**Rationale:** All endpoints show 3.0–3.8s latency consistent with PHP cold-path bootstrap. OPcache eliminates source-file parsing overhead and significantly reduces per-request PHP startup cost.

**Implementation:**

```ini
; php.ini / php.ini-production
opcache.enable=1
opcache.memory_consumption=256
opcache.max_accelerated_files=20000
opcache.validate_timestamps=0   ; disable in production; use deploy step to reset
opcache.jit_buffer_size=100M
opcache.jit=1255
```

**Expected outcome:** 30–60% latency reduction on warm path (industry benchmark for Laravel + OPcache).
**Test validation:** Re-run S01 after OPcache enable; compare avg_ms.

---

### R-002: Add Redis Response Caching for Public Read API (addresses BN-001, BN-004, BN-005)

**Rationale:** The public catalog, subjects, landing, and external-resources APIs return read-only data that changes infrequently (seconds–minutes). Caching responses in Redis removes DB query overhead from hot paths.

**Implementation (Laravel):**

```php
// In CatalogReadService or route-level middleware:
return Cache::remember('catalog:landing', 300, fn() => $this->buildLandingData());
return Cache::remember('catalog:subjects', 600, fn() => Subject::active()->get());
return Cache::remember('external:resources', 300, fn() => $this->fetchResources());
```

**Expected outcome:** Near-zero latency on cache hits; reduces DB load by 90%+ on repeated identical requests.
**TTL guidance:** landing=5min, subjects=10min, external-resources=5min.

---

### R-003: Refactor Integration Middleware Short-Circuit (addresses BN-002)

**Rationale:** Auth rejection latency (3 237ms) exceeds threshold (2 000ms) because the middleware chain runs the full PHP bootstrap before checking authentication. Token validation must fire before any database connection or service resolution.

**Implementation:**

1. Move `IntegrationAuthMiddleware` to the top of the integration route group middleware stack.
2. Ensure the middleware returns early (before `$next($request)`) without instantiating any service or querying the DB:

```php
public function handle(Request $request, Closure $next): Response
{
    $token = $request->bearerToken();
    if (! $token || ! $this->isValidToken($token)) {
        return response()->json(['error' => 'Unauthorized'], 401);
    }
    return $next($request);
}
```

3. Use a stateless JWT or HMAC check in `isValidToken()` — no DB lookup on rejection path.
4. Optionally configure Nginx `auth_request` module to reject invalid tokens before PHP-FPM for maximum short-circuit efficiency.

**Expected outcome:** Rejection latency drops from 3 237ms to < 50ms (Nginx) or < 200ms (PHP early return).

---

## Priority 2 — HIGH (resolve before beta launch)

### R-004: Compress Web Catalog Responses (addresses BN-003)

**Rationale:** `/catalog` returns 130KB uncompressed HTML. Gzip compression at 6:1 ratio would reduce transfer to ~22KB; Brotli at 8:1 to ~16KB.

**Implementation (Nginx):**

```nginx
gzip on;
gzip_types text/html application/json text/css application/javascript;
gzip_comp_level 6;
gzip_min_length 1024;
```

**Expected outcome:** Significant latency reduction for mobile/regional clients on slow connections.

---

### R-005: Implement Blade Partial Caching for Catalog View (addresses BN-003)

**Rationale:** The 130KB HTML page is rebuilt on every request. The catalog listing section changes infrequently and can be fragment-cached.

**Implementation:**

```php
// resources/views/catalog.blade.php
@cache('catalog-list', 300)
    @foreach($items as $item)
        {{-- catalog card --}}
    @endforeach
@endcache
```

Or use Laravel's `cache()` helper in the controller to cache the pre-built data array.

---

### R-006: Tune PHP-FPM Pool for Expected Concurrent Load (addresses BN-004)

**Rationale:** With 4 workers and 3.4s avg latency, maximum throughput is ~1.2 rps. For a 100-user peak, at minimum 50 workers + async DB query are needed.

**Implementation:**

```ini
; php-fpm pool config (www.conf)
pm = dynamic
pm.max_children = 50
pm.start_servers = 10
pm.min_spare_servers = 5
pm.max_spare_servers = 20
pm.max_requests = 1000
```

**Expected outcome:** Concurrent throughput scales roughly linearly with worker count up to database connection limit.

---

### R-007: Add Index on subjects Table (addresses BN-005)

**Rationale:** /api/v1/subjects p95=3969ms (highest variance among passing endpoints). A covering index can eliminate full-table scans.

**Implementation:**

```sql
CREATE INDEX idx_subjects_active_name ON subjects (is_active, name);
```

Or as Laravel migration:

```php
$table->index(['is_active', 'name']);
```

**Expected outcome:** Query time reduction from seconds to < 100ms for filtered subject lookups.

---

## Priority 3 — MEDIUM (resolve before production GA)

### R-008: Fix /news HTTP 500 (addresses BN-006)

**Steps:**

1. Run `php artisan route:list | grep news` — confirm route is registered
2. Check `storage/logs/laravel.log` for the specific exception
3. Verify LibraryNews DB migration is applied: `php artisan migrate:status`
4. Add feature test: `$this->get('/news')->assertStatus(200)`

### R-009: Diagnose /api/login Timeout (addresses BN-007)

**Steps:**

1. Profile: `php artisan tinker` → `Hash::make('password')` — verify bcrypt time < 200ms
2. Check `config/auth.php` — confirm `bcrypt` rounds ≤ 12 in dev
3. Check Sanctum personal access token table for excessive row count → add cleanup job
4. Add `POST /api/login` to a targeted profiling run with Xdebug + Clockwork

### R-010: Full Concurrent Load Test on Linux Staging (addresses BN-001, BN-004)

**Rationale:** All experimental evaluation layer (Phase 3) Part 1 results were collected on a Windows dev workstation with 1-VU sequential tooling. Actual production capacity assessment requires k6 at 50–200 VU on Linux staging.

**Steps:**

1. Provision Linux staging server (Ubuntu 22.04 LTS, Nginx, PHP 8.4, PostgreSQL)
2. Install k6: `sudo apt install k6`
3. Run k6 scripts with `--vus 50 --duration 5m` against `GET /api/v1/landing`
4. Compare results to this baseline; identify capacity ceiling

---

## Summary Table

| Rec                            | Bottleneck             | Priority | Est. Effort | Expected Gain                      |
| ------------------------------ | ---------------------- | -------- | ----------- | ---------------------------------- |
| R-001 OPcache                  | BN-001, BN-008         | P1       | 0.5h        | 30–60% latency reduction           |
| R-002 Redis caching            | BN-001, BN-004, BN-005 | P1       | 4h          | 90%+ DB query elimination          |
| R-003 Middleware short-circuit | BN-002                 | P1       | 2h          | Rejection latency 3237ms → < 200ms |
| R-004 Nginx Gzip               | BN-003                 | P2       | 0.5h        | 130KB → ~22KB HTML transfer        |
| R-005 Blade caching            | BN-003                 | P2       | 2h          | Catalog render overhead eliminated |
| R-006 PHP-FPM tuning           | BN-004                 | P2       | 1h          | Concurrent user capacity ×10–15    |
| R-007 subjects index           | BN-005                 | P2       | 0.5h        | p95 reduction                      |
| R-008 Fix /news 500            | BN-006                 | P3       | 1h          | Module availability restored       |
| R-009 Fix /api/login           | BN-007                 | P3       | 2h          | Auth load testing unblocked        |
| R-010 k6 staging test          | BN-001, BN-004         | P3       | 4h          | Production capacity baseline       |

---

_KazUTB Digital Library — QA experimental evaluation layer (Phase 3) Part 1 — 2026-05-13_
