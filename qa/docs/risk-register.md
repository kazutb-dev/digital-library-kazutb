# Risk Register — KazUTB Digital Library Platform

> **Version:** 1.0
> **Date:** 2026-05-13
> **Project:** KazUTB Digital Library (`kazutb-dev/digital-library-kazutb`)
> **Owner:** (your name)
> **Review cycle:** Weekly during baseline through experimental QA program layers
> **Scale:** Business Impact 1–5 (5=critical), Failure Probability 1–5 (5=almost certain)
> **Risk Score = Impact × Probability**

---

## P0 — CRITICAL RISKS (Score ≥ 15)

| ID | Module / Component | Business Impact | Failure Probability | Risk Score | Why Risky | Suggested Test Types | Priority | Status |
|----|-------------------|----------------|---------------------|------------|-----------|---------------------|---------|--------|
| R01 | **Auth & Session** — External CRM login bridge (`AuthController`, `EnsureAuthenticatedReader`) | 5 | 4 | **20** | CRM is the only auth provider; bridge failure = 100% user lockout; session replay risk; no local fallback | Unit (auth service), Integration (login/logout/session), Security (rate limiting via `throttle:login`, brute force, session fixation) | **P0** | Open |
| R02 | **Circulation State Machine** — Checkout/Return/Renew (`CirculationLoanWriteService`, `InternalCirculationController`) | 5 | 3 | **15** | State transitions (available→issued→returned) must be atomic; double-checkout leaves ghost loans; return of non-existing loan → 500; audit trail (`CirculationAuditEvent`) incomplete on failure | Integration, State machine boundary, Negative (double checkout, invalid loan ID, return non-issued copy), E2E | **P0** | Open |
| R03 | **RBAC Middleware Enforcement** — 8 guards (`EnsureAdminStaff`, `EnsureLibrarianStaff`, `EnsureMemberReader`, `EnsureInternalCirculationStaff`, `EnsureIntegrationBoundary`, etc.) | 5 | 3 | **15** | Any guard bypass allows privilege escalation; 8 distinct guards = 8 bypass surfaces; cross-role contamination (member accessing librarian routes) | Integration (unauthorized access per route group), Negative (wrong role, unauthenticated, expired session), Boundary | **P0** | Open |
| R04 | **Integration API Contracts** — `/integration/v1/*` (`ReservationReadController`, `ReservationMutateController`, `DocumentManagementController`) | 4 | 4 | **16** | External systems depend on stable JSON contracts; idempotency key drift; rate limiting (`throttle:integration`, `throttle:integration-mutate`); HMAC/token validation; response schema changes break consumers | Contract tests, Integration (valid/invalid token), Negative (duplicate requests, rate limit exceeded), Schema validation | **P0** | Open |
| R07 | **Data Integrity** — Legacy migration + null safety (`ReadOnlyPgsqlModel`, 19 migrations) | 4 | 4 | **16** | 19 migrations on evolving schema; legacy MARC-SQL data with known errors (NULL ISBNs, empty titles, bad relations); `ReadOnlyPgsqlModel` means some data is uncontrolled; null dereference in service layer | Migration tests, DB constraint verification, Unit (null safety in services), Integration (handle null/empty catalog fields) | **P0** | Open |

---

## P1 — HIGH RISKS (Score 10–14)

