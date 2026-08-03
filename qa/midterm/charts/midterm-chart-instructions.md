# Intermediate Empirical Review Chart Instructions

## Sources

- qa/midterm/charts/midterm-coverage-chart.csv
- qa/midterm/charts/midterm-defects-chart.csv
- qa/midterm/charts/midterm-execution-time-chart.csv
- qa/midterm/charts/midterm-planned-vs-actual-chart.csv

## Chart 1: Coverage gaps

- Use module as category axis.
- Use coverage_percent as value axis.
- Highlight rows where coverage_percent < 70.

## Chart 2: Defects by module

- Use module as category axis.
- Use failed_tests as value axis.
- Recommended type: bar chart.

## Chart 3: Execution time comparison

- Use suite as category axis.
- Use seconds as value axis.
- Recommended type: column chart.

## Chart 4: Planned vs actual risk score

- Use aspect as category axis.
- Use planned_risk_score and actual_risk_score as two series.
- Recommended type: grouped column chart.

## Reproducibility

- Do not edit chart data manually.
- Regenerate chart csv files from metrics when source metrics change.
- If image exports are produced later, place them under qa/midterm/evidence/screenshots.
