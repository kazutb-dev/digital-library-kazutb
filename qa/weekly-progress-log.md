# Weekly QA Progress Log — KazUTB Digital Library Platform

> **Project:** `kazutb-dev/digital-library-kazutb`
> **Owner:** (your name)
> **Start Date:** 2026-05-13
> **Format:** One entry per week with tasks, results, blockers, and metric snapshots

---

## Log Template (copy per week)

```
### Week N — Date Range
**Assignment:** A1/A2/A3/Paper
**Focus:** [main goal this week]

#### Tasks Completed
- [ ] Task 1
- [ ] Task 2

#### Artifacts Created/Updated
- File: description

#### Metrics Snapshot
| Metric | Previous | This Week | Δ |
|--------|---------|-----------|---|
| PHP test files | | | |
| Coverage % | | | |
| Risk items covered | | | |
| CI pass rate | | | |
| Defects found | | | |

#### Defects Found
| ID | Description | Severity | Status |
|----|------------|---------|--------|

#### Blockers / Risks
- Blocker 1
- Risk 1

#### Next Week Plan
- [ ] Next task 1
- [ ] Next task 2

#### Notes / Observations
> Free text observations during testing
```

---

## Week 2 — 2026-05-13 to 2026-05-19

**Assignment:** A1 (Risk-Based QA)
**Focus:** Environment setup, baseline capture, risk register, manual test cases

### Tasks Completed

- [x] Full repository audit completed
- [x] QA folder structure created (`C:\dev\kazutb-library\qa\`)
- [x] Full audit report written (`qa/docs/00-full-audit-report.md`)
- [x] Risk register drafted (`qa/docs/risk-register.md`) — 17 risks, 5 P0
- [x] Test strategy v0.1 written (`qa/docs/qa-test-strategy.md`)
- [x] Environment setup report created (`qa/docs/qa-environment-setup-report.md`)
- [x] Baseline metrics CSV created (`qa/metrics/baseline-metrics.csv`)
- [ ] Run `composer test` and capture actual results
- [ ] Run `npm run test:e2e` and capture actual results
- [ ] Generate PHPUnit coverage report
- [ ] Write 20+ manual test cases for P0 risks
- [ ] Document 3+ defects/gaps found during exploration

### Artifacts Created/Updated

- `qa/README.md` — QA workspace index
- `qa/docs/00-full-audit-report.md` — Full audit (47KB)
- `qa/docs/risk-register.md` — 17-item risk register
- `qa/docs/qa-test-strategy.md` — Test strategy v0.1
- `qa/docs/qa-environment-setup-report.md` — Setup guide
- `qa/metrics/baseline-metrics.csv` — Metrics baseline
- `qa/weekly-progress-log.md` — This file

### Metrics Snapshot

| Metric                | Baseline | Week 2      | Δ   |
| --------------------- | -------- | ----------- | --- |
| PHP test files        | —        | ~112        | New |
| Coverage %            | —        | ~30% (est.) | New |
| Risk items identified | —        | 17          | New |
| CI jobs passing       | —        | 3           | New |
| Defects documented    | —        | TBD         | —   |

### Defects Found

| ID        | Description | Severity | Status |
| --------- | ----------- | -------- | ------ |
| (fill in) |             |          |        |

### Blockers / Risks

- Demo auth setup not validated yet (blocks E2E)
- Coverage actual % not yet measured (needs clover report)
- PostgreSQL Docker not confirmed running

### Next Week Plan

- [ ] Execute all test commands and fill in Environment Setup Report
- [ ] Write 20+ manual test cases for: Auth (5), RBAC (5), Circulation (5), Catalog (5)
- [ ] Generate actual PHPUnit coverage report
- [ ] Document gaps found during manual exploratory testing
- [ ] Update risk register with actual findings

### Notes / Observations

> Initial audit completed. System is well-structured with strong test coverage for a project at this stage.
> Key gaps: no rich test data factories, low coverage threshold (4%), no performance testing.
> The `ReadOnlyPgsqlModel` pattern is unusual — need to understand test data approach for external read-only DB.

---

## Week 3 — [Fill in dates]

**Assignment:** A1 (continuing) / A2 prep
**Focus:** Manual test case execution, gap filling, automation prep

### Tasks Completed

- [ ] All manual test cases written and reviewed
- [ ] Environment validation 100% complete
- [ ] Coverage report generated and documented
- [ ] Risk register updated with findings
- [ ] A1 artifacts finalized for submission

### Metrics Snapshot

| Metric             | Week 2 | Week 3 | Δ   |
| ------------------ | ------ | ------ | --- |
| PHP test files     | 112    |        |     |
| Coverage %         | 30%    |        |     |
| Risk items covered | 35%    |        |     |
| Defects found      | TBD    |        |     |

---

## Week 4 — [Fill in dates]

**Assignment:** A2 (Automation Baseline)
**Focus:** Factory creation, new automated tests, CI gate updates

### Tasks Planned

- [ ] Create DocumentFactory + ReaderFactory
- [ ] Expand API tests for digital materials + shortlist export
- [ ] Add Playwright tests for librarian circulation flow
- [ ] Raise coverage threshold to 40%
- [ ] Add Infection to CI gate
- [ ] Update ci.yml with new thresholds

---

## Week 5 — [Fill in dates]

**Assignment:** Midterm Checkpoint
**Focus:** Metrics review, research paper methodology section

### Tasks Planned

- [ ] Export all metrics (M1–M12) to CSV
- [ ] Generate coverage trend chart
- [ ] Review CI history (pass rate, flaky tests)
- [ ] Write interim paper section (methodology + early results)
- [ ] Define 2–3 performance hypotheses for A3

---

## Week 6 — [Fill in dates]

**Assignment:** A3 prep + research paper
**Focus:** k6 setup, performance baseline, experimental design

### Tasks Planned

- [ ] Install k6 and write 3 load test scripts
- [ ] Capture baseline performance at 1/5/10 VU
- [ ] Document test environment specs for paper
- [ ] Write hypothesis for performance experiment

---

## Week 7 — [Fill in dates]

**Assignment:** A3 (Performance & Experimental) + Final Paper
**Focus:** Load experiments, charts, research paper finalization

### Tasks Planned

- [ ] Run full load test scenarios
- [ ] Generate performance charts (response time curves, error rate vs load)
- [ ] Populate all metrics M1–M12 in baseline-metrics.csv
- [ ] Write and review research paper draft
- [ ] Prepare final presentation materials

---

## Summary Table (Updated Weekly)

| Week | Assignment | Test Files | Coverage % | Defects Found | CI Pass Rate | Notes         |
| ---- | ---------- | ---------- | ---------- | ------------- | ------------ | ------------- |
| W2   | A1         | 112        | ~30% (est) | TBD           | TBD          | Initial audit |
| W3   | A1         |            |            |               |              |               |
| W4   | A2         |            |            |               |              |               |
| W5   | Midterm    |            |            |               |              |               |
| W6   | A3 prep    |            |            |               |              |               |
| W7   | A3 + Paper |            |            |               |              |               |
