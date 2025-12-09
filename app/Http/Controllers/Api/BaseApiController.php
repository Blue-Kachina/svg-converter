<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\JsonResponse;
use Illuminate\Routing\Controller as BaseController;

class BaseApiController extends BaseController
{
    /**
     * Build a standardized successful JSON response.
     *
     * @param array|object|null $data
     * @param int $status
     * @param array $meta
     */
    protected function success(array|object|null $data = null, int $status = 200, array $meta = []): JsonResponse
    {
        $payload = [
            'success' => true,
            'data' => $data,
        ];

        if (!empty($meta)) {
            $payload['meta'] = $meta;
        }

        return response()->json($payload, $status);
    }

    /**
     * Build a standardized error JSON response.
     *
     * @param string $message
     * @param int $status
     * @param string|null $code
     * @param array $errors
     */
    protected function error(string $message, int $status = 400, ?string $code = null, array $errors = []): JsonResponse
    {
        $payload = [
            'success' => false,
            'error' => array_filter([
                'code' => $code,
                'message' => $message,
                'errors' => !empty($errors) ? $errors : null,
            ], static fn ($v) => !is_null($v)),
        ];

        return response()->json($payload, $status);
    }
}
