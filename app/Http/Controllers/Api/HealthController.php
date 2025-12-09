<?php

namespace App\Http\Controllers\Api;

use App\Services\Health\HealthStatusService;
use Illuminate\Http\Request;

class HealthController extends BaseApiController
{
    public function __construct(private readonly HealthStatusService $health)
    {
    }

    public function health(Request $request)
    {
        $start = microtime(true);
        $checks = $this->health->checkAll();
        $statusCode = $checks['status'] === 'ok' ? 200 : 503;

        return $this->success([
            'status' => $checks['status'],
            'components' => $checks['components'],
        ], $statusCode, [
            'duration_ms' => (int) ((microtime(true) - $start) * 1000),
        ]);
    }
}
