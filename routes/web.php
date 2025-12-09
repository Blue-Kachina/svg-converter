<?php

use Illuminate\Support\Facades\Route;
use App\Jobs\TestJob;

Route::get('/', function () {
    return view('welcome');
});

// Simple route to verify queue configuration
Route::get('/queue-test', function () {
    TestJob::dispatch('Hello from /queue-test');

    return response()->json([
        'queued' => true,
        'connection' => config('queue.default'),
        'note' => 'Run `php artisan queue:work` to process the job.'
    ]);
});
