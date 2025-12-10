<?php

namespace App\Http\Controllers\Api;

use App\Services\Metrics\MetricsRegistry;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class MetricsController extends BaseApiController
{
    public function metrics(Request $request)
    {
        $enabled = (bool) config('metrics.enabled');
        $expose = (bool) config('metrics.expose_endpoint');
        if (!$enabled || !$expose) {
            return response('Not Found', 404);
        }

        // Optional basic auth using METRICS_BASIC_AUTH="user:pass"
        $pair = config('metrics.basic_auth');
        if (!empty($pair)) {
            $auth = $request->header('Authorization');
            if (!is_string($auth) || !str_starts_with($auth, 'Basic ')) {
                return response('Unauthorized', 401, ['WWW-Authenticate' => 'Basic']);
            }
            $decoded = base64_decode(substr($auth, 6), true);
            if ($decoded === false || $decoded !== $pair) {
                return response('Forbidden', 403);
            }
        }

        $registry = new MetricsRegistry();
        $body = $registry->exportPrometheus();
        return response($body, 200, ['Content-Type' => 'text/plain; version=0.0.4']);
    }
}
