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
        $reqId = $request->headers->get('X-Request-Id') ?? uniqid('req_', true);
        $reqBytes = $request->headers->get('Content-Length');
        $reqBytes = is_numeric($reqBytes) ? (int) $reqBytes : null;
        Log::info('HTTP request received', [
            'method' => $request->getMethod(),
            'path' => $request->getPathInfo(),
            'ip' => $request->ip(),
            'user_agent' => substr((string) $request->userAgent(), 0, 255),
            'request_id' => $reqId,
            'request_bytes' => $reqBytes,
        ]);

        /** @var Response $response */
        $response = $next($request);

        $durationMs = (int) round((microtime(true) - $start) * 1000);
        $resBytes = null;
        try {
            $resLenHeader = $response->headers->get('Content-Length');
            $resBytes = is_numeric($resLenHeader) ? (int) $resLenHeader : null;
            if ($resBytes === null) {
                $content = $response->getContent();
                if (is_string($content)) {
                    $resBytes = strlen($content);
                }
            }
        } catch (\Throwable $e) {
            // ignore
        }

        Log::info('HTTP response sent', [
            'status' => $response->getStatusCode(),
            'path' => $request->getPathInfo(),
            'duration_ms' => $durationMs,
            'request_id' => $reqId,
            'response_bytes' => $resBytes,
        ]);

        // Emit metrics (best-effort)
        try {
            $registry = new \App\Services\Metrics\MetricsRegistry();
            if ($registry->enabled()) {
                $method = strtoupper($request->getMethod());
                $status = $response->getStatusCode();
                $statusClass = floor($status / 100) . 'xx';
                $path = $request->getPathInfo();

                $registry->observeHistogram('http_request_duration_ms', (float) $durationMs, [
                    'method' => $method,
                    'path' => $path,
                ]);
                $registry->incrementCounter('http_requests_total', [
                    'method' => $method,
                    'status_class' => $statusClass,
                    'path' => $path,
                ], 1);
                if (is_int($resBytes)) {
                    $registry->incrementCounter('http_response_bytes_total', [
                        'method' => $method,
                        'path' => $path,
                    ], $resBytes);
                }
                if ($status >= 400) {
                    $registry->incrementCounter('http_errors_total', [
                        'method' => $method,
                        'status_class' => $statusClass,
                        'path' => $path,
                    ], 1);
                }
            }
        } catch (\Throwable $e) {
            // ignore metrics failures
        }

        return $response;
    }
}
