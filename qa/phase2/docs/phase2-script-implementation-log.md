# automation and CI governance layer (Phase 2) Script Implementation Log

| Script ID | Module/Feature        | Framework  | Script Name / Location                                     | Status   | Risk Covered | Comments                                              |
| --------- | --------------------- | ---------- | ---------------------------------------------------------- | -------- | ------------ | ----------------------------------------------------- |
| P2-S-001  | Shared HTTP client    | PowerShell | qa/phase2/automation/shared/utils/http-client.ps1          | Complete | R1,R2,R4     | Reusable request helper with timing and error capture |
| P2-S-002  | API Auth              | PowerShell | qa/phase2/automation/api/auth-access.api.test.ps1          | Complete | R1,R14       | 3 auth and guard checks                               |
| P2-S-003  | API Catalog           | PowerShell | qa/phase2/automation/api/catalog-public.api.test.ps1       | Complete | R2           | 3 public catalog checks                               |
| P2-S-004  | API Integration       | PowerShell | qa/phase2/automation/api/integration-boundary.api.test.ps1 | Complete | R4           | 2 integration boundary checks                         |
| P2-S-005  | API Admin/Circulation | PowerShell | qa/phase2/automation/api/admin-circulation.api.test.ps1    | Complete | R3,R5        | 2 operations guard checks                             |
| P2-S-006  | API Runner            | PowerShell | qa/phase2/automation/api/run-phase2-api-smoke.ps1          | Complete | R1-R5        | Aggregates cases and emits report/log                 |
| P2-S-007  | UI Config             | Playwright | qa/phase2/automation/ui/playwright.phase2.config.ts        | Complete | R2,R14       | Isolated automation and CI governance layer (Phase 2) config                               |
| P2-S-008  | UI Public routes      | Playwright | qa/phase2/automation/ui/public-catalog.ui.spec.ts          | Complete | R2,R5        | 5 route checks                                        |
| P2-S-009  | UI Auth/Role routes   | Playwright | qa/phase2/automation/ui/auth-role-access.ui.spec.ts        | Complete | R1,R14       | 3 route checks                                        |
| P2-S-010  | UI Ops routes         | Playwright | qa/phase2/automation/ui/circulation-ops.ui.spec.ts         | Complete | R3,R8,R9     | 3 route checks                                        |
