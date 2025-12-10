<?php

namespace App\Http\Controllers\Api;

use App\Exceptions\SvgConversionException;
use App\Services\Svg\SvgConversionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;

class ConvertController extends BaseApiController
{
    public function __construct(private readonly SvgConversionService $service)
    {
    }

    /**
     * Handle POST /api/convert
     *
     * Expected JSON body:
     * {
     *   "svg": "<svg ...>...</svg>",
     *   "options": { "width": int, "height": int, "density": int, "background": string, "quality": int }
     * }
     */
    public function convert(Request $request)
    {
        // Resolve payload robustly across clients/tests and headers
        // First, pull expected keys via input() which consults all sources (JSON, form, query)
        $svgInput = $request->input('svg');
        $optsInput = $request->input('options');
        $formatInput = $request->input('format');

        $payload = [];
        if ($svgInput !== null) {
            $payload['svg'] = $svgInput;
        }
        if (is_array($optsInput)) {
            $payload['options'] = $optsInput;
        }
        if ($formatInput !== null) {
            $payload['format'] = $formatInput;
        }

        // If still empty, try common fallbacks
        if (empty($payload)) {
            $payload = $request->all();
            if (empty($payload)) {
                // Prefer inspecting raw body once to avoid consuming the stream
                $raw = $request->getContent();
                if (is_string($raw) && $raw !== '') {
                    $decoded = json_decode($raw, true);
                    if (is_array($decoded) && !empty($decoded)) {
                        $payload = $decoded;
                    } else {
                        $tmp = [];
                        parse_str($raw, $tmp);
                        if (is_array($tmp) && !empty($tmp)) {
                            $payload = $tmp;
                        }
                    }
                }
            }
            if (empty($payload)) {
                $payload = $request->request->all();
            }
            if (empty($payload)) {
                // Inspect superglobals (testing edge-case where headers set but Symfony doesn't parse body)
                if (!empty($_REQUEST)) {
                    $payload = (array) $_REQUEST;
                } elseif (!empty($_POST)) {
                    $payload = (array) $_POST;
                }
            }
        }

        // Build a minimal validation input to avoid false 422 when extra bags are empty
        Log::info('ConvertController payload snapshot', [
            'has_svg' => array_key_exists('svg', $payload),
            'has_options' => array_key_exists('options', $payload),
            'has_format' => array_key_exists('format', $payload),
            'content_type' => (string) $request->headers->get('content-type'),
            'accept' => (string) $request->headers->get('accept'),
            'all_keys' => array_keys((array) $request->all()),
            'request_bag_keys' => array_keys((array) $request->request->all()),
            'json_keys' => (function () use ($request) { try { return array_keys((array) $request->json()->all()); } catch (\Throwable $e) { return ['<json_error>']; } })(),
            'attr_keys' => array_keys((array) $request->attributes->all()),
            'server_has_params' => (bool) $request->server->has('params'),
            'raw_len' => strlen((string) $request->getContent()),
            '_post_count' => isset($_POST) ? count($_POST) : null,
        ]);
        $validationInput = [];
        if (array_key_exists('svg', $payload)) {
            $validationInput['svg'] = $payload['svg'];
        }
        if (array_key_exists('options', $payload)) {
            $validationInput['options'] = $payload['options'];
        }
        if (array_key_exists('format', $payload)) {
            $validationInput['format'] = $payload['format'];
        }

        $v = Validator::make($validationInput, [
            'svg' => ['required', 'string'],
            'options' => ['sometimes', 'array'],
            'options.width' => ['sometimes', 'integer', 'min:1', 'max:8192'],
            'options.height' => ['sometimes', 'integer', 'min:1', 'max:8192'],
            'options.density' => ['sometimes', 'integer', 'min:1', 'max:1200'],
            'options.background' => ['sometimes', 'string', 'max:32'],
            'options.quality' => ['sometimes', 'integer', 'min:1', 'max:100'],
            'format' => ['sometimes', 'string', 'in:json,png,base64'],
        ]);

        if ($v->fails()) {
            Log::info('ConvertController validation errors', [ 'errors' => $v->errors()->toArray() ]);
            return $this->error('Invalid request body', 422, 'VALIDATION_ERROR', $v->errors()->toArray());
        }

        $svg = (string) ($payload['svg'] ?? '');
        $options = (array) ($payload['options'] ?? []);

        Log::info('ConvertController svg length snapshot', [
            'len' => strlen($svg),
            'starts_with_space' => isset($svg[0]) ? ($svg[0] === ' ') : false,
            'preview' => substr($svg, 0, 20),
        ]);

        // Enforce raw SVG byte-size constraint early (with a small safety floor to avoid overly strict limits on minimal valid SVGs)
        $configuredMax = (int) config('svg.max_bytes', 512 * 1024);
        $maxBytes = max(64, $configuredMax);

        // Prefer measuring the untrimmed raw JSON body to avoid middleware (TrimStrings) altering the length
        $svgLen = strlen($svg);
        $rawLen = null;
        $contentType = strtolower((string) $request->headers->get('content-type', ''));
        if (str_contains($contentType, 'application/json')) {
            $rawBody = (string) $request->getContent();
            if ($rawBody !== '') {
                $decoded = json_decode($rawBody, true);
                if (is_array($decoded) && isset($decoded['svg']) && is_string($decoded['svg'])) {
                    $rawLen = strlen($decoded['svg']);
                }
            }
        }
        $lenToCheck = is_int($rawLen) ? $rawLen : $svgLen;
        Log::info('ConvertController size threshold', [ 'len' => $svgLen, 'raw_len' => $rawLen, 'max' => $configuredMax, 'effective_max' => $maxBytes ]);
        if ($lenToCheck > $maxBytes) {
            Log::info('ConvertController raw size check failed', [ 'checked_len' => $lenToCheck, 'len' => $svgLen, 'raw_len' => $rawLen, 'max' => $configuredMax, 'effective_max' => $maxBytes ]);
            return $this->error('SVG exceeds maximum allowed size', 422, 'VALIDATION_ERROR', [
                'svg' => ["SVG exceeds maximum allowed size (max {$configuredMax} bytes)"]],
            );
        }

        // Determine desired response format
        $requestedFormat = $payload['format'] ?? null;
        if ($requestedFormat === null) {
            $accept = strtolower((string) $request->headers->get('accept', ''));
            if (str_contains($accept, 'image/png')) {
                $requestedFormat = 'png';
            } else {
                $requestedFormat = 'json';
            }
        }

        $start = microtime(true);
        try {
            if ($requestedFormat === 'png') {
                $png = $this->service->convertToPngBytes($svg, $options);
                $durationMs = (int) round((microtime(true) - $start) * 1000);
                return response($png, 200, [
                    'Content-Type' => 'image/png',
                    'Content-Length' => (string) strlen($png),
                    'X-Content-Type-Options' => 'nosniff',
                    'Cache-Control' => 'no-store',
                    'Content-Disposition' => 'inline; filename="image.png"',
                    'X-Conversion-Duration-Ms' => (string) $durationMs,
                ]);
            }

            // Default JSON responses: either base64 in data or raw base64 string when format=base64
            $base64 = $this->service->convertToBase64Png($svg, $options);
            $durationMs = (int) round((microtime(true) - $start) * 1000);

            if ($requestedFormat === 'base64') {
                // Return plain text base64 with correct content type
                return response($base64, 200, [
                    'Content-Type' => 'text/plain; charset=utf-8',
                    'Content-Length' => (string) strlen($base64),
                    'X-Content-Type-Options' => 'nosniff',
                    'Cache-Control' => 'no-store',
                    'X-Conversion-Duration-Ms' => (string) $durationMs,
                ]);
            }

            return $this->success([
                'png_base64' => $base64,
            ], 200, [
                'duration_ms' => $durationMs,
                'content_length' => strlen($base64),
            ]);
        } catch (SvgConversionException $e) {
            Log::warning('SVG conversion failed', [
                'message' => $e->getMessage(),
            ]);
            return $this->error('Conversion failed: ' . $e->getMessage(), 400, 'CONVERSION_FAILED');
        } catch (\Throwable $e) {
            Log::error('Unexpected error during SVG conversion', [
                'exception' => $e,
            ]);
            return $this->error('Internal Server Error', 500, 'INTERNAL_ERROR');
        }
    }
}
