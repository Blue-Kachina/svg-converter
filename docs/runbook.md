# Operations Runbook

This runbook explains how to monitor, diagnose, and mitigate issues in the SVG Converter service across staging and production.

## Quick links
- Health: `GET /api/ping`, `GET /api/health`
- Status: `GET /api/status`
- Metrics (Prometheus text): `GET /api/metrics` (exposure configurable)
- Logs: `storage/logs/*.log` or container stdout/err

## Routine Monitoring
- Uptime check: probe `/api/ping` every 30s; alert on 3 consecutive failures
- Latency: track `http_request_duration_ms{path="/api/convert"}` p95 and p99
- Error rate: `http_errors_total` and conversion failure counters in metrics
- Throughput: `http_requests_total` for `/api/convert`

## Metrics of interest
- http_request_duration_ms (histogram) with labels: method, path, status_class
- http_requests_total, http_response_bytes_total, http_errors_total
- conversion_duration_ms (histogram)
- conversion_success_total, conversion_failure_total{stage,reason}
- conversion_cache_hits_total

## Log signals
- Look for `critical` level logs from conversion failures bursts
- Alert log: emits when consecutive failures exceed threshold (`metrics.alerts.consecutive_failures_threshold`) with cooldown (`metrics.alerts.cooldown_seconds`)

## First response checklist
1. Verify health
```
curl -fsS $BASE/api/ping
curl -fsS $BASE/api/health
```
2. Check recent errors and metrics
```
curl -fsS $BASE/api/status
# If metrics exposed (staging):
curl -fsS $BASE/api/metrics | head -200
```
3. Inspect logs
```
# Sail
sail artisan pail -f
# Docker compose
docker compose logs -n 200 app
```
4. Try a simple conversion (small SVG) to reproduce

## Common issues
- Spike in `conversion_failure_total{reason="timeout"}`: check Imagick availability, memory pressure, and input size limits
- Increased `http_request_duration_ms` p95: check Octane worker saturation; consider raising `OCTANE_WORKERS` or lowering `OCTANE_MAX_REQUESTS`
- Metrics endpoint errors: confirm `METRICS_EXPOSE` is enabled only in staging or behind auth in prod

## Mitigations
- Temporarily reduce max input size in `config/svg.php` to stabilize
- Scale horizontally by increasing replicas (or workers) if using an orchestrator
- Clear caches if corrupted
```
php artisan config:clear && php artisan route:clear
```

## Post‑incident
- Capture a performance baseline (see `docs/performance-baseline.md`)
- File a retrospective with metrics snapshots and logs
