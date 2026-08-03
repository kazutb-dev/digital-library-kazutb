#!/usr/bin/env bash
set -euo pipefail

test_database="digital_library_test"

case "$test_database" in
  *_test) ;;
  *) echo "Refusing unsafe test database name: $test_database" >&2; exit 2 ;;
esac

runtime_database="$(docker compose exec -T app sh -lc 'printf %s "$DB_DATABASE"')"
if [[ "$runtime_database" == "$test_database" ]]; then
  echo "Refusing to use the runtime database as the test database." >&2
  exit 2
fi

exists="$(docker compose exec -T postgres sh -lc "psql -U \"\$POSTGRES_USER\" -d postgres -Atc \"SELECT 1 FROM pg_database WHERE datname = '$test_database'\"")"
if [[ "$exists" != "1" ]]; then
  docker compose exec -T postgres sh -lc "createdb -U \"\$POSTGRES_USER\" '$test_database'"
fi

# migrate:fresh only discovers tables on Laravel's search path. The legacy
# integration contract deliberately lives in the `app` schema, so reset that
# schema explicitly — after the `_test` and runtime-database guards above.
docker compose exec -T postgres sh -lc \
  "psql -v ON_ERROR_STOP=1 -U \"\$POSTGRES_USER\" -d '$test_database' -c 'DROP SCHEMA IF EXISTS app CASCADE' -c 'CREATE SCHEMA app'"

docker compose exec -T \
  -e APP_ENV=testing \
  -e DB_CONNECTION=pgsql \
  -e DB_DATABASE="$test_database" \
  -e CACHE_STORE=array \
  -e SESSION_DRIVER=array \
  -e QUEUE_CONNECTION=sync \
  app php artisan migrate:fresh --seed --force

docker compose exec -T \
  -e APP_ENV=testing \
  -e DB_CONNECTION=pgsql \
  -e DB_DATABASE="$test_database" \
  -e CACHE_STORE=array \
  -e SESSION_DRIVER=array \
  -e QUEUE_CONNECTION=sync \
  app php vendor/bin/phpunit -c phpunit.postgres.xml "$@"
