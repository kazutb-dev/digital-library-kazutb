# Defense Preparation Questions

Date: 2026-05-13

## A. Architecture Questions

1. How does the repository architecture justify a layered QA strategy instead of a single test layer?
2. Which architectural boundaries most influenced risk prioritization?
3. Why did integration boundary concerns persist across phases?

## B. QA Strategy Questions

1. Why was risk-based prioritization chosen as the initial framework?
2. How did the team validate that risk prioritization remained relevant over time?
3. What changed between phase1 planning and phase3 experimental evidence?

## C. Risk-Analysis Questions

1. Which risk assumptions were confirmed empirically?
2. Which assumptions were weakened by later evidence?
3. How was risk reassessment operationalized during Intermediate Empirical Review?

## D. Automation Questions

1. Why is 100% module presence not equivalent to full automation readiness?
2. What explains the gap between automation breadth and defect persistence?
3. Which automated checks were most informative for decision making?

## E. CI/CD Questions

1. How did quality gates influence the interpretation of system readiness?
2. What are the limits of gate outcomes as quality proxies?
3. Which gate failures were most consequential?

## F. Metrics Questions

1. Why were weighted coverage metrics used instead of only module presence?
2. Which metrics are strongest for defense and why?
3. Which metrics are weakest and how are they caveated?

## G. Mutation Questions

1. What does a mutation score of 85.71% indicate in this repository context?
2. How should surviving mutants be interpreted without overclaiming?
3. What follow-up actions are justified by mutation survivors?

## H. Performance Questions

1. Why are phase3 performance results framed as bounded baseline evidence?
2. How should the integration threshold failure be interpreted?
3. What additional tests are needed before production-level claims?

## I. Chaos/Resilience Questions

1. What can and cannot be concluded from bounded synthetic chaos scenarios?
2. Why does 100% recovery availability not imply complete resilience?
3. How was cascading failure assessed in the available scope?

## J. Reproducibility Questions

1. Which artifacts make this case study reproducible?
2. What environment constraints limit replication fidelity?
3. How are evidence-path traceability and claims governance enforced?

## K. Criticism Scenarios

1. "Your findings are too repository-specific; why should anyone care?"
2. "The literature citations are incomplete; how can claims be trusted?"
3. "Bounded synthetic tests cannot support resilience claims."
4. "Your final insight is strong rhetoric but weakly novel."

---

KazUTB Digital Library - research synthesis layer (Phase 4) Part 3
