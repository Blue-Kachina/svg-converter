<?php

namespace App\Exceptions;

use Exception;

class SvgConversionException extends Exception
{
    public static function imagickNotAvailable(): self
    {
        return new self('Imagick extension is not available for SVG to PNG conversion.');
    }

    public static function loadFailed(string $reason = ''): self
    {
        $msg = 'Failed to load SVG for conversion';
        if ($reason !== '') {
            $msg .= ": {$reason}";
        }
        return new self($msg);
    }

    public static function renderFailed(string $reason = ''): self
    {
        $msg = 'Failed to render PNG from SVG';
        if ($reason !== '') {
            $msg .= ": {$reason}";
        }
        return new self($msg);
    }
}
