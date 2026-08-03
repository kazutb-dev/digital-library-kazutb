# experimental evaluation layer (Phase 3) Part 2 — Mutation Execution Report

Project: KazUTB Digital Library
Date: 2026-05-13
Run ID: 20260513-140552

## 1. Execution Approach

Method: Controlled manual mutation campaign automated by PowerShell script.

Script used:

- `qa/phase3/mutation/plans/run-phase3-mutation.ps1`

The script performs for each mutant:

1. Backup target file content in memory.
2. Apply exact mutation snippet replacement.
3. Run module-targeted automated tests.
4. Capture output and exit code to mutant-specific log.
5. Restore original source file.

## 2. Baseline Pre-Check

Before mutation, the targeted suite passed:

- Command: `php artisan test tests/Feature/Api/Integration/IntegrationRateLimitTest.php tests/Feature/Api/IntegrationBoundarySkeletonTest.php tests/Feature/Api/Integration/ReservationReadTest.php tests/Feature/Api/Integration/ReservationMutateTest.php tests/Feature/Api/Integration/DocumentManagementTest.php`
- Result: 54 passed, 0 failed

## 3. Mutant Execution Summary

| Mutant ID    | Module                              | Tests Executed                                             | Status   | Exit Code | Evidence Log                                                               |
| ------------ | ----------------------------------- | ---------------------------------------------------------- | -------- | --------: | -------------------------------------------------------------------------- |
| MUT-INTB-001 | Integration Boundary Middleware     | IntegrationBoundarySkeletonTest + IntegrationRateLimitTest | Killed   |         1 | `qa/phase3/evidence/logs/phase3-mutation-MUT-INTB-001-20260513-140552.log` |
| MUT-INTB-002 | Integration Boundary Middleware     | IntegrationBoundarySkeletonTest + IntegrationRateLimitTest | Killed   |         1 | `qa/phase3/evidence/logs/phase3-mutation-MUT-INTB-002-20260513-140552.log` |
| MUT-INTB-003 | Integration Boundary Middleware     | IntegrationBoundarySkeletonTest + IntegrationRateLimitTest | Killed   |         1 | `qa/phase3/evidence/logs/phase3-mutation-MUT-INTB-003-20260513-140552.log` |
| MUT-RDR-001  | Integration Reservations Read API   | ReservationReadTest                                        | Killed   |         1 | `qa/phase3/evidence/logs/phase3-mutation-MUT-RDR-001-20260513-140552.log`  |
| MUT-RDR-002  | Integration Reservations Read API   | ReservationReadTest                                        | Killed   |         1 | `qa/phase3/evidence/logs/phase3-mutation-MUT-RDR-002-20260513-140552.log`  |
| MUT-RDR-003  | Integration Reservations Read API   | ReservationReadTest                                        | Killed   |         1 | `qa/phase3/evidence/logs/phase3-mutation-MUT-RDR-003-20260513-140552.log`  |
| MUT-MUT-001  | Integration Reservations Mutate API | ReservationMutateTest                                      | Killed   |         1 | `qa/phase3/evidence/logs/phase3-mutation-MUT-MUT-001-20260513-140552.log`  |
| MUT-MUT-002  | Integration Reservations Mutate API | ReservationMutateTest                                      | Killed   |         1 | `qa/phase3/evidence/logs/phase3-mutation-MUT-MUT-002-20260513-140552.log`  |
| MUT-MUT-003  | Integration Reservations Mutate API | ReservationMutateTest                                      | Killed   |         1 | `qa/phase3/evidence/logs/phase3-mutation-MUT-MUT-003-20260513-140552.log`  |
| MUT-MUT-004  | Integration Reservations Mutate API | ReservationMutateTest                                      | Survived |         0 | `qa/phase3/evidence/logs/phase3-mutation-MUT-MUT-004-20260513-140552.log`  |
| MUT-DOC-001  | Integration Document Management API | DocumentManagementTest                                     | Killed   |         1 | `qa/phase3/evidence/logs/phase3-mutation-MUT-DOC-001-20260513-140552.log`  |
| MUT-DOC-002  | Integration Document Management API | DocumentManagementTest                                     | Killed   |         1 | `qa/phase3/evidence/logs/phase3-mutation-MUT-DOC-002-20260513-140552.log`  |
| MUT-DOC-003  | Integration Document Management API | DocumentManagementTest                                     | Killed   |         1 | `qa/phase3/evidence/logs/phase3-mutation-MUT-DOC-003-20260513-140552.log`  |
| MUT-DOC-004  | Integration Document Management API | DocumentManagementTest                                     | Survived |         0 | `qa/phase3/evidence/logs/phase3-mutation-MUT-DOC-004-20260513-140552.log`  |

## 4. Result Totals

1. Mutants executed: 14
2. Killed: 12
3. Survived: 2
4. Inconclusive/Error: 0

## 5. Generated Data Files

- `qa/phase3/metrics/phase3-mutants.csv`
- `qa/phase3/metrics/phase3-mutants.json`
- `qa/phase3/metrics/phase3-mutation-results.csv`
- `qa/phase3/metrics/phase3-mutation-results.json`
- `qa/phase3/mutation/results/mutation-run-20260513-140552.json`

---

KazUTB Digital Library — QA experimental evaluation layer (Phase 3) Part 2
