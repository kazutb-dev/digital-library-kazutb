# Mutation Testing Scope Decision

**Repository:** kazutb-dev/digital-library-kazutb  
**Date:** 2026-05-14  
**Decision:** No new tool execution; retained baseline + assertion-strengthening evidence

---

## Tool Availability Check

**Infection PHP** (the canonical PHP mutation testing framework) is NOT present
in `vendor/` and is NOT listed in `composer.json` (dev or regular dependencies).

Installing Infection PHP for this campaign would require:

```
composer require --dev infection/infection
```

This is NOT being done per the confirmed campaign decision: "Do NOT install new
mutation tooling for this campaign."

---

## Decision

**M9 Mutation: Retained prior baseline + assertion-density strengthening evidence only.**

No new mutation tool execution will occur in this campaign.

---

## Evidence That WILL Be Reported

### Layer 1: Retained Prior Baseline

Whatever mutation analysis was performed in prior QA phases is retained as the
baseline reference. Exact figures must be taken from the prior mutation summary
table (see `qa/final-improvements/tables/mutation-summary-table.md`).

If prior mutation data is itself inferred (not from a tool run), that caveat
propagates forward — no laundering of inferred data as tool-executed data.

### Layer 2: Assertion-Density Strengthening

This campaign will strengthen test assertions as part of Phase B and C work.
The resulting assertion count increase per module is verifiable from JUnit XML
output and constitutes indirect evidence of improved mutation resistance:

- Higher assertion-per-test ratio reduces the probability that surviving mutants
  go undetected
- Field-by-field response assertions catch value-mutation survivors
- State-verification assertions catch behavioral-mutation survivors

This will be reported as:

> "Assertion density was increased across high-risk test suites (auth: +N,
> reservation: +N, integration: +N) in this campaign. No new mutation tool
> execution was performed; mutation resistance improvement is evidenced
> indirectly by assertion strengthening."

---

## Paper Guidance

### If Prior Baseline Exists From Tool Run

Report in T8 as:

- Prior tool-run results in "Prior Baseline" column
- Phase B/C assertion count delta in "Assertion Strengthening Evidence" column
- "New Tool Execution Status" = "NOT EXECUTED — out of scope for this campaign"

### If Prior Baseline Is Also Inferred

Acknowledge in the paper:

> "Mutation testing was not performed in this study. Indirect evidence of
> mutation resistance improvement is provided through assertion density
> strengthening in enhanced test suites. Future work includes executing
> Infection PHP against the full test suite."

### Threats to Validity (Conclusion Validity)

> "The absence of mutation testing limits the confidence with which assertion
> coverage improvements can be attributed to genuine defect detection capacity
> improvements. This is identified as a limitation of the current study."

---

## Table/Figure Status

- `T8 Mutation Summary Table`: Include with explicit "NOT EXECUTED" in tool
  column; show assertion strengthening deltas as surrogate evidence
- `F9 Mutation chart`: If included, must be labeled "Assertion Density Proxy —
  No mutation tool executed" and show assertion counts, NOT mutation scores
