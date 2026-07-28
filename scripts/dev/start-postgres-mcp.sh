#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
ENV_FILE="$ROOT_DIR/.env"

if [[ -f "$ENV_FILE" ]]; then
  set -a
  # shellcheck disable=SC1090
  source "$ENV_FILE"
  set +a
fi

build_database_url() {
  python3 - "$@" <<'PY'
from __future__ import annotations
import sys
from urllib.parse import quote

user, password, host, port, database = sys.argv[1:6]
print(f"postgresql://{quote(user, safe='')}:{quote(password, safe='')}@{host}:{port}/{quote(database, safe='')}")
PY
}

redact_database_url() {
  python3 - "$1" <<'PY'
from __future__ import annotations
import sys
from urllib.parse import urlsplit, unquote

url = urlsplit(sys.argv[1])
user = unquote(url.username or '')
host = url.hostname or ''
port = url.port or ''
database = (url.path or '/').lstrip('/')
print(f"postgresql://{user}:***@{host}:{port}/{database}")
PY
}

DATABASE_URL_VALUE="${DATABASE_URL:-}"

if [[ -z "$DATABASE_URL_VALUE" ]]; then
  DB_USER="${DB_USERNAME:-${POSTGRES_USER:-}}"
  DB_PASSWORD_VALUE="${DB_PASSWORD:-${POSTGRES_PASSWORD:-}}"
  DB_NAME="${DB_DATABASE:-${POSTGRES_DB:-}}"
  DB_PORT_VALUE="${DB_PORT:-${POSTGRES_PORT:-5432}}"
  DB_HOST_VALUE="${DB_HOST:-${POSTGRES_HOST:-127.0.0.1}}"

  case "$DB_HOST_VALUE" in
    postgres|db|pgsql|0.0.0.0)
      DB_HOST_VALUE="127.0.0.1"
      ;;
  esac

  if [[ -z "$DB_USER" || -z "$DB_PASSWORD_VALUE" || -z "$DB_NAME" ]]; then
    echo "Postgres MCP bootstrap failed: DATABASE_URL is empty and .env does not contain enough DB_* or POSTGRES_* values." >&2
    exit 1
  fi

  DATABASE_URL_VALUE="$(build_database_url "$DB_USER" "$DB_PASSWORD_VALUE" "$DB_HOST_VALUE" "$DB_PORT_VALUE" "$DB_NAME")"
fi

if [[ "${1:-}" == "--print-url" ]]; then
  redact_database_url "$DATABASE_URL_VALUE"
  exit 0
fi

exec npx -y @modelcontextprotocol/server-postgres "$DATABASE_URL_VALUE"
