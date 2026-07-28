#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
ENV_FILE="$ROOT_DIR/.env"
cd "$ROOT_DIR"

if [[ -f "$ENV_FILE" ]]; then
  set -a
  # shellcheck disable=SC1090
  source "$ENV_FILE"
  set +a
fi

DB_HOST_VALUE="${DB_HOST:-${POSTGRES_HOST:-127.0.0.1}}"
DB_PORT_VALUE="${DB_PORT:-${POSTGRES_PORT:-5432}}"
DB_NAME_VALUE="${DB_DATABASE:-${POSTGRES_DB:-}}"
DB_USER_VALUE="${DB_USERNAME:-${POSTGRES_USER:-}}"
DB_PASSWORD_VALUE="${DB_PASSWORD:-${POSTGRES_PASSWORD:-}}"

case "$DB_HOST_VALUE" in
  postgres|db|pgsql|0.0.0.0)
    DB_HOST_VALUE="127.0.0.1"
    ;;
esac

echo "== DB Recovery Audit =="
echo "Timestamp: $(date -u +"%Y-%m-%dT%H:%M:%SZ")"
echo "Host: $DB_HOST_VALUE"
echo "Port: $DB_PORT_VALUE"
echo "Database: ${DB_NAME_VALUE:-<unset>}"
echo "User: ${DB_USER_VALUE:-<unset>}"
echo

if [[ -z "$DB_NAME_VALUE" || -z "$DB_USER_VALUE" ]]; then
  echo "ERROR: DB name/user is not configured in .env"
  exit 1
fi

export PGPASSWORD="$DB_PASSWORD_VALUE"

if ! pg_isready -h "$DB_HOST_VALUE" -p "$DB_PORT_VALUE" -t 3 >/dev/null 2>&1; then
  echo "ERROR: PostgreSQL is not reachable at $DB_HOST_VALUE:$DB_PORT_VALUE"
  echo "Hint: start DB runtime first (docker compose or external postgres)."
  exit 2
fi

echo "-- Database list (visibility-dependent) --"
psql -h "$DB_HOST_VALUE" -p "$DB_PORT_VALUE" -U "$DB_USER_VALUE" -lqt | sed -n '1,60p'
echo

echo "-- Core object existence check --"
psql -h "$DB_HOST_VALUE" -p "$DB_PORT_VALUE" -U "$DB_USER_VALUE" -d "$DB_NAME_VALUE" -v ON_ERROR_STOP=1 <<'SQL'
SELECT table_schema, table_name
FROM information_schema.tables
WHERE (table_schema, table_name) IN (
  ('app','documents'),
  ('app','book_copies'),
  ('app','readers'),
  ('app','reader_contacts'),
  ('app','authors'),
  ('app','publishers'),
  ('app','subjects'),
  ('app','circulation_loans'),
  ('app','digital_materials')
)
ORDER BY table_schema, table_name;
SQL

echo

echo "-- Row counts for known entities (safe dynamic queries) --"
psql -h "$DB_HOST_VALUE" -p "$DB_PORT_VALUE" -U "$DB_USER_VALUE" -d "$DB_NAME_VALUE" -v ON_ERROR_STOP=1 <<'SQL'
DO $$
DECLARE
  rec RECORD;
  sql_text text;
BEGIN
  FOR rec IN
    SELECT * FROM (VALUES
      ('app','documents'),
      ('app','book_copies'),
      ('app','readers'),
      ('app','reader_contacts'),
      ('app','authors'),
      ('app','publishers'),
      ('app','subjects'),
      ('app','circulation_loans'),
      ('app','digital_materials')
    ) AS t(schema_name, table_name)
  LOOP
    IF EXISTS (
      SELECT 1
      FROM information_schema.tables it
      WHERE it.table_schema = rec.schema_name AND it.table_name = rec.table_name
    ) THEN
      sql_text := format('SELECT %L as object_name, count(*)::bigint as row_count FROM %I.%I;', rec.schema_name||'.'||rec.table_name, rec.schema_name, rec.table_name);
      EXECUTE sql_text;
    END IF;
  END LOOP;
END $$;
SQL

echo

echo "-- Database size --"
psql -h "$DB_HOST_VALUE" -p "$DB_PORT_VALUE" -U "$DB_USER_VALUE" -d "$DB_NAME_VALUE" -c "SELECT pg_size_pretty(pg_database_size(current_database())) AS database_size;"

echo

echo "DB recovery audit complete."
