<?php

return [
    // Master enable switch
    'enabled' => env('METRICS_ENABLED', true),

    // Expose /api/metrics endpoint
    'expose_endpoint' => env('METRICS_EXPOSE', true),

    // Optional basic auth, format: "user:pass" (do not use in production without HTTPS)
    'basic_auth' => env('METRICS_BASIC_AUTH', null),

    // Cache store and key prefix for metrics registry
    'store' => env('METRICS_CACHE_STORE', env('CACHE_STORE', config('cache.default'))),
    'prefix' => env('METRICS_PREFIX', 'metrics:'),

    // Histogram buckets in milliseconds for latency-like measures
    'histogram_buckets' => [5, 10, 25, 50, 100, 250, 500, 1000, 2000, 5000],

    // Simple alerting thresholds (best-effort via logs)
    'alerts' => [
        // When this many consecutive conversion failures occur, emit a critical log once per cooldown.
        'consecutive_failures_threshold' => env('METRICS_ALERTS_CONSECUTIVE_FAILURES', 10),
        'cooldown_seconds' => env('METRICS_ALERTS_COOLDOWN', 300),
    ],
];
