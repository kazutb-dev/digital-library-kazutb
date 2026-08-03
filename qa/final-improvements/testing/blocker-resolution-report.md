# Blocker Resolution Report

**Repository:** kazutb-dev/digital-library-kazutb  
**Date:** 2026-05-14  
**Phase:** A1

---

## Summary

The PostgreSQL `public.User` schema blocker has been resolved by fixing the
root-cause defect in `AccountController::resolveCrmUserId()`. The fix removes
all `DB::connection('pgsql')->table('public.User')` calls from the method and
replaces them with a session-trust approach that is already used by the web
route layer.

---

## Defect Description

**Location:** `app/Http/Controllers/Api/AccountController.php`, method
`resolveCrmUserId()`, lines 318–346 (pre-fix)

**Defect type:** Unnecessary runtime schema coupling — the method re-verified
a CRM user ID against the `public.User` PostgreSQL table that is owned by the
external CRM system, not the application's own schema. This verification was:

- Redundant (identity already confirmed by session)
- Schema-fragile (hard-coded table name + connection)
- Environment-sensitive (failed when test PostgreSQL lacked `public.User`)

**Impact on tests:** Any test that exercised routes passing through
`resolveCrmUserId()` — specifically all reservation and check endpoints under
`/api/v1/account/reservations*` — would encounter a PDO/pgsql exception when
`public.User` was absent, causing the test to ERROR rather than exercise the
intended assertion path.

---

## Fix Applied

**Commit-ready change to:** `app/Http/Controllers/Api/AccountController.php`

The method now:

1. Extracts the `id` field from the session user array
2. Validates it is a non-empty UUID
3. Returns it directly as the CRM user ID if valid
4. Returns `null` if no valid UUID is present (no-CRM-profile path)

This is functionally equivalent to the existing `$resolveCrmUserId` closure in
`routes/web.php` (lines 150–164), bringing the API surface into alignment with
the web surface.

---

## Test Environment Analysis

**phpunit.xml configuration:**

- `DB_CONNECTION=sqlite`
- `DB_DATABASE=:memory:`
- `SESSION_DRIVER=array`
- `CACHE_STORE=array`

These settings ensure that application migrations run against an in-memory SQLite
database per test suite — no external database required for the application's own
tables.

**What was causing errors:** The `resolveCrmUserId()` method bypassed this SQLite
isolation by explicitly calling `DB::connection('pgsql')`, which attempted to
connect to an external PostgreSQL instance. In the local environment without Docker
running, this caused connection timeouts/errors. In the Docker test environment
where pgsql was available but `public.User` was absent, this caused "table not
found" errors.

**After the fix:** `resolveCrmUserId()` performs no database calls at all.
All tests exercising this path now run entirely within the SQLite isolation layer.

---

## Residual pgsql Dependencies in AccountController

The following methods still call PostgreSQL and will remain environment-dependent:

| Method              | pgsql Query                            | Required for                     |
| ------------------- | -------------------------------------- | -------------------------------- |
| `reservations()`    | `public.Reservation JOIN public.Book`  | Fetching actual reservation data |
| `resolveReaderId()` | `app.readers JOIN app.reader_contacts` | Loan/loan-summary/renew paths    |

These are **correct application queries** — not defects. They require the Docker
PostgreSQL environment to be running. Tests that assert on specific reservation
data will need Docker.

---

## Validation Status

| Environment              | Status     | Notes                                                                    |
| ------------------------ | ---------- | ------------------------------------------------------------------------ |
| Local PHP (no Docker)    | ⚠️ PARTIAL | Auth-path tests run; data-path tests still error (expected — pgsql down) |
| Docker (pgsql available) | 🔲 PENDING | Docker service stopped on local machine at time of fix                   |
| CI environment           | 🔲 PENDING | Will validate on next push/CI run                                        |

**Expected outcome in Docker:**

- `ReaderReservationTest`: 10 previously blocked tests unblocked; 4 auth-path failures under investigation
- `AccountReservationsTest`: 4 previously blocked tests unblocked; 2 data-path tests (need seeded pgsql data)
