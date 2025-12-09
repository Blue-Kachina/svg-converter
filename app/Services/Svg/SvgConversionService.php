<?php

namespace App\Services\Svg;

use App\Exceptions\SvgConversionException;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class SvgConversionService
{
    public function __construct(
        private readonly SvgInputValidator $validator,
        private readonly SvgConverterInterface $converter,
    ) {
    }

    /**
     * Convert an SVG string to PNG bytes after validation/sanitization.
     * Adds caching for duplicate conversions and enforces output size policy.
     *
     * @param array{width?:int,height?:int,density?:int,background?:string,quality?:int} $options
     * @return string PNG binary
     * @throws SvgConversionException
     */
    public function convertToPngBytes(string $svg, array $options = []): string
    {
        // Safely read config with fallbacks for non-Laravel contexts
        $cfg = function_exists('config') ? (array) config('svg') : [];

        $result = $this->validator->validate($svg, [
            'max_bytes' => $cfg['max_bytes'] ?? (512 * 1024),
            'max_width' => $cfg['max_width'] ?? 4096,
            'max_height' => $cfg['max_height'] ?? 4096,
            'allow_remote_refs' => $cfg['allow_remote_refs'] ?? false,
        ]);

        if (!$result['valid'] || empty($result['sanitized'])) {
            $reason = 'Invalid SVG input';
            if (!empty($result['errors'])) {
                $reason .= ': ' . implode('; ', $result['errors']);
            }
            throw SvgConversionException::loadFailed($reason);
        }

        $sanitized = $result['sanitized'];

        // Caching for duplicate conversions
        $cacheEnabled = (bool) ($cfg['cache']['enabled'] ?? false);
        $cacheTtl = (int) ($cfg['cache']['ttl'] ?? 3600);
        $cachePrefix = (string) ($cfg['cache']['prefix'] ?? 'svgconv:');
        $normalizedOptions = $this->normalizeOptions($options);
        // v2: values are stored as base64 strings for portability across cache stores
        $cacheKey = $cachePrefix . 'v2:' . hash('sha256', $sanitized . '|' . json_encode($normalizedOptions));

        if ($cacheEnabled) {
            $cached = Cache::get($cacheKey);
            if (is_string($cached) && $cached !== '') {
                $decoded = base64_decode($cached, true);
                if ($decoded !== false && substr($decoded, 0, 8) === "\x89PNG\r\n\x1a\n") {
                    return $decoded;
                }
            }
        }

        // Render
        $png = $this->converter->convertToPng($sanitized, $normalizedOptions);

        // Enforce output size / optimize if necessary
        $outCfg = (array) ($cfg['output'] ?? []);
        $maxBytes = (int) ($outCfg['max_png_bytes'] ?? 0);
        if ($maxBytes > 0 && strlen($png) > $maxBytes) {
            $strategy = (string) ($outCfg['oversize_strategy'] ?? 'shrink_quality');
            if ($strategy === 'shrink_quality') {
                $step = max(1, (int) ($outCfg['quality_step'] ?? 10));
                $minQ = max(1, (int) ($outCfg['min_quality'] ?? 40));
                $q = (int) ($normalizedOptions['quality'] ?? ($cfg['conversion']['quality'] ?? 90));
                while ($q > $minQ) {
                    $q = max($minQ, $q - $step);
                    $tryOpts = $normalizedOptions;
                    $tryOpts['quality'] = $q;
                    $png = $this->converter->convertToPng($sanitized, $tryOpts);
                    if (strlen($png) <= $maxBytes) {
                        break;
                    }
                    if ($q === $minQ) {
                        break;
                    }
                }
                if (strlen($png) > $maxBytes) {
                    throw SvgConversionException::renderFailed('PNG exceeds maximum allowed size');
                }
            } else { // reject
                throw SvgConversionException::renderFailed('PNG exceeds maximum allowed size');
            }
        }

        if ($cacheEnabled) {
            try {
                $encoded = base64_encode($png);
                Cache::put($cacheKey, $encoded, $cacheTtl);
            } catch (\Throwable $e) {
                // Do not fail the request if caching fails (e.g., incompatible backend)
                Log::warning('PNG cache put failed', [
                    'key' => $cacheKey,
                    'message' => $e->getMessage(),
                ]);
            }
        }

        return $png;
    }

    /**
     * Convert an SVG string to a base64-encoded PNG string (without data URI prefix).
     *
     * @param array{width?:int,height?:int,density?:int,background?:string,quality?:int} $options
     * @return string base64 string of PNG bytes
     */
    public function convertToBase64Png(string $svg, array $options = []): string
    {
        $png = $this->convertToPngBytes($svg, $options);
        return base64_encode($png);
    }

    /**
     * Normalize options into a deterministic subset for hashing and passing to converters.
     * @param array $options
     * @return array
     */
    private function normalizeOptions(array $options): array
    {
        $allowed = ['width', 'height', 'density', 'background', 'quality'];
        $normalized = [];
        foreach ($allowed as $key) {
            if (array_key_exists($key, $options)) {
                $normalized[$key] = $options[$key];
            }
        }
        ksort($normalized);
        return $normalized;
    }
}
