<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Http\Request;

class RateLimiterServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        // Per-IP/user rate limiting for key endpoints
        RateLimiter::for('convert', function (Request $request) {
            $key = $this->rateKey($request, 'convert');
            $max = (int) (config('svg.rate_limits.convert', 60)); // per minute
            return Limit::perMinute($max)->by($key);
        });

        RateLimiter::for('batch', function (Request $request) {
            $key = $this->rateKey($request, 'batch');
            $max = (int) (config('svg.rate_limits.batch', 15));
            return Limit::perMinute($max)->by($key);
        });

        RateLimiter::for('status', function (Request $request) {
            $key = $this->rateKey($request, 'status');
            $max = (int) (config('svg.rate_limits.status', 120));
            return Limit::perMinute($max)->by($key);
        });
    }

    private function rateKey(Request $request, string $scope): string
    {
        $userId = optional($request->user())->getAuthIdentifier();
        $ip = $request->ip();
        return implode(':', array_filter(['svgconv', $scope, $userId, $ip]));
    }
}
