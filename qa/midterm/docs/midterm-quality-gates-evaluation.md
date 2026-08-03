# Intermediate Empirical Review Quality Gates Evaluation

## Gate set evaluated

| Gate ID | Metric | Threshold | Observed Result | Status | Strictness | Root cause |
| --- | --- | --- | --- | --- | --- | --- |
| MT-QG-001 | Critical regression pass rate | >=90% | API 70.00%, UI 90.91%, PHPUnit 83.78% | Fail | Appropriate | Product and contract defects in high-risk modules |
| MT-QG-002 | Critical public 5xx | 0 | 1 (/news) | Fail | Appropriate | Public route runtime reliability issue |
| MT-QG-003 | Weighted high-risk coverage | >=70% | 75.0% | Pass | Slightly lenient | Overall pass masks depth gaps in some modules |
| MT-QG-004 | Runtime threshold | API<=60s, UI<=90s, PHPUnit<=90s | All pass | Pass | Appropriate | Runtime not current bottleneck |
| MT-QG-005 | Coverage observability | tooling available | blocked locally | Warn | Appropriate | Environment/tooling limitation |

## Critical analysis

- Thresholds are mostly appropriate for current maturity.
- Some failures are due to real code/behavior defects (public 5xx, integration 404 patterns).
- Some risk remains due detectability/tooling limitations, not pure code logic.
- Coverage threshold alone is somewhat lenient because weak modules can remain under-covered while global weighted coverage passes.
