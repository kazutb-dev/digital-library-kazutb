# automation and CI governance layer (Phase 2) Chart Instructions

This guide explains how to generate charts from factual automation and CI governance layer (Phase 2) datasets.

## Source files

- qa/phase2/metrics/charts/phase2-automation-coverage-chart.csv
- qa/phase2/metrics/charts/phase2-execution-time-chart.csv
- qa/phase2/metrics/charts/phase2-defects-vs-risk-chart.csv

## Chart A: Automation coverage by module

1. Open phase2-automation-coverage-chart.csv in spreadsheet software.
2. Use module_feature as category axis.
3. Use coverage_percent as value axis.
4. Optional secondary labels: automated_checks and total_planned_high_risk_checks.
5. Recommended chart type: clustered column.

## Chart B: Execution time by suite

1. Open phase2-execution-time-chart.csv.
2. Filter environment = local for measured values.
3. Use module_feature as category axis.
4. Use total_execution_time_seconds as primary value.
5. Optional secondary series: avg_execution_time_per_test_case_seconds.
6. Recommended chart type: combo (column + line).

## Chart C: Defects vs risk profile

1. Open phase2-defects-vs-risk-chart.csv.
2. Use module_feature as category axis.
3. Use defects_found as value axis.
4. Use pass_fail as color grouping.
5. Recommended chart type: stacked column or grouped bar.

## Reproducibility notes

- Do not edit values manually in chart CSV files.
- If source metrics change, regenerate chart CSV files from source datasets first.
- Keep generated chart images and captions under qa/phase2/reports/execution when added.
