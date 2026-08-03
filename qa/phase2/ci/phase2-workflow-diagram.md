# automation and CI governance layer (Phase 2) Workflow Diagram

## Text Pipeline View

Trigger (push to main / pull_request to main / workflow_dispatch)
  -> secret-scan job (Gitleaks)
  -> backend-quality job (Composer QA + PHPUnit + coverage threshold + backend artifacts)
  -> browser-smoke job (frontend build + Playwright smoke + browser artifacts)
  -> phase2-quality-gates job
       -> validate required automation and CI governance layer (Phase 2) artifacts
       -> validate quality-gate CSV schema header
       -> evaluate current_enforced fail-level gates only
       -> upload phase2-qa-artifacts
       -> publish gate summary to GitHub Step Summary
  -> workflow result surfaced in GitHub Checks and Actions UI

## Mermaid View

```mermaid
flowchart TD
    A[Trigger: push main / PR / manual] --> B[secret-scan]
    B --> C[backend-quality]
    B --> D[browser-smoke]
    C --> E[phase2-quality-gates]
    D --> E
    E --> F[Validate artifacts and gate schema]
    F --> G{Any current_enforced fail gate with status Fail?}
    G -->|Yes| H[Fail workflow and expose results in checks]
    G -->|No| I[Upload phase2 artifacts and publish summary]
```

Notes:
- Implemented gating currently applies only to current_enforced rows in qa/phase2/metrics/phase2-quality-gate-results.csv.
- proposed_next_stage rows are tracked for roadmap visibility and do not break CI.
- Artifact upload preserves metrics and evidence for reproducibility and auditability.
