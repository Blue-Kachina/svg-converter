# Configuration Options

Configuration can be provided via environment variables (preferred) and Laravel config files. This service aims to be safe-by-default and production-ready with sensible defaults.

## Environment Variables

### Rate Limiting
- `SVG_RATE_LIMIT_CONVERT` (default 60) — Per-minute limit for `POST /api/convert` per IP/user.
- `SVG_RATE_LIMIT_BATCH` (default 15) — Per-minute limit for `POST /api/batch-convert`.
- `SVG_RATE_LIMIT_STATUS` (default 120) — Per-minute limit for diagnostic endpoints (e.g., `/api/status`, `/api/health`).

### API Authentication (optional)
- `SVG_API_AUTH_ENABLED` (default false) — When true, endpoints require an API key.
- `SVG_API_KEYS` — Comma-separated list of allowed API keys, e.g. `key1,key2`.
- `SVG_API_KEY_HEADER` (default `X-API-Key`) — Header name expected for API key.

### Request Signing (optional)
- `SVG_SIGNING_ENABLED` (default false) — Enable HMAC request signing.
- `SVG_SIGNING_SECRET` — Shared secret for signing.
- `SVG_SIGNATURE_HEADER` (default `X-Signature`) — Header containing the hex HMAC digest.
- `SVG_TIMESTAMP_HEADER` (default `X-Signature-Timestamp`) — Unix timestamp header.
- `SVG_SIGNATURE_SKEW` (default 300) — Allowed skew in seconds.
- `SVG_SIGNATURE_ALGO` (default `sha256`) — Hash algorithm.

### SVG Input Constraints
- `SVG_MAX_BYTES` (default 524288) — Maximum raw SVG payload size in bytes (early check).

### Output Constraints
- `SVG_OUTPUT_MAX_PNG_BYTES` — Maximum bytes allowed for resulting PNG.
- `SVG_OUTPUT_STRATEGY` — Strategy when output exceeds max: `shrink_quality` or `reject`.

### Batch Limits
- `SVG_BATCH_MAX_ITEMS` (default 50) — Maximum items in a single batch request.

### Results Cache
- `SVG_RESULTS_TTL` (seconds) — Time-to-live for per-item results in cache.
- `SVG_RESULTS_PREFIX` — Cache key prefix for result storage.

### Metrics & Alerts
- `METRICS_ENABLED` (default true) — Toggle metrics collection.
- `METRICS_EXPOSE_ENDPOINT` (default false) — Whether to expose `/api/metrics`.
- `METRICS_BASIC_AUTH` — `user:pass` for basic auth protection of `/api/metrics`.
- `METRICS_ALERTS_CONSECUTIVE_FAILURES_THRESHOLD` — Emit critical log when consecutive failures reach this threshold.
- `METRICS_ALERTS_COOLDOWN_SECONDS` — Cooldown window for repeated alerts.

### Octane
- `OCTANE_SERVER` (e.g., `frankenphp`) — Server implementation.
- `OCTANE_WORKERS` — Number of workers.
- `OCTANE_MAX_REQUESTS` — Max requests per worker before recycle.

## Laravel Config Files

### `config/svg.php`
Typical options (names may vary by codebase — adjust to match actual file):
```php
return [
    'input' => [
        'max_bytes' => env('SVG_MAX_BYTES', 512 * 1024),
    ],
    'output' => [
        'max_png_bytes' => env('SVG_OUTPUT_MAX_PNG_BYTES', 2 * 1024 * 1024),
        'exceed_strategy' => env('SVG_OUTPUT_STRATEGY', 'shrink_quality'), // or 'reject'
    ],
    'batch' => [
        'max_items' => env('SVG_BATCH_MAX_ITEMS', 50),
    ],
    'results' => [
        'ttl' => env('SVG_RESULTS_TTL', 3600),
        'prefix' => env('SVG_RESULTS_PREFIX', 'svg:results:'),
    ],
];
```

### `config/metrics.php`
```php
return [
    'enabled' => env('METRICS_ENABLED', true),
    'expose_endpoint' => env('METRICS_EXPOSE_ENDPOINT', false),
    'basic_auth' => env('METRICS_BASIC_AUTH'), // e.g. "user:pass"

    'alerts' => [
        'consecutive_failures_threshold' => env('METRICS_ALERTS_CONSECUTIVE_FAILURES_THRESHOLD', 10),
        'cooldown_seconds' => env('METRICS_ALERTS_COOLDOWN_SECONDS', 300),
    ],
];
```

## Redis and Cache
- In Sail, Redis is available at `redis:6379`. Configure your cache store accordingly in `config/cache.php`.
- Result storage and metrics registry rely on the cache for transient data.

## Security Defaults
- CORS configured via `fruitcake/php-cors` (see `config/cors.php`).
- Request validation and sanitization are performed before conversion.

## Changing Defaults
Update `.env`, then clear config cache if needed:
```bash
./vendor/bin/sail artisan config:clear
```
