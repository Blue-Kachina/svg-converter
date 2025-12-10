<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class ApiKeyAuth
{
    /**
     * Simple header-based API key check. When disabled via config('svg.auth.enabled'),
     * it becomes a no-op.
     */
    public function handle(Request $request, Closure $next): Response
    {
        if (!config('svg.auth.enabled', false)) {
            return $next($request);
        }

        $header = (string) config('svg.auth.header', 'X-API-Key');
        $rawKeys = (string) config('svg.auth.keys', '');
        $allowed = array_filter(array_map('trim', explode(',', $rawKeys)));

        $provided = $request->headers->get($header);

        if ($provided === null || $provided === '' || (!empty($allowed) && !in_array($provided, $allowed, true))) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'unauthenticated',
                    'message' => 'Invalid or missing API key.',
                ],
            ], 401);
        }

        return $next($request);
    }
}
