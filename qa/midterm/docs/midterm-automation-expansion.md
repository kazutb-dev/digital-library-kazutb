# Intermediate Empirical Review Automation Expansion

## New tests added (8 total)

- Unit: 2
- Integration: 4
- E2E: 2

## Category coverage

- Failure scenarios: MID-INT-001
- Edge cases: MID-U-001, MID-U-002, MID-E2E-002
- Concurrency/race-like pressure: MID-INT-004, MID-E2E-002
- Invalid user behavior: MID-INT-002, MID-INT-003, MID-E2E-001

## Test type depth requirement

- Unit tests implemented in tests/Unit/Services/BibliographyFormatterMidtermTest.php.
- Integration tests implemented in tests/Feature/Api/MidtermIntegrationRiskExpansionTest.php.
- E2E tests implemented in tests/e2e/midterm-risk-expansion.spec.ts.

## Execution evidence

- qa/midterm/evidence/logs/midterm-new-phpunit.log
- qa/midterm/evidence/logs/midterm-new-playwright.log

## Mapping: test to system behavior

The complete mapping with input data, expected output, actual result, level, script path, and evidence is maintained in:

- qa/midterm/metrics/midterm-new-tests.csv
