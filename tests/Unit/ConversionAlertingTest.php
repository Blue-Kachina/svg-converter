<?php

namespace Tests\Unit;

use Tests\TestCase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use App\Services\Svg\SvgConversionService;
use App\Services\Svg\SvgInputValidator;
use App\Services\Svg\SvgConverterInterface;
use Mockery as m;

class ConversionAlertingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'metrics.enabled' => true,
            'metrics.expose_endpoint' => true,
            'metrics.alerts.consecutive_failures_threshold' => 3,
            'metrics.alerts.cooldown_seconds' => 60,
        ]);
        // Reset counters
        Cache::forget('metrics:alerts:conv_failures');
        Cache::forget('metrics:alerts:last_critical_at');
    }

    public function test_critical_log_emitted_once_when_threshold_exceeded(): void
    {
        $validator = new SvgInputValidator();
        $mockConverter = m::mock(SvgConverterInterface::class);
        $service = new SvgConversionService($validator, $mockConverter);

        Log::spy();

        // Cause three validation failures by passing empty SVG
        for ($i = 0; $i < 3; $i++) {
            try {
                $service->convertToPngBytes('');
            } catch (\Throwable $e) {
                // ignore
            }
        }

        Log::shouldHaveReceived('critical')->once();
    }
}
