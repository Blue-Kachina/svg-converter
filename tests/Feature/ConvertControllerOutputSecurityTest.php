<?php

namespace Tests\Feature;

use App\Services\Svg\SvgConverterInterface;
use Illuminate\Foundation\Testing\WithFaker;
use Tests\TestCase;

class ConvertControllerOutputSecurityTest extends TestCase
{
    use WithFaker;

    protected function setUp(): void
    {
        parent::setUp();

        // Bind a fake converter that returns a tiny valid PNG to avoid Imagick dependency
        $this->app->bind(SvgConverterInterface::class, function () {
            return new class implements SvgConverterInterface {
                public function convertToPng(string $svg, array $options = []): string
                {
                    // 1x1 PNG (base64 decoded)
                    $b64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAOa3j0YAAAAASUVORK5CYII=';
                    return base64_decode($b64, true) ?: '';
                }
            };
        });
    }

    private function sampleSvg(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10" fill="#09f"/></svg>';
    }

    public function test_default_returns_json_with_base64(): void
    {
        $resp = $this->postJson('/api/convert', [
            'svg' => $this->sampleSvg(),
        ]);

        $resp->assertStatus(200)
            ->assertHeader('Content-Type', 'application/json')
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['png_base64'], 'meta' => ['duration_ms', 'content_length']]);
    }

    public function test_accept_image_png_returns_raw_png_with_headers(): void
    {
        $resp = $this->withHeaders(['Accept' => 'image/png'])
            ->post('/api/convert', [
                'svg' => $this->sampleSvg(),
            ]);

        $resp->assertStatus(200)
            ->assertHeader('Content-Type', 'image/png')
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Content-Disposition', 'inline; filename="image.png"');

        $body = $resp->getContent();
        $this->assertTrue(str_starts_with($body, "\x89PNG\r\n\x1a\n"));
    }

    public function test_format_base64_returns_plain_text_base64(): void
    {
        $resp = $this->postJson('/api/convert', [
            'svg' => $this->sampleSvg(),
            'format' => 'base64',
        ]);

        $resp->assertStatus(200)
            ->assertHeader('Content-Type', 'text/plain; charset=utf-8');

        $this->assertNotEmpty($resp->getContent());
    }
}
