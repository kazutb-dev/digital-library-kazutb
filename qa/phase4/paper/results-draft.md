# Results Draft

## 1. Reporting Scope

This section reports repository-measured outcomes from phase2, Intermediate Empirical Review, and phase3. Interpretation is intentionally limited; deeper analytical framing is reserved for the Discussion section.

## 2. Phase2 Results Snapshot (Automation and Governance)

Primary evidence sources:

1. qa/phase2/metrics/phase2-automation-coverage.csv
2. qa/phase2/metrics/phase2-execution-time.csv
3. qa/phase2/metrics/phase2-defects-vs-risk.csv
4. qa/phase2/metrics/phase2-quality-gate-results.csv
5. qa/phase2/docs/phase2-metrics-report.md

Measured outcomes:

1. weighted high-risk automation coverage: 75.0% (27/36 checks),
2. module-level automation presence: 7/7 modules,
3. observed defects/issues: 9,
4. current-enforced quality-gate distribution: pass=5, fail=3, warn=1,
5. combined measured automation runtime: 154.578 seconds.

[figure reference placeholder: F2]

## 3. Intermediate Empirical Review Results Snapshot (Reassessment Layer)

Primary evidence sources:

1. qa/midterm/metrics/midterm-required-metrics.csv
2. qa/midterm/metrics/midterm-quality-gate-evaluation.csv
3. qa/midterm/docs/midterm-final-report.md

Measured outcomes:

1. weighted high-risk check coverage remained 75.0% (27/36),
2. high-risk module automation presence remained 100% (7/7),
3. observed defects/issues baseline for reassessment: 9,
4. Intermediate Empirical Review added tests execution: 8/8 passed,
5. Intermediate Empirical Review added run time reported as 24.15 seconds.

[figure reference placeholder: F3]

## 4. Phase3 Experimental Results Snapshot

### 4.1 Performance

Primary evidence sources:

1. qa/phase3/metrics/phase3-performance-results.csv
2. qa/phase3/docs/phase3-metrics-report.md

Measured outcomes:

1. scenarios executed: 9,
2. scenarios passed: 8,
3. scenarios failed: 1 (integration boundary threshold failure),
4. endpoint latency profile concentrated around approximately 3.1s to 3.8s in reported averages,
5. integration boundary scenario exceeded configured threshold.

[figure reference placeholder: F4]

### 4.2 Mutation

Primary evidence sources:

1. qa/phase3/metrics/phase3-mutation-score.csv
2. qa/phase3/metrics/phase3-mutation-results.csv

Measured outcomes:

1. mutants executed: 14,
2. killed mutants: 12,
3. surviving mutants: 2,
4. overall mutation score: 85.71%.

[figure reference placeholder: F5]

### 4.3 Chaos/Resilience

Primary evidence sources:

1. qa/phase3/metrics/phase3-chaos-metrics.csv
2. qa/phase3/metrics/phase3-chaos-results.csv

Measured outcomes:

1. chaos scenarios executed: 4,
2. overall fault-phase availability: 50.00%,
3. overall recovery-phase availability: 100.00%,
4. mean MTTR proxy: 3391.02 ms,
5. cascading failures observed: 0.

[figure reference placeholder: F6]

## 5. Cross-Phase Synthesis Indicator

Primary evidence sources:

1. qa/phase3/metrics/phase3-observed-vs-expected.csv
2. qa/paper-assets/figures/summary/summary_quality_progression_source.csv
3. qa/paper-assets/figures/summary/summary_quality_progression_across_phases.png

Reported output:
A phase-level progression indicator is available in the summary figure source and rendered figure artifacts.

[figure reference placeholder: F7]

## 6. Results Evidence Notes

1. All values above are taken from repository artifacts and not estimated.
2. Bounded synthetic and local-context limitations are documented in phase3 methodology/report files.
3. Any theoretical interpretation of these outputs should be treated as discussion material, not raw results.

[table reference placeholder: T2]

---

KazUTB Digital Library - research synthesis layer (Phase 4) Part 2
