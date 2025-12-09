<?php

namespace Tests\Unit;

use App\Exceptions\SvgConversionException;
use App\Services\Svg\ImagickSvgConverter;
use App\Services\Svg\SvgConversionService;
use App\Services\Svg\SvgInputValidator;
use PHPUnit\Framework\TestCase;
use Tests\Support\InteractsWithSvg;

class SvgConversionServiceTest extends TestCase
{
    use InteractsWithSvg;

    private function requireImagickOrSkip(): void
    {
        if (!class_exists(\Imagick::class)) {
            $this->markTestSkipped('Imagick extension not available; skipping SVG conversion tests.');
        }
    }

    public function test_convert_minimal_svg_to_png_bytes(): void
    {
        $this->requireImagickOrSkip();

        $svg = $this->makeSvg(50, 30, '#00ff00');
        $service = new SvgConversionService(new SvgInputValidator(), new ImagickSvgConverter());

        $png = $service->convertToPngBytes($svg);

        $this->assertNotEmpty($png, 'PNG bytes should not be empty');
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($png, 0, 8), 'PNG signature should match');
    }

    public function test_convert_minimal_svg_to_base64_png(): void
    {
        $this->requireImagickOrSkip();

        $svg = $this->makeSvg(20, 20, '#0000ff');
        $service = new SvgConversionService(new SvgInputValidator(), new ImagickSvgConverter());

        $b64 = $service->convertToBase64Png($svg);
        $this->assertNotEmpty($b64);
        $this->assertNotFalse(base64_decode($b64, true), 'Should be valid base64');

        $png = base64_decode($b64, true);
        $this->assertSame("\x89PNG\r\n\x1a\n", substr($png, 0, 8));
    }

    public function test_invalid_svg_throws_exception(): void
    {
        $this->requireImagickOrSkip();

        $invalidSvg = '<svg><unclosed></svg>';
        $service = new SvgConversionService(new SvgInputValidator(), new ImagickSvgConverter());

        $this->expectException(SvgConversionException::class);
        $service->convertToPngBytes($invalidSvg);
    }
}
