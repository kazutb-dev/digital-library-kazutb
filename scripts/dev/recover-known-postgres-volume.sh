#!/usr/bin/env bash
set -euo pipefail

KNOWN_VOLUME="${1:-kazutb-smart-library-main_postgres_data}"

echo "== Known Docker Volume Recovery Probe =="
echo "Volume candidate: $KNOWN_VOLUME"
echo

if ! command -v docker >/dev/null 2>&1; then
  echo "ERROR: docker is not installed on this host."
  echo "Install Docker first, then re-run this script."
  exit 1
fi

echo "-- Docker info --"
docker --version

echo
if ! docker info >/dev/null 2>&1; then
  echo "ERROR: Docker daemon is not reachable for current user."
  echo "Hint: run with sudo or re-login after adding user to docker group."
  exit 2
fi

echo "-- Volume inspect --"
if docker volume inspect "$KNOWN_VOLUME" >/tmp/volume-inspect.json 2>/dev/null; then
  cat /tmp/volume-inspect.json
  echo
  MOUNTPOINT="$(python3 - <<'PY'
import json
with open('/tmp/volume-inspect.json','r',encoding='utf-8') as f:
    data=json.load(f)
print(data[0].get('Mountpoint',''))
PY
)"
  echo "Mountpoint: ${MOUNTPOINT:-<unknown>}"

  if [[ -n "${MOUNTPOINT:-}" ]]; then
    echo "-- Top files in mountpoint --"
    find "$MOUNTPOINT" -maxdepth 3 -type f -printf '%s\t%p\n' 2>/dev/null | sort -nr | head -60 || true
  fi

  echo
  echo "Recovery hint:"
  echo "1) Start temporary postgres container with this volume attached read-only if needed."
  echo "2) Run pg_dump from temporary container to produce restorable dump."
else
  echo "Volume not found on this host: $KNOWN_VOLUME"
  echo "Likely causes:"
  echo "- data exists on another server/VM"
  echo "- Docker data root was wiped"
  echo "- volume was renamed"
  exit 3
fi
