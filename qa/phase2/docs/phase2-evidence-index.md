# automation and CI governance layer (Phase 2) Evidence Index

| Evidence ID | Area | Type | Description | File Location | Status |
| --- | --- | --- | --- | --- | --- |
| P2-E-001 | API automation | Log | API smoke runner output with per-case status and elapsed time | qa/phase2/evidence/logs/phase2-api-tests.log | implemented |
| P2-E-002 | PHPUnit automation | Log | Targeted high-risk feature/API PHPUnit execution output | qa/phase2/evidence/logs/phase2-phpunit.log | implemented |
| P2-E-003 | UI automation | Log | Playwright automation and CI governance layer (Phase 2) UI suite output with pass/fail summary | qa/phase2/evidence/logs/phase2-playwright.log | implemented |
| P2-E-004 | Automation aggregate | Log | Consolidated API/PHPUnit/Playwright run metrics | qa/phase2/evidence/logs/phase2-automation-summary.log | implemented |
| P2-E-005 | Coverage availability | Log | Coverage driver detection result in measured environment | qa/phase2/evidence/logs/phase2-coverage.log | implemented |
| P2-E-006 | Quality gate evaluation | Log | Gate-by-gate enforced/proposed status evaluation | qa/phase2/evidence/logs/phase2-quality-gates.log | implemented |
| P2-E-007 | CI local validation | Log | Local validation of workflow and required gate artifacts | qa/phase2/evidence/logs/phase2-ci-local-validation.log | implemented |
| P2-E-008 | Gate results dataset | CSV/JSON | Source-of-truth matrix for CI gate evaluation | qa/phase2/metrics/phase2-quality-gate-results.csv; qa/phase2/metrics/phase2-quality-gate-results.json | implemented |
| P2-E-009 | Workflow implementation | YAML | Production CI workflow with phase2-quality-gates job | .github/workflows/ci.yml | implemented |
| P2-E-010 | Reusable CI snippet | YAML | Standalone automation and CI governance layer (Phase 2) snippet for portability and review | qa/phase2/ci/phase2-ci-snippet.yml | implemented |
| P2-E-011 | Gate policy definition | YAML | Structured gate definitions with current vs proposed tiers | qa/phase2/ci/phase2-quality-gates.yml | implemented |
| P2-E-012 | Pipeline diagram | Markdown | Text and mermaid pipeline flow for assignment and handoff | qa/phase2/ci/phase2-workflow-diagram.md | implemented |
| P2-E-013 | UI failure screenshot | Screenshot | Public news route failure captured by Playwright | qa/phase2/reports/test-results/playwright-raw/public-catalog.ui-P2-UI-CAT-004-news-page-loads-chromium/test-failed-1.png | implemented |
| P2-E-014 | UI failure context | Report | Detailed parse/runtime error context | qa/phase2/reports/test-results/playwright-raw/public-catalog.ui-P2-UI-CAT-004-news-page-loads-chromium/error-context.md | implemented |
| P2-E-015 | Execution time metrics | CSV/JSON | Runtime measurements including average per test case and environment | qa/phase2/metrics/phase2-execution-time.csv; qa/phase2/metrics/phase2-execution-time.json | implemented |
| P2-E-016 | Test execution ledger | CSV/JSON | Case-level pass/fail with script, risk, and evidence references | qa/phase2/metrics/phase2-test-execution-log.csv; qa/phase2/metrics/phase2-test-execution-log.json | implemented |
| P2-E-017 | Automation coverage metrics | CSV/JSON | Coverage metrics with planned-check denominator | qa/phase2/metrics/phase2-automation-coverage.csv; qa/phase2/metrics/phase2-automation-coverage.json | implemented |
| P2-E-018 | Defects-vs-risk metrics | CSV/JSON | Module-level defects linked to risk tier and evidence | qa/phase2/metrics/phase2-defects-vs-risk.csv; qa/phase2/metrics/phase2-defects-vs-risk.json | implemented |
| P2-E-019 | Chart-source datasets | CSV | Visualization-ready tables for coverage, execution, defects | qa/phase2/metrics/charts/*.csv | implemented |
| P2-E-020 | Chart generation instructions | Markdown | Reproducible chart generation procedure for reports | qa/phase2/metrics/charts/phase2-chart-instructions.md | implemented |
| P2-E-021 | Metrics synthesis report | Markdown | Consolidated metric narrative for results chapter usage | qa/phase2/docs/phase2-metrics-report.md | implemented |
| P2-E-022 | Metrics interpretation | Markdown | Risk and quality interpretation tied to baseline QA layer (Phase 1) assumptions | qa/phase2/docs/phase2-metrics-interpretation.md | implemented |
| P2-E-023 | Deliverables checklist | Markdown | Submission-readiness checklist with completion status | qa/phase2/docs/phase2-deliverables-checklist.md | implemented |
| P2-E-024 | Execution reproducibility snapshot | Markdown | Snapshot artifact linking run logs to structured metrics | qa/phase2/reports/execution/phase2-execution-dataset-snapshot.md | implemented |
| P2-E-025 | Screenshot inventory note | Markdown | Clarifies current screenshot inventory and update rule | qa/phase2/evidence/screenshots/README.md | implemented |

## Remaining limitations (factual)

- Slack and email integrations are not configured in repository workflows; GitHub-native visibility is the active alerting channel.
- Proposed next-stage gates (coverage >=80 percent, static analysis, flaky rerun control, and 100 percent critical workflow success) are documented but not enforced.
- Current enforced gate set remains red due measured failures in API smoke, targeted PHPUnit subset, and public /news fatal error detection.
