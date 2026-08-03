# experimental evaluation layer (Phase 3) Mutation Command Log

Date: 2026-05-13

## Commands Executed

1. Baseline targeted tests:
    - `php artisan test tests/Feature/Api/Integration/IntegrationRateLimitTest.php tests/Feature/Api/IntegrationBoundarySkeletonTest.php tests/Feature/Api/Integration/ReservationReadTest.php tests/Feature/Api/Integration/ReservationMutateTest.php tests/Feature/Api/Integration/DocumentManagementTest.php`
2. Mutation campaign:
    - `pwsh -File qa/phase3/mutation/plans/run-phase3-mutation.ps1`
3. Score/gap/chart derivation from result CSV:
    - PowerShell aggregation using `Import-Csv qa/phase3/metrics/phase3-mutation-results.csv`

## Run Metadata

- Mutation run ID: `20260513-140552`
- Mutants executed: 14
- Killed: 12
- Survived: 2
- Inconclusive: 0
- Overall score: 85.71%

## Evidence Files

Mutation logs are stored under:

- `qa/phase3/evidence/logs/phase3-mutation-*.log`

Raw run payload:

- `qa/phase3/mutation/results/mutation-run-20260513-140552.json`

---

KazUTB Digital Library — QA experimental evaluation layer (Phase 3) Part 2
