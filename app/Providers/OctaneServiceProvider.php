<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;

class OctaneServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Nothing to register
    }

    public function boot(): void
    {
        // Register only if Octane is installed
        if (! class_exists(\Laravel\Octane\Octane::class)) {
            return;
        }

        // In Octane v2, there is no Octane::afterRequest().
        // Instead, hook into the RequestTerminated event to perform cleanup.
        if (class_exists(\Laravel\Octane\Events\RequestTerminated::class)) {
            Event::listen(\Laravel\Octane\Events\RequestTerminated::class, function (): void {
                // Ensure database connections are not held across requests
                try {
                    foreach (array_keys(config('database.connections', [])) as $name) {
                        try {
                            DB::connection($name)->disconnect();
                        } catch (\Throwable $e) {
                            // Ignore individual disconnect failures
                        }
                    }
                } catch (\Throwable $e) {
                    Log::debug('Octane DB disconnect error on RequestTerminated', ['error' => $e->getMessage()]);
                }

                // Let Monolog handle per-request buffering; nothing special required here.
            });
        }
    }
}
