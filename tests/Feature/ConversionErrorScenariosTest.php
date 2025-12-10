<?php

namespace Tests\Feature;

use App\Exceptions\SvgConversionException;
use App\Services\Svg\SvgConversionService;
use Tests\Support\InteractsWithSvg;
use Tests\TestCase;

class ConversionErrorScenariosTest extends TestCase
{
    use InteractsWithSvg;

    protected function setUp(): void
    {
        parent::setUp();
        config()->set('svg.auth.enabled', false);
    }

    public function test_converter_failure_is_mapped_to_400(): void
    {
        // Bind a service that always throws a render failure
        $this->app->bind(SvgConversionService::class, function () {
            return new class() extends SvgConversionService {
                public function __construct() {}
                public function convertToBase64Png(string $svg, array $options = []): string
                {
                    throw SvgConversionException::renderFailed('Mock render failure');
                }
            };
        });

        $resp = $this->postJson('/api/convert', [
            'svg' => $this->makeSvg(10, 10, '#abc'),
        ]);

        $resp->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'CONVERSION_FAILED');
    }

    public function test_malicious_svg_fails_validation_and_returns_400(): void
    {
        // Bind a service that simulates validator rejection by throwing a load failure
        $this->app->bind(SvgConversionService::class, function () {
            return new class() extends SvgConversionService {
                public function __construct() {}
                public function convertToBase64Png(string $svg, array $options = []): string
                {
                    throw SvgConversionException::loadFailed('Malicious content detected');
                }
            };
        });

        $resp = $this->postJson('/api/convert', [
            'svg' => $this->makeMaliciousSvg(),
        ]);

        // Our controller maps SvgConversionException from validation to 400 as well
        $resp->assertStatus(400)
            ->assertJsonPath('success', false)
            ->assertJsonPath('error.code', 'CONVERSION_FAILED');
    }
}
