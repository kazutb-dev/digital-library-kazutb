# baseline QA layer (Phase 1) Risk Register

**Author:** Murat Almas
**Date:** 2026-05-13

| ID  | Module/Component      | Failure Mode                       | Prob | Impact | Score | Priority | Detection Strategy      | Mitigation Strategy        | Test Types    |
| --- | --------------------- | ---------------------------------- | ---- | ------ | ----- | -------- | ----------------------- | -------------------------- | ------------- |
| R1  | Auth                  | Broken login/session               | 3    | 5      | 15    | P0       | Unit/Feature/E2E        | Strong validation, tests   | Unit, E2E     |
| R2  | Catalog               | Search returns wrong/missing items | 4    | 4      | 16    | P0       | Feature/E2E, logs       | Query tests, data seeding  | Feature, E2E  |
| R3  | Circulation           | Reservation fails or overbooks     | 3    | 5      | 15    | P0       | Feature/E2E, DB checks  | Transactional logic, tests | Feature, E2E  |
| R4  | API                   | Unauthorized access                | 2    | 5      | 10    | P1       | API/Feature, logs       | Auth middleware, tests     | API, Feature  |
| R5  | Admin                 | News not published/visible         | 2    | 4      | 8     | P1       | Feature, UI check       | Admin tests, UI review     | Feature       |
| R6  | External Integrations | Resource fetch fails               | 3    | 3      | 9     | P1       | Logs, Feature           | Mock/test integrations     | Feature, Unit |
| R7  | DB                    | Data loss/corruption               | 1    | 5      | 5     | P1       | Backups, DB checks      | Backups, migration tests   | N/A           |
| R8  | Shortlist             | Items not persisted                | 3    | 4      | 12    | P0       | Feature, API, logs      | Storage tests, API checks  | Feature, API  |
| R9  | Session               | Session loss for guests            | 2    | 3      | 6     | P2       | Feature, logs           | Session tests, config      | Feature       |
| R10 | Playwright E2E        | E2E tests fail to run              | 2    | 4      | 8     | P1       | CI, logs                | Toolchain validation       | E2E           |
| R11 | Docker                | Container fails to start           | 2    | 5      | 10    | P1       | CI, logs                | Compose/test scripts       | N/A           |
| R12 | CI/CD                 | Pipeline fails, blocks deploy      | 2    | 5      | 10    | P1       | CI, logs                | Pipeline tests, docs       | N/A           |
| R13 | Coverage              | High-risk code untested            | 4    | 4      | 16    | P0       | Coverage report, review | Add tests, review          | All           |
| R14 | Permissions           | Role boundary bypass               | 2    | 5      | 10    | P1       | Feature, API, logs      | Middleware, tests          | Feature, API  |
| R15 | Logging/Monitoring    | Failure not detected               | 3    | 3      | 9     | P2       | Log review, alerting    | Add logging, alerting      | N/A           |

**Legend:**

- Prob: Probability (1-5)
- Impact: Impact (1-5)
- Score: Prob x Impact
- Priority: P0 (highest), P1, P2
- Test Types: Unit, Feature, API, E2E, N/A

All risks are prioritized for baseline QA layer (Phase 1) test design and coverage.



