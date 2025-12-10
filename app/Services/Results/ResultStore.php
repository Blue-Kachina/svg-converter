<?php

namespace App\Services\Results;

use Illuminate\Support\Facades\Cache;

/**
 * Cache-backed temporary result store for batch conversions.
 * Stores per-item results (base64 PNG) and batch metadata with TTL.
 */
class ResultStore
{
    public function __construct(
        private readonly string $prefix = '',
        private readonly int $ttlSeconds = 3600,
    ) {
    }

    public static function fromConfig(): self
    {
        $cfg = (array) (function_exists('config') ? config('svg.results') : []);
        $prefix = (string) ($cfg['prefix'] ?? 'svgconv:');
        $ttl = (int) ($cfg['ttl'] ?? 3600);
        return new self($prefix, $ttl);
    }

    public function putBatchMeta(string $batchId, array $meta): void
    {
        Cache::put($this->batchMetaKey($batchId), $meta, $this->ttlSeconds);
    }

    public function getBatchMeta(string $batchId): array|null
    {
        $m = Cache::get($this->batchMetaKey($batchId));
        return is_array($m) ? $m : null;
    }

    public function putItem(string $batchId, int $index, array $payload): void
    {
        Cache::put($this->itemKey($batchId, $index), $payload, $this->ttlSeconds);
    }

    public function getItem(string $batchId, int $index): array|null
    {
        $p = Cache::get($this->itemKey($batchId, $index));
        return is_array($p) ? $p : null;
    }

    public function deleteBatch(string $batchId, int $totalItems): void
    {
        // Best-effort cleanup; Cache facade may not support bulk delete by prefix.
        Cache::forget($this->batchMetaKey($batchId));
        for ($i = 0; $i < $totalItems; $i++) {
            Cache::forget($this->itemKey($batchId, $i));
        }
    }

    public function batchMetaKey(string $batchId): string
    {
        return rtrim($this->prefix, ':') . ":batch:{$batchId}:meta";
    }

    public function itemKey(string $batchId, int $index): string
    {
        return rtrim($this->prefix, ':') . ":batch:{$batchId}:item:{$index}";
    }
}
