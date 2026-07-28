#!/usr/bin/env bash
set -euo pipefail

MSG_FILE="${1:-}"
COMMIT_SOURCE="${2:-}"

if [[ -z "$MSG_FILE" || ! -f "$MSG_FILE" ]]; then
  exit 0
fi

case "$COMMIT_SOURCE" in
  merge|squash|commit|message)
    exit 0
    ;;
esac

REMINDER_FILE_CONTENT='# 📚 Vault reminder: important decisions → run: ./kazutb-library-vault/scripts/log_decision.ps1
# Session end? → run: ./kazutb-library-vault/scripts/end_session.ps1 "summary"

'

current_content="$(cat "$MSG_FILE")"
if [[ "$current_content" == *"# 📚 Vault reminder: important decisions"* ]]; then
  exit 0
fi

printf '%s%s' "$REMINDER_FILE_CONTENT" "$current_content" > "$MSG_FILE"
