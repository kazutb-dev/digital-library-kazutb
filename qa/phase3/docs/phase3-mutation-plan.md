# experimental evaluation layer (Phase 3) Part 2 — Mutation Testing Plan

Project: KazUTB Digital Library
Phase: 3 Part 2 (experimental evaluation layer continuation)
Date: 2026-05-13
Campaign mode: Controlled manual mutation (scripted)

## 1. Goal

Assess how effectively the current automated integration-focused test suite detects realistic logic faults in high-risk API boundary and integration controllers.

## 2. Selection Method

Module selection used:

1. Intermediate Empirical Review risk reassessment (integration boundary and governance controls are high risk).
2. Defect concentration history from automation and CI governance layer (Phase 2) and Intermediate Empirical Review integration regressions.
3. Mutation suitability (small deterministic guards/validators with existing focused tests).
4. Execution feasibility in bounded time with reproducible test commands.

## 3. Bounded Scope Rationale

This campaign is intentionally bounded to 4 modules and 14 mutants to keep the run reproducible and analytically meaningful.

Bound constraints:

1. Focus on high-value guard/validation/context logic.
2. Avoid syntax-break mutants that only produce parse errors.
3. Execute only module-relevant tests per mutant.
4. Keep total mutant count in the recommended 8-20 range.

## 4. Selected Modules

| Module / Component                                                   | Why Selected                                                                                   | Risk Source                                                           | Mutation Suitability                                               | Existing Test Context                                         | Planned Mutants | Planned Types                                               |
| -------------------------------------------------------------------- | ---------------------------------------------------------------------------------------------- | --------------------------------------------------------------------- | ------------------------------------------------------------------ | ------------------------------------------------------------- | --------------: | ----------------------------------------------------------- |
| Integration Boundary Middleware (`EnsureIntegrationBoundary`)        | Governs authentication, required headers, and source-system control for all integration routes | Intermediate Empirical Review integration boundary risk; experimental evaluation layer (Phase 3) Part 1 S08 failure context | Clear branch conditions and fallback returns                       | `IntegrationBoundarySkeletonTest`, `IntegrationRateLimitTest` |               3 | Guard inversion, logical operator, return change            |
| Integration Reservations Read API (`ReservationReadController`)      | Exposes read list/detail with filter/pagination validation                                     | automation and CI governance layer (Phase 2) integration correctness and envelope consistency              | Deterministic validation + not-found branch                        | `ReservationReadTest`                                         |               3 | Logical operator, guard inversion                           |
| Integration Reservations Mutate API (`ReservationMutateController`)  | Approve/reject command safety (role gate, idempotency, payload requirements)                   | Mutation endpoint governance risk; role abuse risk                    | Strong guard-heavy logic and context mapping                       | `ReservationMutateTest`                                       |               4 | Guard inversion, logical operator, key lookup alteration    |
| Integration Document Management API (`DocumentManagementController`) | CRUD-like integration operations with validation, UUID checks, and context mapping             | High change surface, contract governance sensitivity                  | Validation and context mappings provide realistic mutation targets | `DocumentManagementTest`                                      |               4 | Guard inversion, constant alteration, key lookup alteration |

## 5. Planned Mutation Inventory

Planned mutants were formalized in:

- `qa/phase3/metrics/phase3-mutants.csv`
- `qa/phase3/metrics/phase3-mutants.json`

Execution automation:

- `qa/phase3/mutation/plans/run-phase3-mutation.ps1`

## 6. Test Selection Strategy

Per mutant, execute only relevant tests for the mutated module:

1. Boundary middleware mutants: boundary and integration rate-limit tests.
2. Reservations read mutants: reservation read tests only.
3. Reservations mutate mutants: reservation mutate tests only.
4. Document management mutants: document management tests only.

This approach minimizes unrelated noise and isolates fault-detection capability.

## 7. Expected Outputs

The campaign will produce:

1. Mutant registry.
2. Per-mutant execution logs with killed/survived status.
3. Mutation score per module and overall.
4. Surviving-mutant gap analysis with recommendations.

---

KazUTB Digital Library — QA experimental evaluation layer (Phase 3) Part 2
