#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT_DIR"

echo "== Schema Drift Audit =="
echo "Timestamp: $(date -u +"%Y-%m-%dT%H:%M:%SZ")"
echo

TMP_DIR="$(mktemp -d)"
trap 'rm -rf "$TMP_DIR"' EXIT

# Extract app.<table_or_view> references from app/routes/controllers/services.
grep -RInE "app\.[a-zA-Z0-9_]+" app routes config tests 2>/dev/null \
  | sed -E "s/.*(app\.[a-zA-Z0-9_]+).*/\1/" \
  | sort -u > "$TMP_DIR/code_refs.txt"

# Extract tables managed by migrations.
grep -RIn "Schema::create\|Schema::table" database/migrations 2>/dev/null \
  | sed -E "s/.*Schema::(create|table)\('([^']+)'.*/\2/" \
  | sort -u > "$TMP_DIR/migration_tables.txt"

# Normalize migration names to app.* namespace when absent.
awk '{if ($0 ~ /^app\./) print $0; else print "app."$0}' "$TMP_DIR/migration_tables.txt" \
  | sort -u > "$TMP_DIR/migration_tables_app_ns.txt"

echo "-- Referenced app.* objects in code --"
wc -l "$TMP_DIR/code_refs.txt"
sed -n '1,200p' "$TMP_DIR/code_refs.txt"
echo

echo "-- app.* objects covered by migrations --"
wc -l "$TMP_DIR/migration_tables_app_ns.txt"
sed -n '1,200p' "$TMP_DIR/migration_tables_app_ns.txt"
echo

echo "-- Potential legacy dependencies (in code but not in migrations) --"
comm -23 "$TMP_DIR/code_refs.txt" "$TMP_DIR/migration_tables_app_ns.txt" | sed -n '1,300p'

echo

echo "Schema drift audit complete."
