<?php

namespace Tests\Feature;

use App\Services\Svg\SvgConversionService;
use Tests\TestCase;

class InputSizeConstraintsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Ensure auth is disabled for test simplicity
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

    public function test_single_convert_rejects_oversized_svg(): void
    {
        config()->set('svg.max_bytes', 50);
        $smallSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"/>';
        $bigSvg = str_repeat(' ', 60) . $smallSvg; // exceed 50 bytes

        $this->postJson('/api/convert', ['svg' => $bigSvg])
            ->assertStatus(422)
            ->assertJsonFragment(['code' => 'VALIDATION_ERROR']);

        // Control: small should pass
        $this->postJson('/api/convert', ['svg' => $smallSvg])
            ->assertStatus(200);
    }

    public function test_batch_convert_rejects_oversized_svg_item(): void
    {
        config()->set('svg.max_bytes', 50);
        $smallSvg = '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1"/>';
        $bigSvg = str_repeat(' ', 60) . $smallSvg; // exceed 50 bytes

        $payload = [
            'items' => [
                [ 'svg' => $smallSvg ],
                [ 'svg' => $bigSvg ],
            ],
        ];

        $this->postJson('/api/batch-convert', $payload)
            ->assertStatus(422)
            ->assertJsonFragment(['code' => 'validation_error']); // Laravel's default in our exception mapper
    }
}
