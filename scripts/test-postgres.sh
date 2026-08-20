#!/usr/bin/env bash
set -Eeuo pipefail

# Stable public entry point. Keep the implementation under scripts/dev so
# existing automation keeps working while every caller shares one guard.
exec "$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)/dev/test-postgres.sh" "$@"
