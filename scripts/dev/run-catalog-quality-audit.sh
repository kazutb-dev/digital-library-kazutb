#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
ENV_FILE="$ROOT_DIR/.env"
SQL_FILE="$ROOT_DIR/scripts/dev/catalog-quality-audit.sql"

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

if [[ -z "$DB_NAME_VALUE" || -z "$DB_USER_VALUE" ]]; then
  echo "ERROR: DB name/user is not configured in .env"
  exit 1
fi

export PGPASSWORD="$DB_PASSWORD_VALUE"

if ! pg_isready -h "$DB_HOST_VALUE" -p "$DB_PORT_VALUE" -t 3 >/dev/null 2>&1; then
  echo "ERROR: PostgreSQL is not reachable at $DB_HOST_VALUE:$DB_PORT_VALUE"
  exit 2
fi

echo "Running catalog quality audit on ${DB_NAME_VALUE}@${DB_HOST_VALUE}:${DB_PORT_VALUE}"
psql -h "$DB_HOST_VALUE" -p "$DB_PORT_VALUE" -U "$DB_USER_VALUE" -d "$DB_NAME_VALUE" -v ON_ERROR_STOP=1 -f "$SQL_FILE"
