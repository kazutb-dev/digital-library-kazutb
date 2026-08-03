# Introduction Draft

## 1. Problem Context

Digital library systems in university environments combine public information access with role-restricted operational workflows. In the KazUTB Digital Library repository, this coexistence is visible through:

1. public-facing routes and content access paths,
2. member/account workflows,
3. librarian and administrative operations,
4. integration boundary endpoints,
5. circulation and reservation behaviors that require state consistency.

From a QA perspective, this creates a practical risk profile where defects are not uniformly distributed. Repository evidence from baseline QA layer (Phase 1) established that high-priority risks clustered around authentication/session behavior, catalog correctness, circulation reliability, API authorization, shortlist persistence, and coverage gaps (qa/phase1/docs/phase1-risk-register.md).

## 2. Why Risk-Based Multi-Layer QA Matters in This Case

The repository evidence indicates that a single-layer test strategy would be insufficient for this system. The project evolved toward a layered QA model because:

1. policy/guard logic required API and integration assertions,
2. user-visible regressions required UI and route-level validation,
3. workflow semantics required integration/feature coverage,
4. resilience and sensitivity questions required mutation and chaos-style experiments.

This direction was not hypothetical; it emerged empirically as automation and CI governance layer (Phase 2) quality-gate outcomes showed active failures despite measurable automation presence (qa/phase2/metrics/phase2-quality-gate-results.csv; qa/phase2/docs/phase2-metrics-report.md).

## 3. Case-Study Framing

This paper treats the repository as an evolving empirical QA case study rather than a controlled lab benchmark. The methodology and results are grounded in sequential project layers:

1. baseline risk-based QA strategy (Phase 1): risk baseline and testing strategy,
2. automation and quality governance layer (Phase 2): automation expansion and quality-gate governance,
3. Intermediate Empirical Review layer: reassessment and empirical correction,
4. experimental evaluation layer (Phase 3): performance, mutation, and chaos evidence.

The case-study framing is appropriate because the key observations were produced through repository-native artifacts, execution logs, metrics files, and traceability documents across phases (qa/phase1/TRACEABILITY.md; qa/phase2/TRACEABILITY.md; qa/midterm/TRACEABILITY.md; qa/phase3/TRACEABILITY.md).

## 4. Non-Trivial System Characteristics

The repository context is non-trivial in ways that matter for QA design and interpretation:

1. university-scale service expectations with mixed stakeholder workflows,
2. multi-role access boundaries and route protection requirements,
3. combination of public content routes and protected internal operations,
4. integration/API boundary correctness and failure-mode handling,
5. circulation/reservation behavior where inconsistent logic can create high user impact.

These characteristics align with the risk concentrations and later experimental findings, especially around integration boundary persistence as a cross-phase risk hotspot (qa/phase3/docs/phase3-final-summary.md).

## 5. Research Purpose and Contribution Direction

The purpose of this paper is to document and analyze how a risk-based, multi-layer QA process evolved in a real repository and what empirical value each phase added to decision quality. The intended contribution is twofold:

1. an evidence-traceable QA evolution model from baseline risk mapping to experiment-backed synthesis,
2. a disciplined claims-to-evidence approach that reduces unsupported conclusions in project-level research reporting.

Formal positioning against broader software QA literature is required in subsequent citation integration work [citation needed] [external reference required].

[figure reference placeholder: F1, F7]

---

KazUTB Digital Library - Research Synthesis Layer (Phase 4) Part 2
