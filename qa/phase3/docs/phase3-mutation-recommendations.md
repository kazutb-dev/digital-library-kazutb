# experimental evaluation layer (Phase 3) Part 2 — Mutation Recommendations

Project: KazUTB Digital Library
Date: 2026-05-13

## Priority Recommendations

### R-MUT-001 (P1): Strengthen service-call context assertions in ReservationMutateTest

Problem addressed: MUT-MUT-004 survived.

Action:

1. Replace broad `Mockery::type('array')` for context with explicit shape checks.
2. Assert `operatorId`, `operatorBranchId`, `requestId`, and `correlationId` values exactly.
3. Add negative test where malformed/missing operator header leads to expected context failure path.

Expected benefit:

- Kills context-mapping mutants and improves audit-trace correctness confidence.

### R-MUT-002 (P1): Strengthen context assertions in DocumentManagementTest

Problem addressed: MUT-DOC-004 survived.

Action:

1. Assert exact context keys and values passed to service methods (`operator_id`, `request_id`, `correlation_id`).
2. Add dedicated tests for header mapping behavior (valid vs malformed/missing operator header).

Expected benefit:

- Prevents silent regressions in identity propagation to document service operations.

### R-MUT-003 (P2): Add explicit mutation regression tests for context semantics

Action:

1. Introduce dedicated tests that fail when operator header name mapping changes.
2. Keep these tests independent from response-envelope assertions.

Expected benefit:

- Converts currently surviving mutant class into consistently killed class.

### R-MUT-004 (P2): Integrate bounded mutation run into CI (nightly or pre-release)

Action:

1. Add optional CI workflow step invoking `qa/phase3/mutation/plans/run-phase3-mutation.ps1`.
2. Persist mutation result artifacts for trend analysis.
3. Set warning threshold first (for example 80%), then enforce gate after stabilization.

Expected benefit:

- Prevents mutation score regression and keeps test quality measurable over time.

### R-MUT-005 (P3): Expand bounded mutant set in experimental evaluation layer (Phase 3) Part 3

Action:

1. Add 6-10 new mutants focused on IntegrationDocumentManagementService and reservation write service branch logic.
2. Maintain bounded strategy with module-targeted tests only.

Expected benefit:

- Broader confidence without uncontrolled mutation runtime growth.

## Summary

The current suite is strong on boundary and validation behavior (12/14 mutants killed), but context payload semantics require stronger assertions. Addressing R-MUT-001 and R-MUT-002 should materially improve detection quality in the weakest area.

---

KazUTB Digital Library — QA experimental evaluation layer (Phase 3) Part 2
