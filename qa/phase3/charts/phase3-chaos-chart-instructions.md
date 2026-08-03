# experimental evaluation layer (Phase 3) Chaos Charts — Rendering Instructions

Date: 2026-05-13

Use these CSV files:

1. `qa/phase3/charts/phase3-chaos-availability-chart.csv`
2. `qa/phase3/charts/phase3-chaos-recovery-chart.csv`
3. `qa/phase3/charts/phase3-chaos-error-propagation-chart.csv`

## Chart 1: Availability under Fault vs Recovery

Dataset: `phase3-chaos-availability-chart.csv`
Type: grouped bar chart

Columns:

- `scenario_id`
- `fault_availability_pct`
- `recovery_availability_pct`

Interpretation:

- Compare service availability during fault injection and after fault removal.

## Chart 2: Recovery Time (MTTR Proxy)

Dataset: `phase3-chaos-recovery-chart.csv`
Type: bar chart

Columns:

- `scenario_id`
- `mttr_ms`

Interpretation:

- Lower values indicate faster first-success recovery after fault removal.

## Chart 3: Error Propagation Class

Dataset: `phase3-chaos-error-propagation-chart.csv`
Type: stacked bar or grouped bar

Columns:

- `scenario_id`
- `fault_error_count`
- `propagation_class`
- `probe_reachable`

Interpretation:

- Distinguish isolated failures from cascading impact.

## Formatting Guidance

1. Use consistent y-axis percent scaling (0-100) for availability chart.
2. Label MTTR axis in milliseconds.
3. Use distinct colors:

- Fault availability: orange
- Recovery availability: green
- Isolated propagation: blue
- Cascading propagation: red

---

KazUTB Digital Library — QA experimental evaluation layer (Phase 3) Part 3
