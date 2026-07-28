#!/usr/bin/env bash
# check-runtime-critical-paths.sh — summarize test coverage for 6 critical runtime paths
set -euo pipefail
cd "$(git rev-parse --show-toplevel 2>/dev/null || echo '.')"

OK=0
WARN=0
MISS=0

check_file() {
  local label="$1" path="$2" db_note="$3"
  if [ -f "$path" ]; then
    local methods
    methods=$(grep -c 'public function test_' "$path" 2>/dev/null || echo 0)
    echo "[ok] $label — $methods test(s) [$db_note] ($path)"
    OK=$((OK + 1))
  else
    echo "[MISS] $label — file not found ($path)"
    MISS=$((MISS + 1))
  fi
}

echo "== Critical Path 1: Catalog Search =="
check_file "CatalogDbSearchTest"   "tests/Feature/Api/CatalogDbSearchTest.php"   "PG-only, skips in CI"
check_file "CatalogPageTest"       "tests/Feature/CatalogPageTest.php"           "SQLite, runs in CI"
check_file "SpaCatalogWiringTest"  "tests/Feature/SpaCatalogWiringTest.php"      "SQLite, runs in CI"
echo ""

echo "== Critical Path 2: Book Detail =="
check_file "BookDetailDbTest"      "tests/Feature/Api/BookDetailDbTest.php"      "PG-only, skips in CI"
check_file "BookPageTest"          "tests/Feature/BookPageTest.php"              "SQLite, runs in CI"
echo ""

echo "== Critical Path 3: Account Identity / Auth =="
check_file "AuthSessionMeTest"         "tests/Feature/Api/AuthSessionMeTest.php"         "SQLite, runs in CI"
check_file "AuthSessionLifecycleTest"  "tests/Feature/Api/AuthSessionLifecycleTest.php"  "SQLite, runs in CI"
check_file "LoginTest"                 "tests/Feature/Auth/LoginTest.php"                 "SQLite, runs in CI"
echo ""

echo "== Critical Path 4: Reservation List/Detail =="
check_file "AccountReservationsTest"  "tests/Feature/Api/AccountReservationsTest.php"              "PG-only, skips in CI"
check_file "ReservationReadTest"      "tests/Feature/Api/Integration/ReservationReadTest.php"      "SQLite+mocks, runs in CI"
echo ""

echo "== Critical Path 5: Reservation Approve/Reject =="
check_file "ReservationMutateTest"         "tests/Feature/Api/Integration/ReservationMutateTest.php"                   "SQLite+mocks, runs in CI"
check_file "RetirementConsistencyTest"     "tests/Feature/Api/Integration/ReservationMutateRetirementConsistencyTest.php"  "SQLite+mocks, runs in CI"
echo ""

echo "== Critical Path 6: Circulation Checkout/Return =="
check_file "CheckoutReturnTest"   "tests/Feature/Api/InternalCirculationCheckoutReturnTest.php"  "PG-only, skips in CI"
check_file "RenewalTest"          "tests/Feature/Api/InternalCirculationRenewalTest.php"         "PG-only, skips in CI"
check_file "ReadbackTest"         "tests/Feature/Api/InternalCirculationReadbackTest.php"        "PG-only, skips in CI"
echo ""

echo "== CI environment note =="
echo "CI uses SQLite in-memory. Tests marked 'PG-only' skip automatically."
echo "Run with live PostgreSQL for full critical-path coverage."
echo ""

echo "== Summary =="
echo "Test files present: $OK"
if [ "$MISS" -gt 0 ]; then
  echo "Test files MISSING: $MISS"
fi
echo ""
if [ "$MISS" -eq 0 ]; then
  echo "Result: ALL critical-path test files present."
else
  echo "Result: $MISS critical-path test file(s) missing."
fi
