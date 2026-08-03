# Intermediate Empirical Review QA Implementation & Empirical Analysis

This directory contains the intermediate empirical review synthesis layer for the KazUTB Digital Library.

## Scope relationship

- baseline risk-based QA strategy (Phase 1): risk baseline and prioritization.
- automation and quality governance layer (Phase 2): automation evidence, quality gates, CI integration, and measured metrics.
- Intermediate Empirical Review: empirical reassessment and expansion package built from baseline QA layer (Phase 1) + automation and CI governance layer (Phase 2) evidence plus newly executed Intermediate Empirical Review tests.

## Contents

- docs/: narrative deliverables and final report sections.
- metrics/: machine-readable datasets (CSV/JSON parity).
- charts/: chart-ready CSV files and generation instructions.
- evidence/: execution logs, references, and screenshot inventory.
- reports/: executive summary artifact for quick review.

## Reproducibility

1. Review source evidence mapping in qa/midterm/evidence/references/midterm-source-evidence.md.
2. Re-run intermediate empirical review new PHPUnit tests:
   - php artisan test --filter='BibliographyFormatterMidtermTest|MidtermIntegrationRiskExpansionTest'
3. Re-run intermediate empirical review new E2E tests:
   - npx playwright test tests/e2e/midterm-risk-expansion.spec.ts --reporter=list
4. Regenerate metrics/charts if source evidence changes.
5. Verify CSV/JSON parity and references before submission.
