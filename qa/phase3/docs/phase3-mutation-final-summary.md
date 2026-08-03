# experimental evaluation layer (Phase 3) Part 2 — Mutation Testing Final Summary

Project: KazUTB Digital Library
Date: 2026-05-13
Run ID: 20260513-140552

## Executive Summary

A bounded mutation campaign was executed across four high-risk integration modules using a controlled scripted manual approach. The campaign generated 14 realistic mutants and executed targeted automated tests per mutant. Results: 12 mutants killed, 2 survived, 0 inconclusive, yielding an overall mutation score of 85.71%.

The strongest areas are boundary and validation logic. The weakest area is service-context payload assertion fidelity in tests that use broad array matchers.

## Modules Selected

1. Integration Boundary Middleware
2. Integration Reservations Read API
3. Integration Reservations Mutate API
4. Integration Document Management API

## Mutation Scope

1. Bounded to 14 mutants (quality-focused, not brute force).
2. Mutation types: guard inversion, logical operator changes, constant alteration, return modification, key lookup alteration.
3. Mutants applied one-at-a-time with full source restoration after each run.

## Results Snapshot

| Metric                 |  Value |
| ---------------------- | -----: |
| Mutants Created        |     14 |
| Mutants Killed         |     12 |
| Mutants Survived       |      2 |
| Inconclusive/Error     |      0 |
| Overall Mutation Score | 85.71% |

## Strongest Areas

1. Integration boundary authentication/header/source-system enforcement.
2. Reservation read validation and not-found behavior.
3. High sensitivity to broken guard conditions and relaxed constraints.

## Weakest Areas

1. Context mapping verification in controller-to-service interactions.
2. Missing strict assertions for operator-id propagation fields.

## Key Next Actions

1. Strengthen context assertion expectations in `ReservationMutateTest` and `DocumentManagementTest`.
2. Add dedicated context-mapping regression tests.
3. Re-run this bounded campaign after test improvements to target >90% without survivors in context class.

## experimental evaluation layer (Phase 3) Part 3 Recommendation

experimental evaluation layer (Phase 3) Part 3 should focus on mutation-driven hardening:

1. Implement P1 mutation recommendations.
2. Expand mutant set by 6-10 additional high-value service-level mutants.
3. Track mutation score trend over at least two consecutive runs.

## Artifact References

- Plan: `qa/phase3/docs/phase3-mutation-plan.md`
- Execution: `qa/phase3/docs/phase3-mutation-execution-report.md`
- Score: `qa/phase3/docs/phase3-mutation-score-report.md`
- Gaps: `qa/phase3/docs/phase3-mutation-gap-analysis.md`
- Recommendations: `qa/phase3/docs/phase3-mutation-recommendations.md`

---

KazUTB Digital Library — QA experimental evaluation layer (Phase 3) Part 2
