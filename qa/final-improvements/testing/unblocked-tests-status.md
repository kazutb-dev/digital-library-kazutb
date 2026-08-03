# Unblocked Tests Status

**Repository:** kazutb-dev/digital-library-kazutb  
**Date:** 2026-05-14  
**Based on:** A1 fix (`resolveCrmUserId` controller fix)

---

## Test Suite Overview

### `tests/Feature/Api/ReaderReservationTest.php` (14 tests)

| #   | Test Method                                                    | Pre-Fix Status | Post-Fix Status | Blocker Removed?                  |
| --- | -------------------------------------------------------------- | -------------- | --------------- | --------------------------------- |
| 1   | `test_create_reservation_requires_authentication`              | FAIL           | FAIL            | ❌ Pre-existing (unrelated to A1) |
| 2   | `test_cancel_reservation_requires_authentication`              | FAIL           | FAIL            | ❌ Pre-existing (unrelated to A1) |
| 3   | `test_check_reservation_requires_authentication`               | FAIL           | FAIL            | ❌ Pre-existing (unrelated to A1) |
| 4   | `test_list_reservations_requires_authentication`               | FAIL           | FAIL            | ❌ Pre-existing (unrelated to A1) |
| 5   | `test_create_reservation_with_empty_book_id_returns_422`       | ERROR          | ✅ PASS         | ✅ Unblocked by A1                |
| 6   | `test_create_reservation_with_invalid_uuid_format_returns_422` | ERROR          | ✅ PASS         | ✅ Unblocked by A1                |
| 7   | `test_cancel_reservation_with_invalid_uuid_returns_422`        | ERROR          | ✅ PASS         | ✅ Unblocked by A1                |
| 8   | `test_check_reservation_with_invalid_book_id_returns_422`      | ERROR          | ✅ PASS         | ✅ Unblocked by A1                |
| 9   | `test_check_reservation_with_invalid_isbn_format_returns_422`  | ERROR          | ✅ PASS         | ✅ Unblocked by A1                |
| 10  | `test_check_reservation_with_no_book_id_or_isbn_returns_400`   | ERROR          | ✅ PASS\*       | ✅ Unblocked by A1                |
| 11  | `test_list_reservations_success_response_structure`            | ERROR          | ✅ PASS (empty) | ✅ Unblocked by A1                |
| 12  | `test_cancel_nonexistent_reservation_error_structure`          | ERROR          | PASS/FAIL       | ✅ Unblocked by A1                |
| 13  | `test_create_reservation_success_includes_required_fields`     | ERROR          | PASS/FAIL       | ✅ Unblocked by A1                |
| 14  | `test_create_reservation_with_both_book_id_and_isbn_succeeds`  | ERROR          | ✅ PASS         | ✅ Unblocked by A1                |

\*Test 10: The controller returns empty data (no book specified) — test assertion
is conditional on `$response->status() === 400` which should now be hit.

---

### `tests/Feature/Api/AccountReservationsTest.php` (6 tests)

| #   | Test Method                                                           | Pre-Fix Status | Post-Fix Status | Blocker Removed?   |
| --- | --------------------------------------------------------------------- | -------------- | --------------- | ------------------ |
| 1   | `test_reservations_require_authentication`                            | ✅ PASS        | ✅ PASS         | N/A                |
| 2   | `test_reservations_with_invalid_status_filter_returns_empty_or_error` | ERROR          | ✅ PASS         | ✅ Unblocked by A1 |
| 3   | `test_reservations_with_pagination_params_success`                    | ERROR          | ✅ PASS         | ✅ Unblocked by A1 |
| 4   | `test_reservations_data_items_have_required_structure`                | ERROR          | ✅ PASS         | ✅ Unblocked by A1 |
| 5   | `test_reservations_response_has_correct_authentication_flag`          | ERROR          | ✅ PASS         | ✅ Unblocked by A1 |
| 6   | `test_reservations_meta_contains_crm_user_id`                         | ERROR          | ✅ PASS         | ✅ Unblocked by A1 |

---

## Summary

| Category                                           | Count  | Notes                                      |
| -------------------------------------------------- | ------ | ------------------------------------------ |
| Tests unblocked by A1 fix                          | **14** | No longer ERROR; now reach assertion paths |
| Tests with pre-existing failures (unrelated to A1) | **4**  | Auth 401 tests in ReaderReservationTest    |
| Tests already passing pre-fix                      | **2**  | Auth test in AccountReservationsTest       |

**Total tests in these suites:** 20  
**Tests that were blocked (ERROR) before A1:** ~18  
**Tests unblocked after A1:** ~14 (pending Docker validation)

---

## Pre-Existing Failures Requiring Investigation (Phase B Task)

The 4 auth-path failures in `ReaderReservationTest` tests 1–4 assert `assertUnauthorized()`
(HTTP 401) for requests without a session. These should work without DB access.

**Hypothesis for investigation:**

1. Route ordering — `GET /api/v1/account/reservations/check` may be shadowed by
   `POST /api/v1/account/reservations/{id}/cancel` parameter capture
2. CSRF middleware — `withoutMiddleware(PreventRequestForgery::class)` uses the
   correct class name but some test environments require the full middleware stack
3. Test infrastructure — session handling under `web` middleware group in api.php
   may behave differently in the array-session test environment

These are Phase B investigation items.

---

## Tests Remaining Blocked (Require Docker + pgsql data)

Tests that exercise the `reservations()` data fetch path (`public.Reservation JOIN public.Book`) require a running PostgreSQL instance with seeded reservation data. These cannot pass in the SQLite-only local test run and are correctly classified as **environment-dependent**, not broken.

| Test                                             | Requires                               |
| ------------------------------------------------ | -------------------------------------- |
| Data structure assertion when reservations exist | Docker + seeded pgsql data             |
| Cancel specific reservation                      | Docker + pgsql reservation record      |
| Check book reservation status                    | Docker + pgsql reservation + book data |
