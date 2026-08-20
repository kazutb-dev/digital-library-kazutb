#!/usr/bin/env bash
set -Eeuo pipefail

# Canonical PostgreSQL test runner. The database name is validated before any
# destructive client command and the Laravel config cache is redirected to a
# unique, non-existent test path for every invocation.

test_database="${TEST_DB_DATABASE:-digital_library_test}"
prepare_only=false
if [[ "${1:-}" == "--prepare-only" ]]; then
    prepare_only=true
    shift
fi
test_demo_login="${TEST_DEMO_LOGIN_ENABLED:-false}"
if [[ "${test_demo_login}" != "true" && "${test_demo_login}" != "false" ]]; then
    echo "Refusing PostgreSQL test reset: TEST_DEMO_LOGIN_ENABLED must be true or false." >&2
    exit 64
fi
if [[ ! "${test_database}" =~ ^[A-Za-z0-9_]+_test$ ]]; then
    echo "Refusing PostgreSQL test reset: TEST_DB_DATABASE must be an identifier ending in _test." >&2
    exit 64
fi

if [[ -z "$(docker compose ps --status running -q postgres 2>/dev/null)" ]]; then
    echo "The compose PostgreSQL service is not running." >&2
    exit 69
fi

runtime_database="$(docker compose exec -T postgres sh -eu -c 'printf %s "${POSTGRES_DB:-}"')"
if [[ -z "${runtime_database}" ]]; then
    echo "The runtime PostgreSQL database name could not be resolved." >&2
    exit 69
fi
if [[ "${runtime_database}" == "${test_database}" ]]; then
    echo "Refusing PostgreSQL test reset: test and runtime databases are identical." >&2
    exit 64
fi

# The three-way runtime check needs a connectable target. Creating a missing
# database is non-destructive and is permitted only after both identifier and
# separation checks above have passed. Existing databases are not changed
# until Laravel config and PDO have independently confirmed the same target.
docker compose exec -T \
    -e KAZUTB_TEST_DATABASE="${test_database}" \
    postgres sh -eu -c '
        export PGPASSWORD="${POSTGRES_PASSWORD}"
        if ! psql --username="${POSTGRES_USER}" --dbname=postgres --tuples-only --no-align \
            --command="select 1 from pg_database where datname = '"'"'${KAZUTB_TEST_DATABASE}'"'"'" | grep -qx 1; then
            createdb --username="${POSTGRES_USER}" --encoding=UTF8 "${KAZUTB_TEST_DATABASE}"
        fi
    '

cache_token="$(date +%s)-$$"
test_cache="/tmp/kazutb-postgres-setup-${cache_token}.php"
container_env=(
    -e APP_ENV=testing
    -e AD_ENABLED=false
    -e APP_DEMO_LOGIN="${test_demo_login}"
    -e APP_DEMO_LOGIN_ENABLED="${test_demo_login}"
    -e APP_CONFIG_CACHE="${test_cache}"
    -e CACHE_STORE=array
    -e DB_CONNECTION=pgsql
    -e DB_DATABASE="${test_database}"
    -e DB_HOST=postgres
    -e DB_PORT=5432
    -e QUEUE_CONNECTION=sync
    -e SESSION_DRIVER=array
)

echo "Shell test database: ${test_database}"
runtime_probe="$({
    docker compose run --rm --no-deps --entrypoint php \
        "${container_env[@]}" \
        app -r '
            require "/app/vendor/autoload.php";
            $app = require "/app/bootstrap/app.php";
            $app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
            echo "env=".app()->environment().PHP_EOL;
            $configuredDatabase = (string) config("database.connections.".config("database.default").".database");
            $pdoDatabase = (string) Illuminate\Support\Facades\DB::selectOne("select current_database() as name")->name;
            echo "db=".$configuredDatabase.PHP_EOL;
            echo "pdo_db=".$pdoDatabase.PHP_EOL;
            echo "config_cache=".app()->getCachedConfigPath().PHP_EOL;
        '
} 2>/dev/null)"
printf '%s\n' "${runtime_probe}"

effective_environment="$(printf '%s\n' "${runtime_probe}" | sed -n 's/^env=//p' | tail -n1)"
effective_database="$(printf '%s\n' "${runtime_probe}" | sed -n 's/^db=//p' | tail -n1)"
pdo_database="$(printf '%s\n' "${runtime_probe}" | sed -n 's/^pdo_db=//p' | tail -n1)"
effective_cache="$(printf '%s\n' "${runtime_probe}" | sed -n 's/^config_cache=//p' | tail -n1)"

if [[ "${effective_environment}" != "testing" ]]; then
    echo "Refusing PostgreSQL test reset: Laravel runtime APP_ENV is not testing." >&2
    exit 64
fi
if [[ "${effective_database}" != "${test_database}" || ! "${effective_database}" =~ ^[A-Za-z0-9_]+_test$ ]]; then
    echo "Refusing PostgreSQL test reset: shell and Laravel runtime database names differ or are unsafe." >&2
    exit 64
fi
if [[ "${pdo_database}" != "${test_database}" ]]; then
    echo "Refusing PostgreSQL test reset: PDO current_database() does not match the requested test database." >&2
    exit 64
fi
if [[ "${effective_cache}" != "${test_cache}" ]]; then
    echo "Refusing PostgreSQL test reset: Laravel did not use the isolated config cache path." >&2
    exit 64
fi

echo "SAFE TEST DATABASE CONFIRMED: ${effective_database}"

echo "Preparing isolated PostgreSQL database: ${test_database}"
docker compose exec -T \
    -e KAZUTB_TEST_DATABASE="${test_database}" \
    postgres sh -eu -c '
        export PGPASSWORD="${POSTGRES_PASSWORD}"
        dropdb --username="${POSTGRES_USER}" --if-exists --force "${KAZUTB_TEST_DATABASE}"
        createdb --username="${POSTGRES_USER}" --encoding=UTF8 "${KAZUTB_TEST_DATABASE}"
    '

docker compose run --rm --no-deps --entrypoint php "${container_env[@]}" app artisan migrate --force --no-interaction
docker compose run --rm --no-deps --entrypoint php "${container_env[@]}" app artisan db:seed --force --no-interaction

if [[ "${prepare_only}" == "true" ]]; then
    echo "SAFE TEST DATABASE PREPARED: ${test_database}"
    exit 0
fi

echo "Running canonical PostgreSQL PHPUnit suite..."
docker compose run --rm --no-deps --entrypoint php \
    "${container_env[@]}" \
    app vendor/bin/phpunit --configuration phpunit.postgres.xml "$@"
