# Discussion Draft

## 1. Interpretation Frame

The results indicate that QA maturity in this repository increased through layered evidence rather than through a single automation expansion step. The sequence from phase1 to phase3 shows incremental strengthening of empirical confidence, while preserving visible unresolved risks.

## 2. Why the Risk Model Was Useful

The phase1 risk baseline was useful because it provided a stable prioritization lens that remained testable in later phases. Multiple high-priority concerns identified early were repeatedly visible in phase2 and phase3 outputs, especially in integration-boundary related evidence. This cross-phase persistence supports the practical utility of risk-first planning in this case.

Evidence links:

1. qa/phase1/docs/phase1-risk-register.md
2. qa/phase2/metrics/phase2-defects-vs-risk.csv
3. qa/phase3/metrics/phase3-performance-results.csv
4. qa/phase3/docs/phase3-final-summary.md

## 3. Where the Risk Model Was Validated

The model was validated where early high-risk modules aligned with observed defects, governance failures, and later experimental stress points. In particular:

1. gate failures in phase2 confirmed unresolved high-risk areas,
2. Intermediate Empirical Review reassessment preserved elevated risk concentration,
3. phase3 experiments did not remove integration-related concerns.

This pattern suggests that risk-prioritization was directionally correct, even when mitigation depth was incomplete.

## 4. Where the Risk Model Was Weak

The model was weaker in two areas:

1. it did not fully predict the persistence of assertion-depth gaps revealed by mutation survivors,
2. it did not include strong repeated-run stability quantification at Intermediate Empirical Review due limited historical data.

These are not contradictions of the model, but evidence that risk frameworks require iterative calibration with richer empirical datasets.

## 5. What Experiments Added Beyond Basic Automation

Phase3 experiments expanded insight beyond pass/fail automation status:

1. performance testing exposed latency concentration and integration threshold failure,
2. mutation testing measured test sensitivity, not just execution completion,
3. chaos testing measured recovery/propagation behavior under bounded faults.

Together, these experiments added explanatory depth to QA maturity claims that phase2 gate metrics alone could not provide.

## 6. Trade-Offs and Practical Implications

Key trade-offs observed in this case:

1. broad automation presence can coexist with unresolved high-impact defects,
2. bounded/local test execution increases reproducibility but limits external generalization,
3. strict evidence discipline reduces overclaiming but leaves some narrative claims intentionally conservative.

Practical implication:
A layered QA program should treat automation coverage, gate outcomes, and experimental evidence as complementary signals rather than interchangeable metrics.

## 7. Limitations and Threats to Validity

### Internal validity considerations

1. Some metrics originate from bounded synthetic scenarios.
2. Certain baseline metrics remained blocked in early phase tooling conditions.
3. Not all modules received equal depth of experimental probing.

### External validity considerations

1. Sequential local-load performance outputs should not be interpreted as production capacity estimates.
2. Synthetic chaos scenarios are not equivalent to production-scale fault environments.
3. Case-study context may not transfer directly to architectures with different operational profiles.

### Construct validity considerations

1. Composite summary indicators simplify multidimensional quality behavior.
2. Gate distributions capture governance states but not total quality behavior.

Methodological framing of these validity dimensions should be backed with external empirical SE references [citation needed] [external reference required].

## 8. Reproducibility Considerations

Reproducibility is relatively strong in this repository because:

1. metrics are stored as structured CSV/JSON artifacts,
2. traceability matrices map requirements to evidence,
3. figure assets are mapped to source datasets,
4. part-level summaries preserve scope boundaries.

However, reproducibility remains environment-sensitive for timing-related measurements.

[figure reference placeholder: F7]
[table reference placeholder: T3]

---

KazUTB Digital Library - research synthesis layer (Phase 4) Part 2
