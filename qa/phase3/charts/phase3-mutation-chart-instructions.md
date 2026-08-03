# experimental evaluation layer (Phase 3) Mutation Charts — Instructions

Date: 2026-05-13

This file explains how to render mutation charts from factual CSV datasets.

## Source Datasets

1. `qa/phase3/charts/phase3-mutation-score-chart.csv`
2. `qa/phase3/charts/phase3-mutant-status-chart.csv`

## Chart 1 — Mutation Score by Module

Dataset: `phase3-mutation-score-chart.csv`
Recommended chart: Clustered column chart

Columns used:

- `module`
- `mutation_score_pct`

Steps (Excel):

1. Open CSV.
2. Select `module` and `mutation_score_pct`.
3. Insert -> Column -> Clustered Column.
4. Set Y-axis to 0-100.
5. Add data labels (%).
6. Title: `experimental evaluation layer (Phase 3) Part 2 Mutation Score by Module`.

## Chart 2 — Mutant Status Distribution by Module

Dataset: `phase3-mutant-status-chart.csv`
Recommended chart: Stacked column chart

Columns used:

- `module`
- `status`
- `count`

Steps (Excel):

1. Open CSV.
2. Insert PivotTable.
3. Rows: `module`
4. Columns: `status`
5. Values: sum of `count`
6. Insert -> Stacked Column chart.
7. Title: `Killed vs Survived Mutants by Module`.

Color suggestion:

- Killed: green
- Survived: orange
- Error/Inconclusive: gray

## Quality Check

Before presenting charts, verify:

1. Total mutants in status chart equals 14.
2. Overall killed=12 and survived=2 are consistent with `phase3-mutation-score.csv`.

---

KazUTB Digital Library — QA experimental evaluation layer (Phase 3) Part 2
