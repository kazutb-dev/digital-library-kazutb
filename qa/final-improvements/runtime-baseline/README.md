# Runtime Baseline Package

This folder captures the local Docker runtime baseline for the Digital Library platform in repository `kazutb-dev/digital-library-kazutb`.

## Contents

1. `runtime-inspection.md` — inspected runtime architecture, services, ports, env assumptions, and startup flow.
2. `startup-commands.md` — exact startup and verification commands executed.
3. `runtime-status.md` — factual post-start runtime status and reachability results.
4. `runtime-issues.md` — blockers, minimal fixes applied, and remaining risks.

## Scope of This Baseline

This package documents only local Docker runtime preparation and startup validation.
No broad QA enhancement work was performed in this step.

## Current Outcome

1. Docker stack is running (`app`, `postgres`, `frontend-dev`).
2. App and database services are healthy.
3. Baseline HTTP checks returned 200 for root, login, and catalog API.
4. Migrations ran successfully (`Nothing to migrate`, migration status all `Ran`).

## Before Full QA Reruns

The runtime baseline is established and suitable for subsequent QA rerun steps.
Any future test failures should be treated as QA/test logic findings, not startup-state uncertainty, unless container state changes.
