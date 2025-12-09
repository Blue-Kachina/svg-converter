<?php

namespace App\Http\Controllers\Api;

use Illuminate\Http\Request;

class PingController extends BaseApiController
{
    public function ping(Request $request)
    {
        return $this->success([
            'pong' => true,
            'time' => now()->toIso8601String(),
        ]);
    }
}
