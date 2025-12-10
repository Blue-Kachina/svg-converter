<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureJsonRequest
{
    /**
     * Ensure requests to API expect and send JSON.
     * - Enforces Accept: application/json
     * - For methods with a body, enforces Content-Type: application/json
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Respect explicit Accept header (e.g., image/png for binary responses)
        $accept = strtolower((string) $request->headers->get('Accept', ''));
        $wantsBinaryPng = str_contains($accept, 'image/png');

        // For methods that typically have a body, validate Content-Type but allow common types
        if (in_array(strtoupper($request->getMethod()), ['POST', 'PUT', 'PATCH'])) {
            $contentType = strtolower((string) $request->headers->get('Content-Type', ''));

            // Allowed content types for API requests
            $allowedTypes = ['application/json', 'application/x-www-form-urlencoded', 'multipart/form-data', 'text/plain'];
            $isAllowed = false;
            foreach ($allowedTypes as $type) {
                if (str_contains($contentType, $type)) {
                    $isAllowed = true;
                    break;
                }
            }

            // If client explicitly wants binary PNG from /api/convert, allow even if not JSON
            $isConvertRoute = $request->is('api/convert');
            if (!$isAllowed && !($isConvertRoute && $wantsBinaryPng)) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'invalid_content_type',
                        'message' => 'Unsupported Content-Type. Use application/json or form-encoded.',
                    ],
                ], 415);
            }

            // When Content-Type is JSON, ensure body is valid JSON
            if (str_contains($contentType, 'application/json')) {
                try {
                    $request->json()->all();
                } catch (\Throwable $e) {
                    return response()->json([
                        'success' => false,
                        'error' => [
                            'code' => 'malformed_json',
                            'message' => 'Request body contains malformed JSON.',
                        ],
                    ], 400);
                }
            }
        }

        return $next($request);
    }
}
