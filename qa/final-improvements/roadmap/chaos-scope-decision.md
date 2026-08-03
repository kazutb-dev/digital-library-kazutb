# Chaos / Resilience Scope Decision

**Repository:** kazutb-dev/digital-library-kazutb  
**Date:** 2026-05-14  
**Decision:** EXCLUDED from new evidence

---

## Assessment

The application runs in a Docker Compose single-instance environment
(`kazutb-library-app-1`, `kazutb-library-postgres-1`, `kazutb-library-frontend-dev-1`).

Evaluated fault injection approaches against safety criteria:

| Approach                            | Safe                 | Reversible  | No Workspace Risk | Clear Evidence | Environment Fit                          |
| ----------------------------------- | -------------------- | ----------- | ----------------- | -------------- | ---------------------------------------- |
| Stop postgres container temporarily | ✅                   | ✅          | ✅                | ⚠️ weak        | ⚠️ disrupts all concurrent tests         |
| Network delay via `tc netem`        | ✅                   | ✅          | ✅                | ⚠️ weak        | ❌ requires Linux net tools in container |
| DB connection pool exhaustion       | ⚠️ risky             | ⚠️ may hang | ⚠️                | ⚠️             | ❌ hard to bound                         |
| Kill app container                  | ❌ destroys test env | ❌          | ❌                | ✅             | ❌                                       |

**None of the evaluated approaches meet all 5 required criteria simultaneously.**

The single-instance architecture means any meaningful fault injection disrupts
the only available test environment, risking uncleaned state and no concurrent
baseline to compare against.

---

## Decision

**D5 Chaos: EXCLUDED from new evidence for this campaign.**

This decision is final unless Docker is restarted and a bounded, isolated
chaos experiment can be safely scoped — which is not the case in the current
local-Windows environment where Docker Desktop was stopped.

---

## Paper Placement

Chaos/resilience evidence from this campaign must appear **only** in:

1. **Threats to Validity (Construct Validity):**  
   "Resilience testing was not performed in this study. The single-instance
   Docker Compose environment used for experimentation does not support safe,
   bounded fault injection without disrupting the test environment itself."

2. **Limitations:**  
   "No chaos engineering or fault injection experiments were executed. Resilience
   claims are therefore absent from the experimental results."

3. **Future Work:**  
   "A bounded chaos engineering phase using a dedicated multi-instance staging
   environment would allow testing of recovery behavior, MTTR proxies, and
   cascading failure containment."

4. **Prior evidence note** (if applicable):  
   Any chaos observations from prior campaign sessions must be labeled as
   "environmental observation only — not controlled experiment" and must NOT
   appear in the results tables.

---

## Table/Figure Status

- `T10 Chaos/Resilience Summary Table`: **EXCLUDED from main results**
- `F10 Chaos figure`: **EXCLUDED from main results**
- Both may appear in a "Limitations and Future Work" appendix with explicit
  "Not executed" labeling.
