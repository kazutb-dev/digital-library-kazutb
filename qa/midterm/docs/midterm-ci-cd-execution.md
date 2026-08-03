# Intermediate Empirical Review CI/CD Execution

## Pipeline used

Repository workflow: .github/workflows/ci.yml (GitHub Actions).

## Observed CI behavior (from workflow definition + phase2 evidence)

- Checkout source code.
- Run secret scanning.
- Install backend dependencies and run backend verification + coverage artifact generation.
- Install frontend dependencies, build assets, and run Playwright smoke.
- Validate phase2 artifact presence and gate schema.
- Evaluate fail-level quality gates for current_enforced rows.
- Upload artifacts.

## Required Intermediate Empirical Review interpretation

- Automation is integrated in CI/CD and not manual-only.
- Pass/fail and artifact generation are wired into the pipeline.
- Coverage generation exists in CI backend job (pcov-enabled), while local measured coverage remains blocked in referenced phase2 run.

## Evidence references

- qa/midterm/evidence/references/midterm-source-evidence.md
- qa/midterm/evidence/logs/ref-phase2-api-tests.log
- qa/midterm/evidence/logs/ref-phase2-playwright.log
- qa/midterm/evidence/logs/ref-phase2-phpunit.log
- qa/midterm/evidence/logs/ref-phase2-quality-gates.log

## Screenshot note

No new GitHub Actions screenshot was generated in this session.
This is explicitly tracked as a limitation, and factual log/reference paths are provided instead.
