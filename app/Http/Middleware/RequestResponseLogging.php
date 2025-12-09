<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Symfony\Component\HttpFoundation\Response;

class RequestResponseLogging
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $start = microtime(true);

        // Capture minimal request info (avoid logging bodies by default)
        Log::info('HTTP request received', [
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'request_id' => $request->headers->get('X-Request-Id') ?? uniqid('req_', true),
        ]);

        /** @var Response $response */
        $response = $next($request);

        $durationMs = (int) round((microtime(true) - $start) * 1000);

        Log::info('HTTP response sent', [
            'status' => $response->getStatusCode(),
            'path' => $request->getPathInfo(),
            'duration_ms' => $durationMs,
            'request_id' => $request->headers->get('X-Request-Id') ?? null,
        ]);

        return $response;
    }
}
