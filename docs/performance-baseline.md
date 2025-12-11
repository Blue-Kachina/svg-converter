# Performance Baseline

This guide explains how to gather a repeatable performance baseline using built‑in Artisan commands and the `scripts/perf-baseline.sh` helper.

## Prerequisites
- Docker + Sail alias preferred, or local PHP with Composer
- SQLite file present (`database/database.sqlite`); the script will create it if missing

## One‑shot baseline (recommended)
```
bash scripts/perf-baseline.sh 200 20 100
```
- 200 requests total
- concurrency 20
- 100 iterations for the micro‑benchmark

Outputs are saved under `artifacts/` with timestamps:
- `benchmark-YYYYmmdd-HHMMSS.txt`
- `loadtest-YYYYmmdd-HHMMSS.txt`

## Manual commands
```
# Run inside Sail for consistent results
sail artisan svg:benchmark --iterations=100
sail artisan svg:load-test --requests=200 --concurrency=20
```

## What to record
- p50/p90/p95/p99 latency
- Throughput (RPS)
- Success/error rate and error distribution
- Avg/Max output PNG bytes
- Process peak memory (from benchmark output)

## Interpreting results
- Compare to previous baselines stored in `artifacts/`
- Investigate regressions >10% in p95 or error rate increases
- When using Octane, re‑run after changing worker counts or max‑requests

## Notes
- Run on a quiescent machine or CI runner for stability
- Consider pinning container CPU/memory in Docker to control variability
