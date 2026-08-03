# baseline QA layer (Phase 1) Test Strategy

**Author:** Murat Almas
**Date:** 2026-05-13

## 1. Scope

- **In scope:**
    - Core user flows: auth, catalog, shortlist, reservation, admin news
    - API endpoints for catalog, shortlist, auth
    - Role/permission boundaries
    - Environment/toolchain validation
    - Baseline metrics collection
- **Out of scope:**
    - Performance/load testing (future phase)
    - Full automation of all flows (future phase)
    - UI/UX exploratory (future phase)

## 2. Test Levels

- **Unit:** Core services, models (PHPUnit)
- **Integration:** Service-to-service, DB (PHPUnit)
- **API:** REST endpoints (Feature tests, Playwright)
- **E2E:** Full user flows (Playwright)
- **Non-functional:** Planned for future (performance, security)

## 3. Manual vs Automation (baseline QA layer (Phase 1))

- **Manual:** All high-risk flows, defect exploration, environment validation
- **Automation:** Existing PHPUnit, Playwright, composer validate
- **Split:** ~60% manual, 40% automation (baseline QA layer (Phase 1))

## 4. Entry/Exit Criteria

- **Entry:**
    - All tools installed and validated
    - Baseline metrics collected
    - 25+ manual test cases authored
- **Exit:**
    - All high-risk flows covered by at least one test
    - All critical defects logged and triaged
    - CI pipeline passes with no critical errors

## 5. Defect Severity/Priority Model

- **Severity:**
    - S1: System down/data loss
    - S2: Major function broken
    - S3: Minor function broken
    - S4: Cosmetic/typo
- **Priority:**
    - P0: Must fix before release
    - P1: Should fix soon
    - P2: Can defer

## 6. Quality Gates

- All high-risk modules have at least one mapped test
- CI pipeline must pass for merge
- No S1/S2 open defects at phase exit

## 7. Risk Mapping

- Test design and execution prioritized by risk register (P0/P1 first)
- Manual test cases and automation mapped to top risks

**This strategy will be refined in future phases as automation and coverage increase.**



