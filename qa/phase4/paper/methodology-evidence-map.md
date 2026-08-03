# Methodology Evidence Map

Date: 2026-05-13

## Purpose

Map paper methodology statements to factual repository artifacts from phases 1-3 and midterm.

## Evidence Mapping

| Methodology Component               | Claim Scope                                                               | Evidence Artifacts                                                                                                                        | Key Metrics/Signals                                              | Limitations to Disclose                                       |
| ----------------------------------- | ------------------------------------------------------------------------- | ----------------------------------------------------------------------------------------------------------------------------------------- | ---------------------------------------------------------------- | ------------------------------------------------------------- |
| Risk-first baseline design          | QA campaign started with risk prioritization and explicit test strategy   | qa/phase1/docs/phase1-risk-register.md; qa/phase1/docs/phase1-test-strategy.md                                                            | 15 baseline risks; P0/P1/P2 tiering                              | Early phase had blocked coverage-driver metrics               |
| Baseline measurement discipline     | Metrics collection followed explicit rules and blocked-metric policy      | qa/phase1/docs/phase1-baseline-metrics-methodology.md; qa/phase1/metrics/phase1-baseline-metrics.csv                                      | Manual tests=25; unit=2; feature=97; e2e=1                       | Coverage values marked blocked/not measured where unavailable |
| Automation and governance expansion | Quality gates and automation depth were formalized                        | qa/phase2/TRACEABILITY.md; qa/phase2/docs/phase2-metrics-report.md; qa/phase2/metrics/phase2-quality-gate-results.csv                     | Weighted coverage=75%; gates pass=5 fail=3 warn=1                | Stability not fully recovered by phase2 completion            |
| Intermediate Empirical Review empirical reassessment loop | Risk was re-scored using observed evidence and weak-spot analysis         | qa/midterm/docs/midterm-methodology.md; qa/midterm/docs/midterm-final-report.md; qa/midterm/metrics/midterm-required-metrics.csv          | 9 observed issues; 75.0% weighted coverage; 8/8 new tests passed | Flaky rate not measurable with available repeated-run history |
| Experimental methodology (phase3)   | Performance, mutation, and chaos used bounded reproducible campaigns      | qa/phase3/docs/phase3-performance-methodology.md; qa/phase3/docs/phase3-experimental-setup.md; qa/phase3/docs/phase3-chaos-test-plan.md   | Performance 8/9 pass; mutation 85.71%; chaos recovery 100%       | Sequential local load and synthetic fault models              |
| Synthesis methodology               | Observed-vs-expected and integrated reporting used cross-phase comparison | qa/phase3/docs/phase3-observed-vs-expected.md; qa/phase3/docs/phase3-experimental-final-report.md; qa/phase3/docs/phase3-final-summary.md | Integration boundary consistently high-risk                      | Not a production-scale operational study                      |

## Methodology Figure Candidates

1. qa/paper-assets/figures/summary/summary_quality_progression_across_phases.png
2. qa/paper-assets/figures/phase1/phase1_manual_vs_automated_inventory.png

---

KazUTB Digital Library - research synthesis layer (Phase 4) Part 1
