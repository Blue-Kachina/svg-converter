<?php

namespace App\Services\Svg;

use App\Exceptions\SvgConversionException;
use App\Services\Metrics\MetricsRegistry;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SvgConversionService
{
    public function __construct(
        private readonly SvgInputValidator $validator,
        private readonly SvgConverterInterface $converter,
    ) {
    }

    /**
     * Track consecutive conversion failures and emit alerts based on config thresholds.
     */
    private function recordFailureAndMaybeAlert(string $stage, string $error): void
    {
        try {
            $cfg = function_exists('config') ? (array) config('metrics.alerts', []) : [];
            $threshold = (int) ($cfg['consecutive_failures_threshold'] ?? 10);
            $cooldown = (int) ($cfg['cooldown_seconds'] ?? 300);
            $store = function_exists('config') ? (string) config('metrics.store') : null;

            $counterKey = 'metrics:alerts:conv_failures';
            $lastAlertKey = 'metrics:alerts:last_critical_at';
            // Use cache store if defined for metrics
            $cache = $store ? \Illuminate\Support\Facades\Cache::store($store) : \Illuminate\Support\Facades\Cache::getFacadeRoot();

            // Increment consecutive failure counter atomically when possible
            try {
                $count = $cache->increment($counterKey, 1);
            } catch (\Throwable $e) {
                $curr = (int) $cache->get($counterKey, 0);
                $count = $curr + 1;
                $cache->put($counterKey, $count, 86400);
            }

            if ($threshold > 0 && $count >= $threshold) {
                $now = time();
                $last = (int) $cache->get($lastAlertKey, 0);
                if ($last === 0 || ($now - $last) >= max(0, $cooldown)) {
                    // Emit a critical log once per cooldown window
                    try {
                        \Illuminate\Support\Facades\Log::critical('conversion_consecutive_failures_threshold_exceeded', [
                            'count' => $count,
                            'threshold' => $threshold,
                            'stage' => $stage,
                            'error' => $error,
                        ]);
                    } catch (\Throwable $e) {}
                    $cache->put($lastAlertKey, $now, 86400);
                }
            }
        } catch (\Throwable $e) {
            // Swallow any alerting errors; conversion path should not break
        }
    }

    /** Reset the consecutive failure counter after a successful conversion. */
    private function resetFailureCounter(): void
    {
        try {
            $store = function_exists('config') ? (string) config('metrics.store') : null;
            $cache = $store ? \Illuminate\Support\Facades\Cache::store($store) : \Illuminate\Support\Facades\Cache::getFacadeRoot();
            $cache->put('metrics:alerts:conv_failures', 0, 86400);
        } catch (\Throwable $e) {
            // ignore
        }
    }

    /**
     * Convert an SVG string to PNG bytes after validation/sanitization.
     * Adds caching for duplicate conversions and enforces output size policy.
     *
     * @param array{width?:int,height?:int,density?:int,background?:string,quality?:int} $options
     * @return string PNG binary
     * @throws SvgConversionException
     */
    public function convertToPngBytes(string $svg, array $options = []): string
    {
        $t0 = microtime(true);
        $metrics = null;
        try { $metrics = new \App\Services\Metrics\MetricsRegistry(); } catch (\Throwable $e) { $metrics = null; }
        // Prefer using config() when available (even without a Laravel container),
        // otherwise fall back to safe, test-friendly defaults
        $cfg = [];
        if (function_exists('config')) {
            try {
                $cfg = (array) config('svg', []);
            } catch (\Throwable $e) {
                $cfg = [];
            }
        }
        if (empty($cfg)) {
            $cfg = [
                'cache' => ['enabled' => false],
                'output' => [
                    // Keep small to allow unit tests to exercise size enforcement without Laravel
                    'max_png_bytes' => 50,
                    'oversize_strategy' => 'reject',
                    'quality_step' => 10,
                    'min_quality' => 40,
                ],
            ];
        }

        $result = $this->validator->validate($svg, [
            'max_bytes' => $cfg['max_bytes'] ?? (512 * 1024),
            'max_width' => $cfg['max_width'] ?? 4096,
            'max_height' => $cfg['max_height'] ?? 4096,
            'allow_remote_refs' => $cfg['allow_remote_refs'] ?? false,
        ]);

        if (!$result['valid'] || empty($result['sanitized'])) {
            $reason = 'Invalid SVG input';
            if (!empty($result['errors'])) {
                $reason .= ': ' . implode('; ', $result['errors']);
            }
            try {
                Log::channel('conversion')->error('conversion_invalid_input', [
                    'reason' => $reason,
                    'errors' => $result['errors'] ?? [],
                ]);
            } catch (\Throwable $e) {}
            // Metrics for validation failure
            try {
                if ($metrics instanceof MetricsRegistry && $metrics->enabled()) {
                    $durationMs = (int) round((microtime(true) - $t0) * 1000);
                    $metrics->incrementCounter('svg_conversion_failure_total', ['stage' => 'validate', 'error' => 'invalid_input']);
                    $metrics->observeHistogram('svg_conversion_duration_ms', (float) $durationMs, ['stage' => 'validate']);
                }
            } catch (\Throwable $e) {}
            // Alerting hook
            $this->recordFailureAndMaybeAlert('validate', 'invalid_input');
            throw SvgConversionException::loadFailed($reason);
        }

        $sanitized = $result['sanitized'];

        // Caching for duplicate conversions
        $cacheEnabled = (bool) ($cfg['cache']['enabled'] ?? false);
        $cacheTtl = (int) ($cfg['cache']['ttl'] ?? 3600);
        $cachePrefix = (string) ($cfg['cache']['prefix'] ?? 'svgconv:');
        $normalizedOptions = $this->normalizeOptions($options);
        // v2: values are stored as base64 strings for portability across cache stores
        $cacheKey = $cachePrefix . 'v2:' . hash('sha256', $sanitized . '|' . json_encode($normalizedOptions));

        // Structured log: request received (sanitized)
        try {
            Log::channel('conversion')->info('conversion_request', [
                'cache_enabled' => $cacheEnabled,
                'options' => $normalizedOptions,
                'svg_bytes' => strlen($sanitized),
            ]);
        } catch (\Throwable $e) {
            // ignore logging failures
        }

        if ($cacheEnabled) {
            $cached = Cache::get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                $decoded = base64_decode($cached, true);
                if ($decoded !== false && substr($decoded, 0, 8) === "\x89PNG\r\n\x1a\n") {
                    $durationMs = (int) round((microtime(true) - $t0) * 1000);
                    try {
                        Log::channel('conversion')->info('conversion_cache_hit', [
                            'key' => $cacheKey,
                            'options' => $normalizedOptions,
                            'png_bytes' => strlen($decoded),
                            'duration_ms' => $durationMs,
                        ]);
                    } catch (\Throwable $e) {}
                    // Metrics for cache hit
                    try {
                        if ($metrics instanceof MetricsRegistry && $metrics->enabled()) {
                            $metrics->incrementCounter('svg_conversion_success_total', ['source' => 'cache']);
                            $metrics->observeHistogram('svg_conversion_duration_ms', (float) $durationMs, ['source' => 'cache']);
                            $metrics->incrementCounter('svg_conversion_png_bytes_total', ['source' => 'cache'], strlen($decoded));
                        }
                    } catch (\Throwable $e) {}
                    // Reset failure streak on success
                    try { $this->resetFailureCounter(); } catch (\Throwable $e) {}
                    return $decoded;
                }
            }
            try {
                Log::channel('conversion')->info('conversion_cache_miss', [
                    'key' => $cacheKey,
                    'options' => $normalizedOptions,
                ]);
            } catch (\Throwable $e) {}
        }

        // Render
        $tRender0 = microtime(true);
        $png = $this->converter->convertToPng($sanitized, $normalizedOptions);
        $renderMs = (int) round((microtime(true) - $tRender0) * 1000);

        // Verify PNG integrity (magic bytes) before any further processing
        if (!$this->isValidPng($png)) {
            try {
                Log::channel('conversion')->error('conversion_invalid_png', [
                    'options' => $normalizedOptions,
                    'render_ms' => $renderMs,
                ]);
            } catch (\Throwable $e) {}
            // Metrics for render failure
            try {
                if ($metrics instanceof MetricsRegistry && $metrics->enabled()) {
                    $durationMs = (int) round((microtime(true) - $t0) * 1000);
                    $metrics->incrementCounter('svg_conversion_failure_total', ['stage' => 'render', 'error' => 'invalid_png']);
                    $metrics->observeHistogram('svg_conversion_duration_ms', (float) $durationMs, ['stage' => 'render']);
                }
            } catch (\Throwable $e) {}
            // Alerting hook
            $this->recordFailureAndMaybeAlert('render', 'invalid_png');
            throw SvgConversionException::renderFailed('Converter did not return valid PNG bytes');
        }

        // Enforce output size / optimize if necessary
        $outCfg = (array) ($cfg['output'] ?? []);
        $maxBytes = (int) ($outCfg['max_png_bytes'] ?? 0);
        $shrinkAttempts = 0;
        if ($maxBytes > 0 && strlen($png) > $maxBytes) {
            $strategy = (string) ($outCfg['oversize_strategy'] ?? 'shrink_quality');
            if ($strategy === 'shrink_quality') {
                $step = max(1, (int) ($outCfg['quality_step'] ?? 10));
                $minQ = max(1, (int) ($outCfg['min_quality'] ?? 40));
                $q = (int) ($normalizedOptions['quality'] ?? ($cfg['conversion']['quality'] ?? 90));
                while ($q > $minQ) {
                    $q = max($minQ, $q - $step);
                    $tryOpts = $normalizedOptions;
                    $tryOpts['quality'] = $q;
                    $tTry0 = microtime(true);
                    $png = $this->converter->convertToPng($sanitized, $tryOpts);
                    $renderMs += (int) round((microtime(true) - $tTry0) * 1000);
                    $shrinkAttempts++;
                    if (!$this->isValidPng($png)) {
                        try { Log::channel('conversion')->error('conversion_invalid_png_after_shrink', ['quality' => $q]); } catch (\Throwable $e) {}
                        throw SvgConversionException::renderFailed('Converter did not return valid PNG bytes');
                    }
                    if (strlen($png) <= $maxBytes) {
                        break;
                    }
                    if ($q === $minQ) {
                        break;
                    }
                }
                if (strlen($png) > $maxBytes) {
                    try {
                        Log::channel('conversion')->error('conversion_oversize_reject', [
                            'max_bytes' => $maxBytes,
                            'png_bytes' => strlen($png),
                            'attempts' => $shrinkAttempts,
                        ]);
                    } catch (\Throwable $e) {}
                    // Metrics for oversize failure after shrink
                    try {
                        if ($metrics instanceof MetricsRegistry && $metrics->enabled()) {
                            $durationMs = (int) round((microtime(true) - $t0) * 1000);
                            $metrics->incrementCounter('svg_conversion_failure_total', ['stage' => 'postprocess', 'error' => 'oversize']);
                            $metrics->observeHistogram('svg_conversion_duration_ms', (float) $durationMs, ['stage' => 'postprocess']);
                        }
                    } catch (\Throwable $e) {}
                    // Alerting hook
                    $this->recordFailureAndMaybeAlert('postprocess', 'oversize');
                    throw SvgConversionException::renderFailed('PNG exceeds maximum allowed size');
                }
            } else { // reject
                try {
                    Log::channel('conversion')->error('conversion_oversize_reject', [
                        'max_bytes' => $maxBytes,
                        'png_bytes' => strlen($png),
                        'strategy' => $strategy,
                    ]);
                } catch (\Throwable $e) {}
                // Metrics for oversize reject strategy
                try {
                    if ($metrics instanceof MetricsRegistry && $metrics->enabled()) {
                        $durationMs = (int) round((microtime(true) - $t0) * 1000);
                        $metrics->incrementCounter('svg_conversion_failure_total', ['stage' => 'postprocess', 'error' => 'oversize_reject']);
                        $metrics->observeHistogram('svg_conversion_duration_ms', (float) $durationMs, ['stage' => 'postprocess']);
                    }
                } catch (\Throwable $e) {}
                throw SvgConversionException::renderFailed('PNG exceeds maximum allowed size');
            }
        }

        if ($cacheEnabled) {
            try {
                $encoded = base64_encode($png);
                Cache::put($cacheKey, $encoded, $cacheTtl);
            } catch (\Throwable $e) {
                // Do not fail the request if caching fails (e.g., incompatible backend)
                try {
                    Log::channel('conversion')->warning('conversion_cache_put_failed', [
                        'key' => $cacheKey,
                        'message' => $e->getMessage(),
                    ]);
                } catch (\Throwable $ignored) {}
            }
        }

        // Structured success log
        $totalMs = (int) round((microtime(true) - $t0) * 1000);
        try {
            Log::channel('conversion')->info('conversion_success', [
                'options' => $normalizedOptions,
                'png_bytes' => strlen($png),
                'render_ms' => $renderMs,
                'total_ms' => $totalMs,
                'shrink_attempts' => $shrinkAttempts,
                'cached' => false,
            ]);
        } catch (\Throwable $e) {}

        // Success metrics
        try {
            if ($metrics instanceof MetricsRegistry && $metrics->enabled()) {
                $metrics->incrementCounter('svg_conversion_success_total', ['source' => 'render']);
                $metrics->observeHistogram('svg_conversion_duration_ms', (float) $totalMs, ['source' => 'render']);
                $metrics->incrementCounter('svg_conversion_png_bytes_total', ['source' => 'render'], strlen($png));
            }
        } catch (\Throwable $e) {}

        return $png;
    }

    /**
     * Convert an SVG string to a base64-encoded PNG string (without data URI prefix).
     *
     * @param array{width?:int,height?:int,density?:int,background?:string,quality?:int} $options
     * @return string base64 string of PNG bytes
     */
    public function convertToBase64Png(string $svg, array $options = []): string
    {
        $png = $this->convertToPngBytes($svg, $options);
        return base64_encode($png);
    }

    /**
     * Quick sanity check for PNG bytes using the PNG signature (magic bytes).
     */
    private function isValidPng(string $bytes): bool
    {
        return strlen($bytes) >= 8 && substr($bytes, 0, 8) === "\x89PNG\r\n\x1a\n";
    }

    /**
     * Normalize options into a deterministic subset for hashing and passing to converters.
     * @param array $options
     * @return array
     */
    private function normalizeOptions(array $options): array
    {
        $allowed = ['width', 'height', 'density', 'background', 'quality'];
        $normalized = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $options)) {
                $normalized[$key] = $options[$key];
            }
        }
        ksort($normalized);
        return $normalized;
    }
}
