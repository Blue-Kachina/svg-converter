<?php

return [
    // Octane server to use. Options: "frankenphp", "swoole", "roadrunner"
    'server' => env('OCTANE_SERVER', 'frankenphp'),

    // Watch for file changes and reload workers in development
    'watch' => env('OCTANE_WATCH', true),

    // The number of application workers that will be maintained per CPU core.
    'workers' => env('OCTANE_WORKERS', 1),

    // Max requests a worker should handle before being recycled.
    'max_requests' => env('OCTANE_MAX_REQUESTS', 250),

    // Octane Listeners: ensure proper cleanup for long-running workers
    'listeners' => [
        \Laravel\Octane\Events\OperationTerminated::class => [
            \Laravel\Octane\Listeners\FlushOnce::class,
            \Laravel\Octane\Listeners\FlushTemporaryContainerInstances::class,
            \Laravel\Octane\Listeners\DisconnectFromDatabases::class,
            \Laravel\Octane\Listeners\CollectGarbage::class,
        ],
    ],

    // Octane-specific cache store for per-worker memory (optional)
    'cache' => [
        'store' => env('OCTANE_CACHE_STORE', 'octane'),
    ],

    // Garbage collection thresholds
    'garbage' => [
        'threshold' => 50,
        'limit' => 1000,
    ],

    // Task worker configuration
    'tasks' => [
        'workers' => env('OCTANE_TASK_WORKERS', 1),
        'max_requests' => env('OCTANE_TASK_MAX_REQUESTS', 500),
    ],

    // File paths to watch in watch mode
    'watch_directories' => [
        base_path('app'),
        base_path('bootstrap'),
        base_path('config'),
        base_path('routes'),
        base_path('resources'),
    ],
];
