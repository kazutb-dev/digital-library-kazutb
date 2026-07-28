#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT_DIR"

mkdir -p build/test-results

export APP_ENV="${APP_ENV:-testing}"
export APP_KEY="${APP_KEY:-base64:QUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUFBQUE=}"
export DB_CONNECTION="${DB_CONNECTION:-sqlite}"
export DB_DATABASE="${DB_DATABASE:-:memory:}"
export CACHE_STORE="${CACHE_STORE:-array}"
export SESSION_DRIVER="${SESSION_DRIVER:-array}"
export QUEUE_CONNECTION="${QUEUE_CONNECTION:-sync}"

PHP_RUNNER="host"
if ! php -r 'exit(PHP_VERSION_ID >= 80400 ? 0 : 1);' >/dev/null 2>&1; then
  if command -v docker >/dev/null 2>&1; then
    PHP_RUNNER="docker"
  else
    echo "PHP 8.4+ is required for the QA gates, and Docker is not available as a fallback." >&2
    exit 1
  fi
fi

run_php_check() {
  local command="$1"

  if [[ "$PHP_RUNNER" == "host" ]]; then
    bash -lc "$command"
  else
    docker compose run --rm --entrypoint sh -v "$ROOT_DIR":/app app -lc "$command"
  fi
}

run_php_check 'php artisan optimize:clear --ansi'
run_php_check './vendor/bin/pint --test app/Http/Controllers/Api/AuthController.php app/Http/Controllers/Api/CatalogController.php routes/web.php tests/Feature/PublicShellTest.php tests/Feature/CatalogPageTest.php tests/Feature/AccountPageTest.php tests/Feature/InternalAccessBoundaryTest.php tests/Feature/InternalDashboardPageTest.php tests/Feature/InternalReviewPageTest.php tests/Feature/InternalStewardshipPageTest.php tests/Feature/InternalCirculationPageTest.php tests/Feature/Api/AuthHardeningTest.php tests/Feature/Api/ReaderAccessProtectionTest.php tests/Feature/Api/Integration/DocumentManagementTest.php tests/Feature/Api/Integration/IntegrationRateLimitTest.php tests/Feature/Api/Integration/ReservationMutateTest.php'
run_php_check "php artisan test --filter='BibliographyFormatterTest|PublicShellTest|CatalogPageTest|AccountPageTest|InternalAccessBoundaryTest|InternalDashboardPageTest|InternalReviewPageTest|InternalStewardshipPageTest|InternalCirculationPageTest|AuthHardeningTest|ReaderAccessProtectionTest|CatalogDbSearchTest|AuthSessionLifecycleTest|AuthSessionMeTest|BookDetailDbTest|AccountReservationsTest|ReservationMutateTest|DocumentManagementTest|IntegrationRateLimitTest' --log-junit=build/test-results/critical-paths.xml"

if command -v node >/dev/null 2>&1 && node -e "process.exit(Number(process.versions.node.split('.')[0]) >= 20 ? 0 : 1)"; then
  if [[ ! -x node_modules/.bin/vite ]]; then
    npm ci --no-audit --fund=false --no-update-notifier --loglevel=error
  fi

  npm run build
elif command -v docker >/dev/null 2>&1; then
  docker run --rm -v "$ROOT_DIR":/workspace -w /workspace node:22 sh -lc 'npm ci --no-audit --fund=false --no-update-notifier --loglevel=error && npm run build'
else
  echo "Frontend build requires Node 20+ or Docker with node:22 available." >&2
  exit 1
fi

echo "QA verification passed: Pint, critical-path tests, and the frontend production build."
