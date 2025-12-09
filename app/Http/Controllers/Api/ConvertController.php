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
        $payload = $request->all();

        $v = Validator::make($payload, [
            'svg' => ['required', 'string'],
            'options' => ['sometimes', 'array'],
            'options.width' => ['sometimes', 'integer', 'min:1', 'max:8192'],
            'options.height' => ['sometimes', 'integer', 'min:1', 'max:8192'],
            'options.density' => ['sometimes', 'integer', 'min:1', 'max:1200'],
            'options.background' => ['sometimes', 'string', 'max:32'],
            'options.quality' => ['sometimes', 'integer', 'min:1', 'max:100'],
        ]);

        if ($v->fails()) {
            return $this->error('Invalid request body', 422, 'VALIDATION_ERROR', $v->errors()->toArray());
        }

        $svg = (string) $payload['svg'];
        $options = (array) ($payload['options'] ?? []);

        $start = microtime(true);
        try {
            $base64 = $this->service->convertToBase64Png($svg, $options);
            $durationMs = (int) round((microtime(true) - $start) * 1000);

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
