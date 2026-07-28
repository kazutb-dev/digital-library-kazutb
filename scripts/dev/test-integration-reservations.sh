#!/usr/bin/env bash
set -euo pipefail

cd "$(dirname "$0")/../.."

run_php_tests() {
  if php artisan list --raw 2>/dev/null | grep -q '^test$'; then
    php artisan test tests/Feature/Api/Integration/ReservationMutateTest.php \
      tests/Feature/Api/Integration/ReservationReadTest.php \
      tests/Feature/Api/Integration/ReservationMutateRetirementConsistencyTest.php
  elif [ -f vendor/bin/phpunit ]; then
    php vendor/bin/phpunit \
      tests/Feature/Api/Integration/ReservationMutateTest.php \
      tests/Feature/Api/Integration/ReservationReadTest.php \
      tests/Feature/Api/Integration/ReservationMutateRetirementConsistencyTest.php
  else
    echo "PHPUnit binary not found at vendor/bin/phpunit."
    echo "Run: composer install"
    exit 127
  fi
}

if command -v php >/dev/null 2>&1; then
  run_php_tests
elif command -v docker >/dev/null 2>&1 && docker compose ps app >/dev/null 2>&1; then
  docker compose exec -T app sh -lc '
    if php artisan list --raw 2>/dev/null | grep -q "^test$"; then
      php artisan test \
        tests/Feature/Api/Integration/ReservationMutateTest.php \
        tests/Feature/Api/Integration/ReservationReadTest.php \
        tests/Feature/Api/Integration/ReservationMutateRetirementConsistencyTest.php
    elif [ -f vendor/bin/phpunit ]; then
      php vendor/bin/phpunit \
        tests/Feature/Api/Integration/ReservationMutateTest.php \
        tests/Feature/Api/Integration/ReservationReadTest.php \
        tests/Feature/Api/Integration/ReservationMutateRetirementConsistencyTest.php
    else
      echo "PHPUnit binary not found at vendor/bin/phpunit inside container."
      echo "Run: docker compose exec -T app composer install"
      exit 127
    fi
  '
else
  echo "PHP runtime not found."
  echo "Run with local PHP, or start containers and run:"
  echo "  docker compose up -d"
  echo "  docker compose exec -T app php artisan test tests/Feature/Api/Integration/ReservationMutateTest.php tests/Feature/Api/Integration/ReservationReadTest.php tests/Feature/Api/Integration/ReservationMutateRetirementConsistencyTest.php"
  exit 127
fi
