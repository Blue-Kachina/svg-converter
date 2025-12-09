<?php

namespace App\Http\Controllers\Api;

use App\Services\Health\HealthStatusService;
use Illuminate\Http\Request;

class StatusController extends BaseApiController
{
    public function __construct(private readonly HealthStatusService $health)
    {
    }

    public function status(Request $request)
    {
        $start = microtime(true);
        $diag = $this->health->diagnostics();
        $statusCode = $diag['status'] === 'ok' ? 200 : 503;

        return $this->success($diag, $statusCode, [
            'duration_ms' => (int) ((microtime(true) - $start) * 1000),
        ]);
    }
}
