<?php

namespace App\Providers;

use App\Services\Svg\ImagickSvgConverter;
use App\Services\Svg\SvgConverterInterface;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(SvgConverterInterface::class, function () {
            $driver = function_exists('config') ? (string) config('svg.conversion.driver', 'imagick') : 'imagick';
            switch ($driver) {
                case 'imagick':
                default:
                    return new ImagickSvgConverter();
            }
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        //
    }
}
