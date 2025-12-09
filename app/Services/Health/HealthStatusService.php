<?php

namespace App\Services\Health;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

class HealthStatusService
{
    public function checkAll(): array
    {
        $components = [
            'app' => $this->checkApp(),
//            'db' => $this->checkDatabase(),
            'cache' => $this->checkCache(),
            'queue' => $this->checkQueue(),
            'octane' => $this->checkOctane(),
        ];

        $ok = collect($components)->every(fn ($c) => $c['ok'] === true);

        return [
            'status' => $ok ? 'ok' : 'degraded',
            'components' => $components,
        ];
    }

    public function diagnostics(): array
    {
        $checks = $this->checkAll();

        return [
            'status' => $checks['status'],
            'components' => $checks['components'],
            'system' => [
                'time' => now()->toIso8601String(),
                'php_version' => PHP_VERSION,
                'laravel_version' => app()->version(),
                'env' => app()->environment(),
                'debug' => (bool) config('app.debug'),
            ],
            'config' => [
                'queue_driver' => config('queue.default'),
                'cache_store' => config('cache.default'),
                'octane' => [
                    'server' => config('octane.server'),
                    'workers' => (int) config('octane.workers'),
                    'task_workers' => (int) config('octane.task_workers'),
                ],
                'svg' => [
                    'max_bytes' => (int) env('SVG_MAX_BYTES'),
                    'max_width' => (int) env('SVG_MAX_WIDTH'),
                    'max_height' => (int) env('SVG_MAX_HEIGHT'),
                    'allow_remote_refs' => (bool) filter_var(env('SVG_ALLOW_REMOTE_REFS', false), FILTER_VALIDATE_BOOLEAN),
                    'conversion_driver' => env('SVG_CONVERSION_DRIVER'),
                ],
            ],
            'resources' => [
                'storage_free_bytes' => @disk_free_space(storage_path()) ?: null,
                'storage_total_bytes' => @disk_total_space(storage_path()) ?: null,
                'memory_usage_bytes' => function_exists('memory_get_usage') ? memory_get_usage(true) : null,
            ],
        ];
    }

    private function checkApp(): array
    {
        return [
            'ok' => true,
            'details' => [
                'name' => config('app.name'),
                'locale' => config('app.locale'),
            ],
        ];
    }

    private function checkDatabase(): array
    {
        try {
            DB::connection()->select('select 1');
            $ok = true;
        } catch (\Throwable $e) {
            $ok = false;
        }

        return [
            'ok' => $ok,
            'details' => [
                'connection' => config('database.default'),
            ],
        ];
    }

    private function checkCache(): array
    {
        $store = config('cache.default');
        $key = 'health:ping:' . Str::random(8);
        $ok = false;
        try {
            Cache::store($store)->put($key, '1', 5);
            $ok = Cache::store($store)->get($key) === '1';
        } catch (\Throwable $e) {
            $ok = false;
        }

        return [
            'ok' => $ok,
            'details' => [
                'store' => $store,
            ],
        ];
    }

    private function checkQueue(): array
    {
        $driver = config('queue.default');
        $ok = true;
        $details = [
            'driver' => $driver,
        ];

        if ($driver === 'database') {
            try {
                $ok = Schema::hasTable('jobs');
                $details['jobs_table'] = $ok ? 'present' : 'missing';
            } catch (\Throwable $e) {
                $ok = false;
                $details['jobs_table'] = 'unknown';
            }
        }

        return [
            'ok' => $ok,
            'details' => $details,
        ];
    }

    private function checkOctane(): array
    {
        $active = app()->bound('octane') || class_exists(\Laravel\Octane\Octane::class);

        return [
            'ok' => true, // presence not required for OK, informational only
            'details' => [
                'installed' => class_exists(\Laravel\Octane\Octane::class),
                'bound' => app()->bound('octane'),
                'active' => $active,
            ],
        ];
    }
}
