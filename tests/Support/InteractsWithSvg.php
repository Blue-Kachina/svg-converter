<?php

namespace Tests\Support;

trait InteractsWithSvg
{
    /**
     * Returns a minimal valid SVG string of a given size and color.
     */
    protected function makeSvg(
        int $width = 100,
        int $height = 100,
        string $fill = '#000000'
    ): string {
        $w = max(1, $width);
        $h = max(1, $height);
        $fill = htmlspecialchars($fill, ENT_QUOTES, 'UTF-8');

        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="{$w}" height="{$h}" viewBox="0 0 {$w} {$h}">
            <rect width="100%" height="100%" fill="{$fill}" />
        </svg>
        SVG;
    }

    /**
     * Returns a potentially malicious SVG string for security testing scenarios.
     */
    protected function makeMaliciousSvg(): string
    {
        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="100" height="100">
            <script>alert('xss');</script>
            <foreignObject width="100" height="100">
                <body xmlns="http://www.w3.org/1999/xhtml">
                    <img src="javascript:alert('xss')" />
                </body>
            </foreignObject>
        </svg>
        SVG;
    }
}
