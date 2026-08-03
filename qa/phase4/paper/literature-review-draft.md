# Literature Review Draft

## 1. Scope of Literature Positioning

This section positions the repository-backed QA approach against established research and industrial practices. It is intentionally structured as a draft scaffold; external references are not fabricated and are marked where required.

## 2. Risk-Based Testing

Risk-based testing prioritizes testing effort according to impact and likelihood so that limited QA capacity targets the most consequential failures first [citation needed] [external reference required].

In this repository, the risk-first baseline appears in phase1 risk and strategy artifacts, where high-priority modules were identified before deeper automation and experimentation (qa/phase1/docs/phase1-risk-register.md; qa/phase1/docs/phase1-test-strategy.md). This is consistent with risk-oriented QA sequencing, but formal theoretical anchoring still requires external literature support [citation needed] [external reference required].

## 3. Layered QA Strategy

Layered QA generally combines unit, integration, API, UI/E2E, and non-functional evidence so that different defect classes are observed at different abstraction levels [citation needed] [external reference required].

The repository evidence reflects this layering trajectory:

1. phase1 established baseline scope and constraints,
2. phase2 expanded automation and governance,
3. Intermediate Empirical Review reassessed evidence quality,
4. phase3 added performance, mutation, and chaos perspectives.

The methodological value of layered evidence triangulation requires explicit empirical software engineering references [citation needed] [external reference required].

## 4. Automation and CI/CD Quality Gates

Quality gates in CI/CD are typically used to enforce measurable acceptance criteria and block risky changes when thresholds are violated [citation needed] [external reference required].

Phase2 artifacts document this governance pattern in practical form via gate datasets and pass/fail/warn distributions (qa/phase2/metrics/phase2-quality-gate-results.csv; qa/phase2/TRACEABILITY.md). The observed outcome, where governance can be active even when release confidence remains limited, aligns with the distinction between control presence and quality maturity [citation needed] [external reference required].

## 5. Mutation Testing

Mutation testing is used to assess whether existing test suites can detect intentionally introduced behavioral perturbations [citation needed] [external reference required].

Phase3 mutation artifacts show strong but incomplete sensitivity, including surviving mutants in selected modules (qa/phase3/metrics/phase3-mutation-score.csv; qa/phase3/metrics/phase3-mutation-results.csv). Interpreting mutation score thresholds and survivor implications requires external methodological references [citation needed] [external reference required].

## 6. Performance Testing

Performance testing literature distinguishes between bounded baseline tests and production-like load modeling, including explicit external-validity caveats for local/sequential execution contexts [citation needed] [external reference required].

Phase3 performance artifacts explicitly disclose bounded synthetic methodology and resulting limits on generalization (qa/phase3/docs/phase3-performance-methodology.md; qa/phase3/metrics/phase3-performance-results.csv). The repository therefore provides a useful case for discussing baseline utility versus scalability inference boundaries.

## 7. Chaos Engineering and Resilience Testing

Chaos engineering and fault-injection approaches are used to evaluate system behavior under controlled disruption and to test assumptions about recovery and fault isolation [citation needed] [external reference required].

Phase3 chaos artifacts implement bounded synthetic scenarios with explicit disclosure, including recovery availability and propagation classification (qa/phase3/docs/phase3-chaos-test-plan.md; qa/phase3/metrics/phase3-chaos-metrics.csv). The conceptual distinction between bounded synthetic fault models and production-grade resilience validation should be grounded with external sources [citation needed] [external reference required].

## 8. Empirical Software Engineering and Case-Study Evidence

Empirical software engineering case studies emphasize context transparency, reproducibility paths, and threats-to-validity handling [citation needed] [external reference required].

The repository includes traceability, metrics datasets, and figure mapping artifacts that support this empirical framing (qa/phase3/TRACEABILITY.md; qa/paper-assets/figures/figure-index.md; qa/phase4/paper/claims-to-evidence-matrix.md). Final scholarly positioning still requires external citation completion in Part 2 revision cycles.

## 9. Literature Integration Gaps to Resolve

The following areas still require explicit external sources:

1. risk-based testing theory,
2. quality-gate governance models,
3. mutation score interpretation norms,
4. performance baseline validity framing,
5. chaos engineering methodology,
6. threats-to-validity standards in empirical SE case studies.

These gaps are tracked in qa/phase4/references/citation-needed-tracker.md.

---

KazUTB Digital Library - research synthesis layer (Phase 4) Part 2
