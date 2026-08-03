# experimental evaluation layer (Phase 3) Part 2 — Mutation Gap Analysis

Project: KazUTB Digital Library
Date: 2026-05-13

## 1. Surviving Mutants Overview

Two mutants survived in this campaign:

1. MUT-MUT-004 (ReservationMutateController context mapping)
2. MUT-DOC-004 (DocumentManagementController context mapping)

Both survivors involve operator-id header mapping to context arrays passed into mocked services.

## 2. Gap Analysis Table

| Mutant ID   | Module / Component                  | Survived | Gap Type                                  | Impact | Why Current Tests Missed It                                                                                                                   | Recommended Improvement                                                         | Priority |
| ----------- | ----------------------------------- | -------- | ----------------------------------------- | ------ | --------------------------------------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------------- | -------- |
| MUT-MUT-004 | Integration Reservations Mutate API | Yes      | Weak assertion on service context payload | Medium | `ReservationMutateTest` validates service calls with `Mockery::type('array')`, but does not assert `operatorId` and `operatorBranchId` values | Assert exact context payload values in approve/reject service call expectations | P2       |
| MUT-DOC-004 | Integration Document Management API | Yes      | Weak assertion on service context payload | Medium | `DocumentManagementTest` validates service arguments broadly, without asserting `operator_id` semantic mapping                                | Assert exact context payload values in list/create/patch/archive expectations   | P2       |

## 3. Coverage Gaps Identified

1. Missing fine-grained assertions on controller-to-service context payload fields.
2. Tests emphasize response envelopes and HTTP status codes more than internal semantic argument fidelity.
3. Header-to-context mapping logic is insufficiently constrained by current tests.

## 4. Impact Assessment

1. Medium risk: incorrect operator identity propagation may break auditability and authorization semantics downstream without failing current tests.
2. Not immediately visible at API response level because services are mocked and accept any context array shape.

## 5. Evidence

- `qa/phase3/metrics/phase3-mutation-gaps.csv`
- `qa/phase3/metrics/phase3-mutation-gaps.json`
- `qa/phase3/evidence/logs/phase3-mutation-MUT-MUT-004-20260513-140552.log`
- `qa/phase3/evidence/logs/phase3-mutation-MUT-DOC-004-20260513-140552.log`

---

KazUTB Digital Library — QA experimental evaluation layer (Phase 3) Part 2
