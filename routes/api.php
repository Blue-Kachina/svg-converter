<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PingController;
use App\Http\Controllers\Api\OctaneDiagnosticsController;
use App\Http\Controllers\Api\ConvertController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\StatusController;
use App\Http\Controllers\Api\BatchConvertController;
use App\Http\Controllers\Api\MetricsController;

Route::middleware('api')
    ->group(function () {
        Route::get('/ping', [PingController::class, 'ping'])->middleware('throttle:status');

        // Primary conversion endpoint
        Route::post('/convert', [ConvertController::class, 'convert'])->middleware('throttle:convert');

        // Health & status endpoints
        Route::get('/health', [HealthController::class, 'health'])->middleware('throttle:status');
        Route::get('/status', [StatusController::class, 'status'])->middleware('throttle:status');

        // Batch conversion endpoints
        Route::post('/batch-convert', [BatchConvertController::class, 'batchConvert'])->middleware('throttle:batch');
        Route::get('/batch/{id}', [BatchConvertController::class, 'batchStatus'])->middleware('throttle:status');

        // Metrics endpoint (guarded inside controller)
        Route::get('/metrics', [MetricsController::class, 'metrics']);

        // Octane diagnostics
        Route::get('/octane/diag', [OctaneDiagnosticsController::class, 'diag'])->middleware('throttle:status');
        Route::post('/octane/queue-test', [OctaneDiagnosticsController::class, 'queueTest'])->middleware('throttle:batch');
    });
