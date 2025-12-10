<?php

namespace App\Services\Metrics;

use Illuminate\Support\Facades\Cache;

/**
 * Lightweight, cache-backed metrics registry.
 * - Supports counters and histograms
 * - Thread/worker safe under Octane via atomic cache operations where possible
 * - Exposes Prometheus text format
 */
class MetricsRegistry
{
    private string $prefix;
    private string $store;
    private array $histogramBuckets;
    private bool $enabled;

    public function __construct()
    {
        $cfg = [];
        if (function_exists('config')) {
            try { $cfg = (array) config('metrics', []); } catch (\Throwable $e) { $cfg = []; }
        }
        $this->enabled = (bool) ($cfg['enabled'] ?? true);
        $this->prefix = (string) ($cfg['prefix'] ?? 'metrics:');
        $defaultStore = function_exists('config') ? (string) config('cache.default') : 'file';
        $this->store = (string) ($cfg['store'] ?? $defaultStore);
        $this->histogramBuckets = (array) ($cfg['histogram_buckets'] ?? [
            // milliseconds buckets
            5, 10, 25, 50, 100, 250, 500, 1000, 2000, 5000
        ]);
    }

    public function enabled(): bool
    {
        return $this->enabled;
    }

    // --- Counter ---
    public function incrementCounter(string $name, array $labels = [], int $value = 1): void
    {
        if (!$this->enabled) return;
        $key = $this->key('counter', $name, $labels);
        try {
            // Some stores support atomic increment
            Cache::store($this->store)->increment($key, $value);
        } catch (\Throwable $e) {
            // Fallback read/modify/write
            $curr = (int) Cache::store($this->store)->get($key, 0);
            Cache::store($this->store)->put($key, $curr + $value, 86400);
        }
    }

    // --- Histogram ---
    public function observeHistogram(string $name, float $value, array $labels = []): void
    {
        if (!$this->enabled) return;
        // Count total
        $this->incrementCounter($name.'__count', $labels, 1);
        // Sum
        $this->incrementCounter($name.'__sum', $labels, (int) round($value));
        // Buckets are cumulative
        foreach ($this->histogramBuckets as $bucket) {
            if ($value <= $bucket) {
                $bucketLabels = $labels + ['le' => (string)$bucket];
                $this->incrementCounter($name.'__bucket', $bucketLabels, 1);
            }
        }
        // +Inf bucket
        $bucketLabels = $labels + ['le' => '+Inf'];
        $this->incrementCounter($name.'__bucket', $bucketLabels, 1);
    }

    // --- Read helpers ---
    public function getCounterValue(string $name, array $labels = []): int
    {
        $key = $this->key('counter', $name, $labels);
        $val = Cache::store($this->store)->get($key, 0);
        return (int) (is_numeric($val) ? $val : 0);
    }

    // --- Export ---
    public function exportPrometheus(): string
    {
        if (!$this->enabled) {
            return "# HELP metrics_disabled Metrics collection disabled\n# TYPE metrics_disabled gauge\nmetrics_disabled 1\n";
        }
        // We don't list keys from cache stores generically; instead we maintain an index
        $indexKey = $this->indexKey();
        $allKeys = Cache::store($this->store)->get($indexKey, []);
        if (!is_array($allKeys)) $allKeys = [];

        $lines = [];
        foreach ($allKeys as $keyInfo) {
            // $keyInfo: ['type' => 'counter', 'name' => '...', 'labels' => [...], 'key' => '...']
            $type = $keyInfo['type'] ?? 'counter';
            $name = $keyInfo['name'] ?? '';
            $labels = $keyInfo['labels'] ?? [];
            $key = $keyInfo['key'] ?? '';
            $val = Cache::store($this->store)->get($key);
            if (!is_numeric($val)) $val = 0;

            $metricName = $this->sanitizeName($name);
            $labelStr = $this->formatLabels($labels);
            $lines[] = sprintf('%s%s %s', $metricName, $labelStr, (string) $val);
        }

        return implode("\n", $lines)."\n";
    }

    // Internal: track an index of keys for export. Called from key() generator.
    private function rememberKey(string $type, string $name, array $labels, string $key): void
    {
        $indexKey = $this->indexKey();
        $entry = ['type' => $type, 'name' => $name, 'labels' => $labels, 'key' => $key];
        try {
            Cache::store($this->store)->lock($indexKey.':lock', 2)->block(2, function () use ($indexKey, $entry) {
                $list = Cache::store($this->store)->get($indexKey, []);
                if (!is_array($list)) $list = [];
                // Deduplicate by key
                $exists = false;
                foreach ($list as $item) {
                    if (($item['key'] ?? '') === $entry['key']) { $exists = true; break; }
                }
                if (!$exists) {
                    $list[] = $entry;
                    Cache::store($this->store)->put($indexKey, $list, 86400);
                }
            });
        } catch (\Throwable $e) {
            // Best effort without lock
            $list = Cache::store($this->store)->get($indexKey, []);
            if (!is_array($list)) $list = [];
            $list[] = $entry;
            Cache::store($this->store)->put($indexKey, $list, 86400);
        }
    }

    private function indexKey(): string
    {
        return $this->prefix.'index';
    }

    private function key(string $type, string $name, array $labels): string
    {
        ksort($labels);
        $labelStr = json_encode($labels, JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE);
        $hash = sha1($name.'|'.$labelStr);
        $key = $this->prefix.$type.':'.$hash;
        // Record in index for export
        $this->rememberKey($type, $name, $labels, $key);
        return $key;
    }

    private function sanitizeName(string $name): string
    {
        // Prometheus metric name rules: [a-zA-Z_:][a-zA-Z0-9_:]*
        $name = preg_replace('/[^a-zA-Z0-9_:]/', '_', $name);
        return $name ?? '';
    }

    private function formatLabels(array $labels): string
    {
        if (empty($labels)) return '';
        $pairs = [];
        foreach ($labels as $k => $v) {
            $k = preg_replace('/[^a-zA-Z0-9_]/', '_', (string)$k);
            $v = (string) $v;
            $v = str_replace(['\\', "\n", '"'], ['\\\\', '\\n', '\\"'], $v);
            $pairs[] = sprintf('%s="%s"', $k, $v);
        }
        return '{'.implode(',', $pairs).'}';
    }
}
