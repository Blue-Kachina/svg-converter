<?php

// Provide a lightweight global config() helper for tests that don't boot Laravel.
// Only returns keys used by SvgConversionService in these tests.
namespace {
    if (!function_exists('config')) {
        function config($key = null)
        {
            if ($key === 'svg') {
                return [
                    // Disable caching to avoid needing Laravel Cache in plain PHPUnit
                    'cache' => [
                        'enabled' => false,
                    ],
                    'output' => [
                        // Keep default small to exercise size enforcement in a dedicated test
                        'max_png_bytes' => 50,
                        'oversize_strategy' => 'reject',
                        'quality_step' => 10,
                        'min_quality' => 40,
                    ],
                ];
            }
            return null;
        }
    }
}

namespace Tests\Unit {

use App\Exceptions\SvgConversionException;
use App\Services\Svg\SvgConversionService;
use App\Services\Svg\SvgConverterInterface;
use App\Services\Svg\SvgInputValidator;
use PHPUnit\Framework\TestCase;

class SvgConversionServiceEncodingTest extends TestCase
{
    private function minimalValidSvg(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10" fill="#000"/></svg>';
    }

    private function pngWithLength(int $len): string
    {
        // Start with PNG signature (magic bytes), pad with 'A' to reach desired length
        $header = "\x89PNG\r\n\x1a\n"; // 8 bytes
        $pad = str_repeat('A', max(0, $len - strlen($header)));
        return $header . $pad;
    }

    public function test_convert_to_base64_png_returns_expected_base64(): void
    {
        // Return a small valid-looking PNG (just signature + padding) well under max limit
        $pngBytes = $this->pngWithLength(30);

        $converter = new class($pngBytes) implements SvgConverterInterface {
            public function __construct(private string $bytes) {}
            public function convertToPng(string $svg, array $options = []): string
            {
                return $this->bytes;
            }
        };

        $service = new SvgConversionService(new SvgInputValidator(), $converter);

        $svg = $this->minimalValidSvg();
        $base64 = $service->convertToBase64Png($svg);

        $this->assertSame(base64_encode($pngBytes), $base64, 'Base64 output should match PNG bytes produced by converter');
    }

    public function test_png_exceeding_max_size_is_rejected(): void
    {
        // Create an oversize PNG (> 50 bytes per our test config())
        $oversizePng = $this->pngWithLength(80);

        $converter = new class($oversizePng) implements SvgConverterInterface {
            public function __construct(private string $bytes) {}
            public function convertToPng(string $svg, array $options = []): string
            {
                return $this->bytes;
            }
        };

        $service = new SvgConversionService(new SvgInputValidator(), $converter);

        $this->expectException(SvgConversionException::class);
        $this->expectExceptionMessage('PNG exceeds maximum allowed size');

        $service->convertToPngBytes($this->minimalValidSvg());
    }
}
}
