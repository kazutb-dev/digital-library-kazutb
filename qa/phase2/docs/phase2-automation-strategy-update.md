# automation and CI governance layer (Phase 2) Automation Strategy Update

## Tool selection rationale

- Playwright is used for browser-level smoke and public route stability checks.
- PHPUnit is used for backend workflow regression on critical modules.
- PowerShell API smoke scripts provide fast endpoint and policy checks that are easy to run locally.

## Automation organization

- qa/phase2/automation/ui: Playwright route and guard smoke scenarios.
- qa/phase2/automation/api: domain-specific API smoke scripts.
- qa/phase2/automation/shared: reusable HTTP helper and endpoint inventory.

## Reusability strategy

- Shared HTTP request utility and endpoint inventory file.
- Consistent case ID format across API, UI, and governance datasets.
- Script-level mapping now captured in qa/phase2/metrics/phase2-test-execution-log.csv.

## Maintainability strategy

- Focus on high-risk scenario assertions over brittle deep UI selector trees.
- Keep scripts domain-scoped and small for selective reruns.
- Preserve both machine-readable (CSV/JSON) and human-readable (Markdown/log) outputs.

## Data strategy

- Metric datasets now carry explicit denominator fields for coverage and evidence references for defects.
- Execution-time dataset now includes average per test case and environment context.
- Chart-source CSVs under qa/phase2/metrics/charts support reproducible figure generation.

## CI fit

- Current quality-gate evaluation enforces current_enforced fail-level rows and keeps proposed_next_stage gates informational.
- Evidence, metrics, and chart-source artifacts are linked through the evidence index for reproducibility.

## Research paper relevance

- Enables transparent mapping from risk assumptions to observed defect concentrations.
- Supports quantitative tables and chart figures without manual recomputation.
- Documents both achieved maturity and unresolved reliability gaps.
