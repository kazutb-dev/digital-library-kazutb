# automation and CI governance layer (Phase 2): Test Automation Implementation

This folder contains automation and quality governance layer (automation and CI governance layer (Phase 2)) artifacts for the KazUTB Digital Library.

## Scope delivered

- High-risk automation scope definition grounded in baseline QA layer (Phase 1) risk analysis.
- API, UI, and targeted backend automation for critical workflows.
- Quality gates and CI integration with enforced/proposed separation.
- Metrics package for automation coverage, execution time, defects-vs-risk, and test governance logs.
- Reproducibility package for research reporting (evidence index, chart datasets, execution snapshots).

## Quick navigation

- Automation scripts: qa/phase2/automation
- Metrics datasets: qa/phase2/metrics
- Chart-ready datasets: qa/phase2/metrics/charts
- Evidence logs: qa/phase2/evidence/logs
- Evidence visuals: qa/phase2/evidence/screenshots
- Reports and summaries: qa/phase2/docs
- Execution snapshots: qa/phase2/reports/execution

## Reproducibility sequence

1. Run API smoke suite:
   - pwsh qa/phase2/automation/api/run-phase2-api-smoke.ps1
2. Run Playwright phase2 suite:
   - npx playwright test --config qa/phase2/automation/ui/playwright.phase2.config.ts
3. Run targeted PHPUnit subset used by automation and CI governance layer (Phase 2) evidence.
4. Refresh CSV and JSON datasets in qa/phase2/metrics from factual run output.
5. Refresh chart input files in qa/phase2/metrics/charts.
6. Update qa/phase2/docs/phase2-evidence-index.md with any new evidence artifacts.

## Primary evidence logs

- qa/phase2/evidence/logs/phase2-api-tests.log
- qa/phase2/evidence/logs/phase2-phpunit.log
- qa/phase2/evidence/logs/phase2-playwright.log
- qa/phase2/evidence/logs/phase2-automation-summary.log
- qa/phase2/evidence/logs/phase2-quality-gates.log
- qa/phase2/evidence/logs/phase2-ci-local-validation.log
