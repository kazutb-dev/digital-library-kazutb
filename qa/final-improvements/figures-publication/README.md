# Clean Paper Figure Package

This directory is a new figure package built from scratch from canonical evidence in `qa/final-improvements/`.

## What It Contains

- 8 main figures (`fig-01` to `fig-08`) in SVG and PNG
- `manifest.csv` listing generated assets
- generation script in `scripts/build_figures.py`
- figure documentation files for captions, mapping, selection, and rendering notes

## Primary Manuscript Figures

1. `fig-01-risk-heatmap`
2. `fig-02-coverage-vs-risk`
3. `fig-03-execution-time-comparison`
4. `fig-04-performance-bounds`
5. `fig-05-cicd-pipeline`
6. `fig-06-testing-architecture`
7. `fig-07-defect-detection-comparison`
8. `fig-08-quality-gates-summary`

## Canonical Evidence Sources Used

- `qa/final-improvements/metrics/risk-table.csv`
- `qa/final-improvements/metrics/coverage-vs-risk.csv`
- `qa/final-improvements/metrics/execution-time-comparison.csv`
- `qa/final-improvements/metrics/performance-rerun.csv`
- `qa/final-improvements/tables/defect-detection-table.md`
- `qa/final-improvements/tables/quality-gates-table.md`
- `qa/final-improvements/tables/performance-summary-table.md`
- `qa/final-improvements/tables/mutation-summary-table.md`
- `qa/final-improvements/SUMMARY.md`
- `qa/final-improvements/TRACEABILITY.md`

## Why This Set Is Intentionally Small

The package intentionally keeps only clean, chart-worthy visuals and two concise methodology diagrams. The two new charts are summary visualizations only; detailed defect detection and quality gate definitions remain as tables because they are clearer and more precise there.

## Rebuild Command

Run from repository root:

```powershell
python qa/final-improvements/figures-publication/scripts/build_figures.py
```
