# Rendering Report

## Tools Used

- Python 3
- matplotlib
- seaborn
- pandas
- numpy

## Script Used

- `scripts/build_figures.py`

## Figures Generated

- fig-01-risk-heatmap
- fig-02-coverage-vs-risk
- fig-03-execution-time-comparison
- fig-04-performance-bounds
- fig-05-cicd-pipeline
- fig-06-testing-architecture
- fig-07-defect-detection-comparison
- fig-08-quality-gates-summary

## Figures Intentionally Omitted

- quality gates table-image figure (table-first artifact)
- risk-to-test mapping figure (table-first artifact)
- environment matrix figure (table-first artifact)
- text-heavy defect and mutation dashboards
- optional suite outcome figure (excluded for readability)

## New Summary Charts

- `fig-07-defect-detection-comparison` summarizes manual versus automated defect detection counts using a compact comparison bar chart.
- `fig-08-quality-gates-summary` summarizes gate state counts only; the detailed gate definitions remain in the quality gates table.

## Rendering Limitations

- Canonical `qa/final-improvements` metrics do not provide raw percentile trace data for direct p95 computation.
- `fig-04-performance-bounds` panel A uses a bounded proxy derived from canonical critical-path timing; caveat is explicit in captions and mapping.
- The new summary charts intentionally reduce detail and rely on the canonical tables for full gate and defect definitions.

## Why This Package Is Better Than the Discarded One

- Smaller set: eight focused figures only, with two additional summary charts that remain compact.
- No text-heavy pseudo-figures.
- No table-like dashboard charts.
- Short labels and clean axis wording.
- Explicit caveats for bounded performance evidence.
- Better manuscript fit and lower visual clutter.
