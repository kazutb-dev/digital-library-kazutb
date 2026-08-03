# Assertion Prioritization Report

**Repository:** kazutb-dev/digital-library-kazutb  
**Date:** 2026-05-14  
**Phase:** Pre-B/C — identifies Phase C targets

---

## Methodology

Analyzed `tests/Feature/Api/` (57 test files) by:

1. Identifying tests with single `assertStatus(N)` and no further assertions
2. Identifying tests that use conditional assertions (`if ($response->status() === 200)`)
3. Cross-referencing with high-risk components (auth, reservation, integration, admin)

---

## Priority 1: Status-Code-Only Tests in High-Risk Components

These tests assert only on HTTP status, providing no mutation resistance beyond
gross routing/middleware behavior.

| File                    | Test Method                                             | Only Assertion                           | Risk Level                      |
| ----------------------- | ------------------------------------------------------- | ---------------------------------------- | ------------------------------- |
| `ReaderReservationTest` | `test_create_reservation_requires_authentication`       | `assertUnauthorized()`                   | HIGH — auth boundary            |
| `ReaderReservationTest` | `test_cancel_reservation_requires_authentication`       | `assertUnauthorized()`                   | HIGH                            |
| `ReaderReservationTest` | `test_check_reservation_requires_authentication`        | `assertUnauthorized()`                   | HIGH                            |
| `ReaderReservationTest` | `test_list_reservations_requires_authentication`        | `assertUnauthorized()`                   | HIGH                            |
| `LoanVisibilityTest`    | `test_loan_summary_requires_auth`                       | `assertStatus(401)`                      | HIGH                            |
| `LoanVisibilityTest`    | `test_loans_requires_auth`                              | `assertStatus(401)`                      | HIGH                            |
| `AuthHardeningTest`     | `test_login_failure_does_not_leak_crm_response_details` | `assertStatus(401)` + non-key assertions | MEDIUM — partially strengthened |

**Recommended Phase C addition for all status-code-only auth tests:**

```php
// Add after assertUnauthorized():
$response->assertJsonStructure(['authenticated', 'message'])
         ->assertJsonPath('authenticated', false);
```

---

## Priority 2: Conditional Assertion Tests

Tests where assertions only run if a condition is met — potentially a no-op if
the condition is false, providing false test coverage signal.

| File                      | Test Method                                                  | Issue                                                    |
| ------------------------- | ------------------------------------------------------------ | -------------------------------------------------------- |
| `AccountReservationsTest` | `test_reservations_with_pagination_params_success`           | `if ($response->status() === 200)` — conditional         |
| `AccountReservationsTest` | `test_reservations_data_items_have_required_structure`       | `if ($response->status() === 200 && data)` — conditional |
| `AccountReservationsTest` | `test_reservations_response_has_correct_authentication_flag` | `if ($response->status() === 200)` — conditional         |
| `ReaderReservationTest`   | `test_list_reservations_success_response_structure`          | `if ($response->status() === 200)` — conditional         |
| `ReaderReservationTest`   | `test_cancel_nonexistent_reservation_error_structure`        | `if ($response->status() >= 400)` — conditional          |
| `ReaderReservationTest`   | `test_create_reservation_success_includes_required_fields`   | `if ($response->status() === 201)` — conditional         |

**Fix:** Replace conditional assertions with unconditional ones. After the A1 fix,
these tests now reach a deterministic state (no-data 200 for the list, 422/403 for
invalid operations) so conditions can be replaced with specific status + structure asserts.

**Example refactor:**

```php
// BEFORE (conditional — may be a no-op):
if ($response->status() === 200) {
    $this->assertIsArray($response->json('data'));
}

// AFTER (unconditional — always asserts):
$response->assertOk()
         ->assertJsonStructure(['authenticated', 'data', 'meta'])
         ->assertJsonPath('authenticated', true);
$this->assertIsArray($response->json('data'));
```

---

## Priority 3: Integration Boundary Tests

Integration tests that check status but not payload contracts:

| File                                         | Assessment                                                             |
| -------------------------------------------- | ---------------------------------------------------------------------- |
| `MidtermIntegrationRiskExpansionTest`        | Has `assertStatus(200)` inside a loop; needs field-by-field validation |
| `IdentityMappingE2ETest`                     | `assertStatus(200)` followed by field access — partially done          |
| `Integration/ReservationMutateTest`          | ✅ Already strengthened (67 assertions, idempotency, context)          |
| `Integration/AdminPrivilegeNegativePathTest` | ✅ Already strengthened (35 assertions, 7 routes × 4 roles)            |

---

## Priority 4: Missing Database-State Verification

After mutation operations (create/cancel reservation, loan renewal), no tests
verify that the expected database record was created/updated. This is a significant
mutation resistance gap for the persistence layer.

| Operation          | Test File               | Missing State Check                                 |
| ------------------ | ----------------------- | --------------------------------------------------- |
| Create reservation | `ReaderReservationTest` | No `assertDatabaseHas('public.Reservation', [...])` |
| Cancel reservation | `ReaderReservationTest` | No status check in DB                               |
| Renew loan         | `AccountRenewalTest`    | DB state not verified post-renew                    |

**Note:** Database-state assertions against `public.*` tables require the Docker
pgsql environment. This is a Phase B/C task gated on Docker availability.

---

## Phase C Work Queue (Ordered by Impact)

1. **Remove conditional assertions** from 6 tests in AccountReservationsTest /
   ReaderReservationTest — replace with deterministic assertOk + assertJsonStructure
   _(unblocked by A1 fix, can run locally)_

2. **Strengthen auth 401 tests** with JSON body assertions (authenticated=false,
   message field present) — 6 tests, low effort, high mutation resistance gain

3. **Add error payload assertions** to 422/400/403 negative-path tests —
   verify `error`, `message`, `success=false` fields are present

4. **Add database-state verification** to create/cancel reservation tests —
   requires Docker, scoped to Phase B when Docker is available

5. **Strengthen MidtermIntegrationRiskExpansionTest** — replace bare assertStatus(200)
   with field-by-field payload checks

---

## Assertion Strength Summary (Current State)

| Component              | Tests | Avg Assertions/Test | Status-Code-Only  | Assessment             |
| ---------------------- | ----- | ------------------: | ----------------- | ---------------------- |
| Auth/session           | ~15   |                ~3.2 | 6                 | ⚠️ Several status-only |
| Reservation/account    | ~20   |                ~1.8 | 4 + 6 conditional | ❌ Needs strengthening |
| Integration (enhanced) | 43    |                ~2.4 | 0                 | ✅ Strong              |
| Loans/renewal          | ~20   |                ~2.1 | 2                 | ⚠️ Acceptable          |
| Admin/internal routes  | ~30   |                ~2.8 | 2                 | ✅ Good                |

**Phase C target:** Bring reservation/account suite to ≥3.0 assertions/test average.
