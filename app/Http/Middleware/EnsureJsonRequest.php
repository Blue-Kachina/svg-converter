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
        // Force JSON responses
        $request->headers->set('Accept', 'application/json');

        // For methods that typically have a body, ensure JSON Content-Type
        if (in_array(strtoupper($request->getMethod()), ['POST', 'PUT', 'PATCH'])) {
            $contentType = (string) $request->headers->get('Content-Type', '');
            if (!str_contains(strtolower($contentType), 'application/json')) {
                return response()->json([
                    'success' => false,
                    'error' => [
                        'code' => 'invalid_content_type',
                        'message' => 'Content-Type must be application/json',
                    ],
                ], 415);
            }

            // If JSON is present but malformed, Laravel will throw a SyntaxError during parsing. We can try to access input to trigger it.
            try {
                // Attempt to decode input to ensure valid JSON
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

        return $next($request);
    }
}
