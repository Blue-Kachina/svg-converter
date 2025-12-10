<?php

namespace Tests\Feature;

use App\Services\Svg\SvgConversionService;
use Illuminate\Foundation\Testing\WithFaker;
use Illuminate\Support\Str;
use Tests\TestCase;

class RateLimitingTest extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();
        // Reduce rate limit for test isolation
        config()->set('svg.rate_limits.convert', 2);
        config()->set('svg.auth.enabled', false);

        // Bind a fake converter to avoid Imagick dependency
        $this->app->bind(SvgConversionService::class, function () {
            return new class() extends SvgConversionService {
                public function __construct() {}
                public function convertToBase64Png(string $svg, array $options = []): string
                {
                    return base64_encode('png');
                }
            };
        });
    }

    public function test_convert_endpoint_is_rate_limited_after_threshold(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10" fill="#000"/></svg>';

        // First two requests should pass
        $this->postJson('/api/convert', ['svg' => $svg])
            ->assertStatus(200);
        $this->postJson('/api/convert', ['svg' => $svg])
            ->assertStatus(200);

        // Third within same minute should be 429
        $this->postJson('/api/convert', ['svg' => $svg])
            ->assertStatus(429);
    }
}
