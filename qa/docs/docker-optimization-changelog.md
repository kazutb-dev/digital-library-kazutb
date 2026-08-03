# Docker Optimization Changelog

Date: 2026-05-13

## Applied Changes

### docker-compose.yml

- Added PostgreSQL runtime tuning flags:
    - shared_buffers=256MB
    - work_mem=8MB
    - maintenance_work_mem=64MB
    - effective_cache_size=768MB
    - max_connections=120
    - random_page_cost=1.1
- Added resource limits/reservations for postgres, app, and frontend-dev.
- Changed bind mounts to cached mode for app and frontend-dev:
    - ./:/app:cached
- Added healthcheck start_period (30s) for app to reduce false-negative early checks.
- Made frontend file watching less aggressive by default:
    - CHOKIDAR_USEPOLLING default false
    - CHOKIDAR_INTERVAL default 250ms

### docker/nginx.conf

- Added keepalive tuning:
    - keepalive_timeout 65
    - keepalive_requests 1000
- Enabled gzip compression for textual payloads.
- Enabled fastcgi_keep_conn for better PHP-FPM connection reuse.

### docker/php.ini

- Extended OPcache and path cache tuning:
    - opcache.enable_cli=1
    - opcache.interned_strings_buffer=24
    - opcache.max_wasted_percentage=10
    - opcache.revalidate_freq=1
    - opcache.fast_shutdown=1
    - realpath_cache_size=4096K
    - realpath_cache_ttl=600

## Expected Impact

- Faster warm request handling.
- Lower startup and runtime jitter under Docker Desktop on Windows.
- Lower background CPU pressure from file watching.
