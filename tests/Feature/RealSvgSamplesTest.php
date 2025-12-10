<?php

namespace Tests\Feature;

use App\Services\Svg\SvgConversionService;
use Tests\TestCase;

class RealSvgSamplesTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config()->set('svg.auth.enabled', false);

        // Stub conversion to avoid Imagick; return a valid tiny PNG base64
        $this->app->bind(SvgConversionService::class, function () {
            return new class() extends SvgConversionService {
                public function __construct() {}
                public function convertToBase64Png(string $svg, array $options = []): string
                {
                    return 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAOa3j0YAAAAASUVORK5CYII=';
                }
            };
        });
    }

    private function sampleGradientSvg(): string
    {
        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">
            <defs>
                <linearGradient id="g" x1="0" y1="0" x2="1" y2="1">
                    <stop offset="0%" stop-color="#ff9a9e"/>
                    <stop offset="100%" stop-color="#fad0c4"/>
                </linearGradient>
            </defs>
            <rect x="0" y="0" width="64" height="64" fill="url(#g)"/>
        </svg>
        SVG;
    }

    private function samplePathSvg(): string
    {
        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="64" height="64" viewBox="0 0 64 64">
            <path d="M8 8 L56 8 L56 56 L8 56 Z M16 16 L48 16 L48 48 L16 48 Z" fill="#0af" fill-rule="evenodd"/>
        </svg>
        SVG;
    }

    private function sampleTextSvg(): string
    {
        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="120" height="40">
            <rect width="120" height="40" fill="#fff"/>
            <text x="10" y="25" font-family="Arial" font-size="16" fill="#333">Hello SVG</text>
        </svg>
        SVG;
    }

    /**
     * Ensure realistic, but safe SVGs pass validation and produce output via /api/convert.
     */
    public function test_realistic_svgs_convert_successfully(): void
    {
        foreach ([$this->sampleGradientSvg(), $this->samplePathSvg(), $this->sampleTextSvg()] as $svg) {
            $resp = $this->postJson('/api/convert', [ 'svg' => $svg ]);
            $resp->assertStatus(200)
                ->assertJsonPath('success', true)
                ->assertJsonStructure(['data' => ['png_base64']]);
        }
    }
}