| ID | Module / Component | Business Impact | Failure Probability | Risk Score | Why Risky | Suggested Test Types | Priority | Status |
|----|-------------------|----------------|---------------------|------------|-----------|---------------------|---------|--------|
| R05 | **Catalog Search & Filter** — `CatalogReadService`, `CatalogController`, `/v1/catalog-db` | 4 | 3 | **12** | Core member-facing feature; SQL injection via search params; UDC subject filter logic complexity; language filter (`CatalogDbLanguageFilterTest`); empty result handling; large result sets performance | Unit (service), API (params/filters), E2E (search flow), Boundary (XSS chars, empty query, 10k results) | **P1** | Open |
| R06 | **Reservation Workflow** — `ReaderReservationService`, `AccountController` | 4 | 3 | **12** | Queue position logic; duplicate reservation prevention; cancellation atomicity; max reservation limit enforcement; race condition (two users reserve last copy simultaneously) | Integration, State machine (create→approve→cancel), Boundary (at-limit, concurrent), E2E | **P1** | Open |
| R08 | **Digital Materials Streaming** — `DigitalMaterialService`, `DigitalMaterialController`, `/v1/digital-materials/{id}/stream` | 3 | 4 | **12** | File path resolution may expose server paths; unauthorized access to PDFs without auth check; broken stream on missing file; MIME type handling | Security (unauthenticated access attempt), Integration (valid/missing file), Boundary (large file, corrupt file) | **P1** | Open |
| R11 | **Error Handling & Logging** — `CirculationWriteException`, `InternalCopyWriteException`, `InternalDocumentReviewException`, `IntegrationDocumentManagementException` | 3 | 4 | **12** | Custom exception classes must return correct HTTP codes (422/409/500) not expose stack traces; unhandled exceptions in production = 500 with debug info | Unit (exception classes), Integration (trigger each exception type), E2E (error pages render correctly) | **P1** | Open |
| R12 | **Performance Hotspots** — Catalog search, SPA cold load, circulation queries | 3 | 4 | **12** | No performance baseline exists; PostgreSQL full-text search without confirmed indexes; SPA JS bundle size unknown; no CDN; concurrent user load unknown | Load test (k6), DB EXPLAIN ANALYZE, Frontend bundle analysis (`vite build --analyze`) | **P1** | Open |
| R10 | **Admin Panel Mutations** — `AdminUserManagementService`, `AdminIntegrationService`, role assignment | 4 | 2 | **8** | Role assignment errors cause privilege escalation; admin operations must be atomic; audit logging required | Integration (role assignment/removal), Negative (invalid role, self-demotion), E2E (admin CRUD) | **P1** | Open |

---

## P2 — MEDIUM RISKS (Score < 10)

| ID | Module / Component | Business Impact | Failure Probability | Risk Score | Why Risky | Suggested Test Types | Priority | Status |
|----|-------------------|----------------|---------------------|------------|-----------|---------------------|---------|--------|
| R09 | **Shortlist** — Session-based (`ShortlistController`, `ShortlistStorageService`) | 3 | 3 | **9** | Session-based without login = data loss on session expire; export format correctness; external resource shortlist entry | Unit, API (add/remove/export/check), E2E (member flow), Boundary (max items, session reset) | **P2** | Open |
| R13 | **ISBN Enrichment** — `IsbnService`, `CatalogEnrichmentService`, `InternalEnrichmentController` | 3 | 3 | **9** | ISBN validation edge cases; external enrichment API unreachable; bulk-validate timeout; partial enrichment leaving inconsistent state | Unit (ISBN parsing), Integration (mock external), Boundary (invalid ISBN, bulk 1000 items) | **P2** | Open |
| R14 | **Scientific Repository** — `ScientificRepositoryService`, `ScientificWork` model | 3 | 3 | **9** | File upload security (MIME type validation, size limits); metadata completeness; access control for thesis/dissertations | Integration (upload workflow), Security (malicious file upload), Boundary (large file) | **P2** | Open |
| R16 | **AI Assistant** — `TwentyFirstBridgeService`, `InternalAiAssistantController` | 2 | 4 | **8** | External API dependency; token expiry (1h); session/thread management; no fallback on API failure | Integration (with mock), Error handling (API timeout/500), Boundary (token expiry) | **P2** | Open |
| R15 | **Notification System** — `LibraryNotificationService`, `LibraryNotificationFeedService` | 2 | 3 | **6** | Notification delivery failures; duplicate notifications; incorrect recipient mapping | Unit, Integration (delivery), Boundary (duplicate trigger) | **P2** | Open |
| R17 | **News & Events** — `AdminNewsManagementService`, `LibraryNews` model | 2 | 2 | **4** | Low-risk CRUD; publication/unpublication logic; date ordering | Unit, E2E (admin create/publish/unpublish) | **P2** | Open |

---

## Risk Summary Dashboard

| Priority | Count | Total Risk Score | Avg Score |
|---------|-------|-----------------|-----------|
| P0 | 5 | 82 | 16.4 |
| P1 | 6 | 56 | 9.3 |
| P2 | 6 | 44 | 7.3 |
| **Total** | **17** | **182** | **10.7** |

---

## Risk Revision Log

| Date | ID | Change | Changed By |
|------|----|--------|-----------|
| 2026-05-13 | ALL | Initial version created from repository audit | (your name) |
| | | | |

---

## How to Use This Register

1. **Review weekly** — update status (Open/Mitigated/Closed) as tests are written and executed
2. **Re-score** — adjust Impact/Probability after defects are found or fixed
3. **Link to test cases** — add test case IDs in the Status column when tests exist
4. **Add new risks** — append rows as new areas are discovered during testing
5. **Track in research paper** — use "P0 defects found per week" as key metric M5
