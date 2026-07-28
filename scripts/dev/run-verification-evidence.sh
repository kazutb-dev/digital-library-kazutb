#!/usr/bin/env bash
set -euo pipefail

ROOT_DIR="$(cd "$(dirname "$0")/../.." && pwd)"
cd "$ROOT_DIR"

STAMP="$(date +%Y%m%d-%H%M%S)"
OUT_DIR="$ROOT_DIR/evidence/verification"
mkdir -p "$OUT_DIR"

QA_LOG="$OUT_DIR/qa-gates-$STAMP.txt"
E2E_LOG="$OUT_DIR/playwright-smoke-$STAMP.txt"
TRACE_LOG="$OUT_DIR/ci-traceability-$STAMP.txt"
REMOTE_LOG="$OUT_DIR/remote-ci-summary-$STAMP.txt"

{
  echo "Verification evidence capture"
  echo "Timestamp: $(date -Iseconds)"
  echo "Repository: $(basename "$ROOT_DIR")"
  echo
  echo "> composer qa:ci"
} | tee "$QA_LOG"
composer qa:ci | tee -a "$QA_LOG"

{
  echo
  echo "----------------------------------------"
  echo "> npm run test:e2e"
} | tee "$E2E_LOG"
npm run test:e2e | tee -a "$E2E_LOG"

{
  echo "Verification trace"
  echo "Timestamp: $(date -Iseconds)"
  echo
  git log --oneline -n 20 -- .github/workflows/ci.yml .github/workflows/release-package.yml scripts/dev/run-ci-gates.sh scripts/dev/check-coverage-threshold.php tests/e2e/public-smoke.spec.ts tests/Feature/InternalAccessBoundaryTest.php docs/qa
} > "$TRACE_LOG"

if command -v gh >/dev/null 2>&1; then
  {
    echo "Recent GitHub Actions runs"
    echo "Timestamp: $(date -Iseconds)"
    echo
    GH_PAGER=cat gh run list --workflow "Continuous Verification" --limit 10 2>/dev/null || GH_PAGER=cat gh run list --workflow CI --limit 10 || true
  } > "$REMOTE_LOG"
fi

if command -v python3 >/dev/null 2>&1 && [[ -f "$OUT_DIR/charts/generate_quality_charts.py" ]]; then
  python3 "$OUT_DIR/charts/generate_quality_charts.py" >/dev/null
fi

echo "Verification artifacts written to:"
echo "- $QA_LOG"
echo "- $E2E_LOG"
echo "- $TRACE_LOG"
if [[ -f "$REMOTE_LOG" ]]; then
  echo "- $REMOTE_LOG"
fi
