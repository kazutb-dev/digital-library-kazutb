# experimental evaluation layer (Phase 3) Part 2 — Mutation Score Report

Project: KazUTB Digital Library
Date: 2026-05-13
Run ID: 20260513-140552

## 1. Formula

Mutation Score (%) = (Killed Mutants / Executed Non-Equivalent Mutants) x 100

Definitions:

1. Executed Non-Equivalent Mutants = Created - Equivalent - Inconclusive
2. Equivalent mutants were not identified in this bounded run.
3. Inconclusive mutants were 0.

## 2. Module-Level Score

| Module / Component                  | Mutant Type | Mutants Created | Mutants Killed | Mutants Survived | Inconclusive | Equivalent | Executed Non-Equivalent | Mutation Score (%) | Notes                                                 |
| ----------------------------------- | ----------- | --------------: | -------------: | ---------------: | -----------: | ---------: | ----------------------: | -----------------: | ----------------------------------------------------- |
| Integration Boundary Middleware     | mixed       |               3 |              3 |                0 |            0 |          0 |                       3 |             100.00 | Strong detection for boundary guards and source rules |
| Integration Reservations Read API   | mixed       |               3 |              3 |                0 |            0 |          0 |                       3 |             100.00 | Validation and not-found assertions are effective     |
| Integration Reservations Mutate API | mixed       |               4 |              3 |                1 |            0 |          0 |                       4 |              75.00 | One context-mapping mutant survived                   |
| Integration Document Management API | mixed       |               4 |              3 |                1 |            0 |          0 |                       4 |              75.00 | One context-mapping mutant survived                   |
| Overall                             | mixed       |              14 |             12 |                2 |            0 |          0 |                      14 |              85.71 | High overall score with targeted weaknesses           |

## 3. Interpretation

1. The overall score (85.71%) indicates strong fault detection for guard/validation logic.
2. Surviving mutants cluster in context payload mapping where tests use broad array matchers and do not verify exact context values.
3. The suite is strongest in request validation and authorization-path behavior.
4. The suite is weaker in assertions on internal service-call semantic context.

## 4. Output Files

- `qa/phase3/metrics/phase3-mutation-score.csv`
- `qa/phase3/metrics/phase3-mutation-score.json`
- `qa/phase3/charts/phase3-mutation-score-chart.csv`

---

KazUTB Digital Library — QA experimental evaluation layer (Phase 3) Part 2
