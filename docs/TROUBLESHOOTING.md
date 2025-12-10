# Troubleshooting Guide

This guide lists common issues and resolutions for the SVG Converter service.

## BindingResolutionException: Target class [config] does not exist.
- Cause: Instantiating a class that calls Laravel helpers like `config()` in a plain PHPUnit test that doesn't boot the framework.
- Fix: Write your tests extending `Tests\TestCase` (boots the application), or refactor the class to inject dependencies instead of calling helpers in constructors.

## Imagick not available
- Symptom: Conversion-related unit tests are skipped or fail.
- Notes: In this codebase, tests that require Imagick should skip when the extension isn't present.
- Fix: Install Imagick in your environment, or rely on the skip behavior. Ensure tests assert skip conditions early.

## Feature test fails with PDO MySQL constants error
- Cause: Environment without `ext/pdo_mysql` running a test that boots the application defaults to MySQL.
- Fix: Ensure tests use SQLite as configured in `phpunit.xml` and that `database/database.sqlite` exists. Avoid booting full app unless necessary.

## Octane worker state leakage
- Symptom: Odd behavior across requests when running under Octane.
- Fix: Avoid static/global mutable state. Ensure services are stateless or reset per request. Review middleware and singletons for cached state.

## 422 VALIDATION_ERROR when calling /api/convert
- Cause: Malformed input or invalid options (e.g., width/height out of range, non-SVG string).
- Fix: Validate your input. See `docs/openapi.yaml` for schema and `README.md` for examples.

## 400 CONVERSION_FAILED
- Cause: Conversion error (invalid SVG content or output exceeds limits).
- Fix: Adjust input, reduce dimensions/density/quality, or increase output size limit in config/env.

## 500 INTERNAL_ERROR
- Cause: Unexpected server error.
- Fix: Check logs at `storage/logs/*.log` for details. If running Octane, look for worker-level messages.

## Rate limiting blocks requests
- Symptom: Responses with 429 Too Many Requests.
- Fix: Adjust rate limits via env variables (`SVG_RATE_LIMIT_*`) or test with lower request volume.

## Batch results missing or expired
- Cause: Result TTL elapsed or cache misconfiguration.
- Fix: Ensure cache store is configured; tune `SVG_RESULTS_TTL` and `SVG_RESULTS_PREFIX`.

## Metrics endpoint not accessible
- Cause: Disabled exposure or missing auth.
- Fix: Set `METRICS_EXPOSE_ENDPOINT=true` and configure `METRICS_BASIC_AUTH` if required. Verify route `/api/metrics`.

## CORS issues in browser clients
- Fix: Configure allowed origins/headers/methods in `config/cors.php` and ensure middleware is enabled.

## Debugging tips
- Increase verbosity: `sail artisan test -vvv`.
- Stop on failure: `sail artisan test --stop-on-failure`.
- Clear config cache: `sail artisan config:clear`.
- Tail logs: `sail logs -f` or host `tail -f storage/logs/laravel-*.log`.
