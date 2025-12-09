<?php

namespace App\Services\Svg;

use App\Exceptions\SvgConversionException;
use Imagick;
use ImagickException;
use ImagickPixel;
use Throwable;

class ImagickSvgConverter implements SvgConverterInterface
{
    /** @inheritDoc */
    public function convertToPng(string $sanitizedSvg, array $options = []): string
    {
        if (!class_exists(Imagick::class)) {
            throw SvgConversionException::imagickNotAvailable();
        }

        $hasConfig = function_exists('config');
        $density = isset($options['density'])
            ? (int) $options['density']
            : (int) ($hasConfig ? config('svg.conversion.density', 144) : 144);
        $bg = $options['background']
            ?? (string) ($hasConfig ? config('svg.conversion.background', 'transparent') : 'transparent');
        $targetWidth = isset($options['width']) ? (int) $options['width'] : null;
        $targetHeight = isset($options['height']) ? (int) $options['height'] : null;
        $quality = isset($options['quality'])
            ? (int) $options['quality']
            : (int) ($hasConfig ? config('svg.conversion.quality', 90) : 90);

        try {
            $imagick = new Imagick();
            // Set density (DPI) to control rasterization size
            if ($density > 0) {
                $imagick->setResolution($density, $density);
            }

            // Background (transparent by default)
            $pixel = new ImagickPixel($bg);
            $imagick->setBackgroundColor($pixel);

            // Read SVG from memory
            $ok = $imagick->readImageBlob($sanitizedSvg, 'svg');
            if (!$ok) {
                throw SvgConversionException::loadFailed('Imagick could not read SVG blob');
            }

            // Flatten to raster
            $imagick->setImageFormat('png');

            if ($targetWidth !== null || $targetHeight !== null) {
                $w = $targetWidth ?? 0; // 0 means best fit preserves aspect
                $h = $targetHeight ?? 0;
                $imagick->resizeImage($w, $h, Imagick::FILTER_LANCZOS, 1, true);
            }

            if ($quality > 0) {
                $imagick->setImageCompressionQuality($quality);
            }

            // Ensure proper alpha channel handling for transparency
            if (method_exists($imagick, 'setImageAlphaChannel')) {
                $imagick->setImageAlphaChannel(Imagick::ALPHACHANNEL_ACTIVATE);
            }

            $png = $imagick->getImagesBlob();

            // Basic PNG signature validation
            if (substr($png, 0, 8) !== "\x89PNG\r\n\x1a\n") {
                // Some environments may produce a multi-image blob; ensure we export single image
                $imagick = $imagick->coalesceImages();
                $imagick->setImageFormat('png');
                $png = $imagick->getImageBlob();
            }

            if ($png === '' || substr($png, 0, 8) !== "\x89PNG\r\n\x1a\n") {
                throw SvgConversionException::renderFailed('Output is not a valid PNG');
            }

            return $png;
        } catch (ImagickException $e) {
            throw SvgConversionException::renderFailed($e->getMessage());
        } catch (Throwable $e) {
            throw SvgConversionException::renderFailed($e->getMessage());
        }
    }
}
