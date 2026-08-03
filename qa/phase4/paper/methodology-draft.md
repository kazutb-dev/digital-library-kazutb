# Methodology Draft

## 1. Methodological Framing

This study uses an evolving empirical QA case-study design over sequential QA program layers in a single repository. The intent is not to claim universal benchmark performance, but to analyze how structured QA evidence matured across four stages:

1. baseline risk and strategy definition,
2. automation and governance formalization,
3. empirical reassessment,
4. experiment-backed reliability and resilience synthesis.

The pipeline is grounded in repository artifacts and explicit traceability records (qa/phase1/TRACEABILITY.md; qa/phase2/TRACEABILITY.md; qa/midterm/TRACEABILITY.md; qa/phase3/TRACEABILITY.md).

## 2. baseline risk-based QA strategy (Phase 1): Risk Strategy Foundation

Phase1 defined the initial risk model and testing strategy, including module-level risk priorities and test-level intent (qa/phase1/docs/phase1-risk-register.md; qa/phase1/docs/phase1-test-strategy.md).

Methodological contribution of phase1:

1. established risk-ranked target surface,
2. defined test-level layering intent,
3. recorded baseline metrics and blocked-metric policy for evidence discipline (qa/phase1/docs/phase1-baseline-metrics-methodology.md).

This phase created the decision baseline that later phases could validate, adjust, or challenge.

## 3. automation and quality governance layer (Phase 2): Automation and Quality-Gate Governance

Phase2 operationalized the strategy by adding measurable automation coverage, runtime metrics, defects-vs-risk analysis, and quality-gate enforcement artifacts (qa/phase2/docs/phase2-metrics-report.md; qa/phase2/metrics/phase2-automation-coverage.csv; qa/phase2/metrics/phase2-quality-gate-results.csv).

Methodological role of phase2:

1. moved from strategy to measured enforcement,
2. connected test execution with gate outcomes,
3. produced governance evidence suitable for reproducible review.

This stage provided a necessary control layer, but still left unresolved blockers and depth gaps, which became inputs for reassessment.

## 4. Intermediate Empirical Review Layer: Empirical Reassessment

The Intermediate Empirical Review layer re-scored risk assumptions and examined where phase2 evidence supported or contradicted prior expectations (qa/midterm/docs/midterm-methodology.md; qa/midterm/docs/midterm-final-report.md; qa/midterm/metrics/midterm-required-metrics.csv).

Methodological role of Intermediate Empirical Review:

1. convert static planning assumptions into evidence-informed updates,
2. identify weak spots such as insufficient repeated-run stability evidence,
3. guide where deeper experiments were justified.

This reassessment stage is critical because it prevents linear overconfidence and preserves adaptive methodological behavior.

## 5. experimental evaluation layer (Phase 3): Experimental Evidence Layer

Phase3 introduced three complementary experimental lenses:

1. performance baseline under bounded synthetic sequential load,
2. mutation sensitivity for test robustness analysis,
3. chaos-style bounded fault injection for recovery/propagation behavior.

Key sources: qa/phase3/docs/phase3-performance-methodology.md; qa/phase3/docs/phase3-experimental-setup.md; qa/phase3/docs/phase3-experimental-final-report.md.

Methodological rationale:

1. performance reveals latency and bottleneck behavior,
2. mutation reveals assertion sensitivity limits,
3. chaos reveals recovery and isolation behavior beyond nominal-path automation.

## 6. Risk-Based Selection and Multi-Layer Coverage Logic

Selection logic remained risk-prioritized across phases. High-risk modules and boundary workflows received disproportionate evidence attention through API, integration, and experimental checks. This allowed cross-phase consistency in evaluating whether major risk claims were strengthening or persisting.

Representative cross-phase hotspot example:
integration boundary concerns appeared in phase2 defect/gate evidence and remained visible in phase3 performance and mutation outcomes (qa/phase2/metrics/phase2-defects-vs-risk.csv; qa/phase3/metrics/phase3-performance-results.csv; qa/phase3/metrics/phase3-mutation-score.csv).

## 7. Evidence Collection and Metrics Strategy

Evidence collection followed artifact discipline:

1. structured CSV/JSON metrics packages,
2. traceability and report documents,
3. command/log evidence for reproducibility,
4. paper-ready figure assets mapped to datasets.

Core metrics families included:

1. coverage/governance metrics (phase2),
2. reassessment metrics (Intermediate Empirical Review),
3. experimental metrics (phase3 performance/mutation/chaos).

Mapped figure assets are documented in qa/paper-assets/figures/figure-index.md and qa/phase4/paper/figures-and-tables-map.md.

## 8. Role of CI/CD and Governance

CI/CD quality gates functioned as operational governance controls in phase2 and informed later interpretation layers. In this methodology, CI artifacts are treated as evidence sources rather than as complete proxies for system quality. This distinction is necessary because gate pass/fail outcomes coexist with unresolved module-level risks.

## 9. Constraints and Threats-to-Validity Considerations

Methodological constraints to retain in interpretation:

1. bounded synthetic execution contexts in performance and chaos studies,
2. local environment dependency for some measurements,
3. incomplete repeated-run data for quantitative flaky-rate confirmation,
4. blocked metrics in early phase contexts.

These are not hidden weaknesses; they are explicit boundaries on claim scope and external generalization.

Formal linkage to external methodological standards requires literature support [citation needed] [external reference required].

[figure reference placeholder: F1, F7]
[table reference placeholder: T1]

---

KazUTB Digital Library - Research Synthesis Layer (Phase 4) Part 2
