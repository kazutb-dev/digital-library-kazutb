#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT_DIR"

echo "== Infra Audit =="
echo "Timestamp: $(date -u +"%Y-%m-%dT%H:%M:%SZ")"
echo

echo "-- Host --"
hostnamectl 2>/dev/null | sed -n '1,12p' || true
echo "User: $(whoami)"
id || true
echo

echo "-- Runtime binaries --"
for bin in php composer node npm docker psql pg_isready; do
  if command -v "$bin" >/dev/null 2>&1; then
    echo "FOUND: $bin -> $(command -v "$bin")"
  else
    echo "MISSING: $bin"
  fi
done
echo

echo "-- Versions --"
php -v | head -n1 || true
composer -V || true
node -v || true
npm -v || true
if command -v docker >/dev/null 2>&1; then
  docker --version || true
else
  echo "docker: not installed"
fi
psql --version || true
echo

echo "-- Services --"
if command -v systemctl >/dev/null 2>&1; then
  systemctl list-unit-files 2>/dev/null | grep -Ei 'docker|postgres|php8\.[34]-fpm|nginx' | head -80 || true
fi
echo

echo "-- Ports --"
ss -ltnp | grep -E ':80 |:443 |:5432|:5433|:5173|:8080' || true
echo

echo "-- Git state --"
git status --short --branch || true
git stash list || true
echo

echo "-- Environment DB hints (.env*) --"
for f in .env .env.example .env.dev.example .env.prod.example; do
  if [[ -f "$f" ]]; then
    echo "### $f"
    grep -E 'APP_ENV|APP_URL|DB_CONNECTION|DB_HOST|DB_PORT|DB_DATABASE|DB_USERNAME|POSTGRES_DB|POSTGRES_USER|POSTGRES_PORT|DATABASE_URL' "$f" || true
  fi
done
echo

echo "-- Postgres reachability --"
for host in 127.0.0.1 localhost 10.0.1.8; do
  for port in 5432 5433 5434; do
    pg_isready -h "$host" -p "$port" -t 2 >/dev/null 2>&1 && echo "UP: $host:$port" || echo "DOWN: $host:$port"
  done
done
echo

echo "-- Backup-like files (top 60 by size) --"
find /home/admtutor /var/backups /tmp -type f \( -iname '*.sql' -o -iname '*.dump' -o -iname '*.backup' -o -iname '*.bak' -o -iname '*.tar' -o -iname '*.tar.gz' -o -iname '*.tgz' -o -iname '*.zip' -o -iname '*.gz' \) -printf '%s\t%TY-%Tm-%Td %TH:%TM\t%p\n' 2>/dev/null | sort -nr | head -60 || true

echo

echo "Infra audit complete."
