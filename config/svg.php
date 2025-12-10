<?php

return [
    // Maximum raw SVG string size accepted (in bytes)
    'max_bytes' => env('SVG_MAX_BYTES', 512 * 1024), // 512 KB

    // Maximum width/height (from attributes) we accept for processing
    'max_width' => env('SVG_MAX_WIDTH', 4096),
    'max_height' => env('SVG_MAX_HEIGHT', 4096),

    // Whether to allow remote http(s) references in href/xlink:href
    'allow_remote_refs' => env('SVG_ALLOW_REMOTE_REFS', false),

    // Conversion settings
    'conversion' => [
        'driver' => env('SVG_CONVERSION_DRIVER', 'imagick'),
        // Density (DPI) used by rasterizer to control output resolution
        'density' => env('SVG_CONVERSION_DENSITY', 144),
        // Background defaults to transparent; can be a color like 'white' or '#ffffff'
        'background' => env('SVG_CONVERSION_BACKGROUND', 'transparent'),
        // PNG compression quality (0-100)
        'quality' => env('SVG_CONVERSION_QUALITY', 90),
    ],

    // Caching of duplicate conversions
    'cache' => [
        'enabled' => env('SVG_CACHE_ENABLED', true),
        // seconds
        'ttl' => env('SVG_CACHE_TTL', 3600),
        // optional prefix to avoid collisions
        'prefix' => env('SVG_CACHE_PREFIX', 'svgconv:'),
    ],

    // Output constraints and optimization behavior
    'output' => [
        // Max allowed PNG bytes; 0 to disable check
        'max_png_bytes' => env('SVG_MAX_PNG_BYTES', 2 * 1024 * 1024), // 2 MB
        // Strategy when PNG exceeds max size: 'reject' or 'shrink_quality'
        'oversize_strategy' => env('SVG_OVERSIZE_STRATEGY', 'shrink_quality'),
        // When shrinking, reduce quality by this step until min_quality
        'quality_step' => env('SVG_QUALITY_STEP', 10),
        'min_quality' => env('SVG_MIN_QUALITY', 40),
    ],

    // Temporary files directory and cleanup policy
    'temp' => [
        'dir' => env('SVG_TEMP_DIR', storage_path('app/svg-temp')),
        // Files older than this (seconds) will be cleaned by the command
        'max_age_seconds' => env('SVG_TEMP_MAX_AGE', 24 * 3600), // 24h
    ],

    // Result storage for batch processing
    'results' => [
        // seconds; how long batch results are retrievable
        'ttl' => env('SVG_RESULTS_TTL', 3600),
        'prefix' => env('SVG_RESULTS_PREFIX', 'svgconv:'),
    ],
];
