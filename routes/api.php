<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\Api\PingController;
use App\Http\Controllers\Api\OctaneDiagnosticsController;
use App\Http\Controllers\Api\ConvertController;
use App\Http\Controllers\Api\HealthController;
use App\Http\Controllers\Api\StatusController;
use App\Http\Controllers\Api\BatchConvertController;

Route::middleware('api')
    ->group(function () {
        Route::get('/ping', [PingController::class, 'ping']);

        // Primary conversion endpoint
        Route::post('/convert', [ConvertController::class, 'convert']);

        // Health & status endpoints
        Route::get('/health', [HealthController::class, 'health']);
        Route::get('/status', [StatusController::class, 'status']);

        // Batch conversion endpoints
        Route::post('/batch-convert', [BatchConvertController::class, 'batchConvert']);
        Route::get('/batch/{id}', [BatchConvertController::class, 'batchStatus']);

        // Octane diagnostics
        Route::get('/octane/diag', [OctaneDiagnosticsController::class, 'diag']);
        Route::post('/octane/queue-test', [OctaneDiagnosticsController::class, 'queueTest']);
    });
