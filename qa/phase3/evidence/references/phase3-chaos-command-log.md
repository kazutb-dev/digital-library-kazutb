# experimental evaluation layer (Phase 3) Chaos Command Log

Date: 2026-05-13

## Commands Executed

1. Chaos run:

- `pwsh -File qa/phase3/chaos/scripts/run-phase3-chaos.ps1`

2. Chaos metrics derivation:

- PowerShell aggregation over `qa/phase3/chaos/results/phase3-chaos-summary-20260513-142323.csv`

3. Tooling verification:

- `php -v`
- `python --version`
- `pwsh --version`
- `composer --version`

## Run IDs

1. Chaos run ID: `20260513-142323`
2. Mutation run ID (prior phase): `20260513-140552`
3. Performance run IDs (prior phase): `20260513-133438`, `20260513-134213`, `20260513-134513`

## Evidence Paths

1. `qa/phase3/chaos/results/phase3-chaos-summary-20260513-142323.csv`
2. `qa/phase3/chaos/results/phase3-chaos-requests-20260513-142323.csv`
3. `qa/phase3/evidence/logs/phase3-chaos-CHS-001-API-DOWN-20260513-142323.log`
4. `qa/phase3/evidence/logs/phase3-chaos-CHS-002-DB-SLOWDOWN-20260513-142323.log`
5. `qa/phase3/evidence/logs/phase3-chaos-CHS-003-NET-LATENCY-20260513-142323.log`
6. `qa/phase3/evidence/logs/phase3-chaos-CHS-004-CPU-PRESSURE-20260513-142323.log`

---

KazUTB Digital Library — QA experimental evaluation layer (Phase 3) Part 3
