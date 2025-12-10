<?php

namespace Tests\Unit;

use App\Exceptions\SvgConversionException;
use App\Services\Svg\SvgConversionService;
use App\Services\Svg\SvgConverterInterface;
use App\Services\Svg\SvgInputValidator;
use PHPUnit\Framework\TestCase;

class SvgConversionServiceTest extends TestCase
{
    private function makeValidSvg(): string
    {
        return '<svg xmlns="http://www.w3.org/2000/svg" width="10" height="10"><rect width="10" height="10" fill="#000"/></svg>';
    }

    /**
     * A fake converter that returns invalid (non-PNG) bytes
     */
    private function makeBadConverter(): SvgConverterInterface
    {
        return new class implements SvgConverterInterface {
            public function convertToPng(string $svg, array $options = []): string
            {
                return 'NOTPNG';
            }
        };
    }

    public function test_converter_returning_non_png_bytes_is_rejected(): void
    {
        $service = new SvgConversionService(new SvgInputValidator(), $this->makeBadConverter());

        $this->expectException(SvgConversionException::class);
        $this->expectExceptionMessage('valid PNG');

        $service->convertToPngBytes($this->makeValidSvg());
    }
}
