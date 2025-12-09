<?php

namespace App\Services\Svg;

interface SvgConverterInterface
{
    /**
     * Convert the given sanitized SVG string into PNG binary bytes.
     *
     * @param string $sanitizedSvg SVG string that has been validated/sanitized
     * @param array{width?:int,height?:int,density?:int,background?:string,format?:string,quality?:int} $options
     * @return string PNG bytes
     */
    public function convertToPng(string $sanitizedSvg, array $options = []): string;
}
