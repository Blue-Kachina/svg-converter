<?php

namespace App\Http\Controllers\Api;

use App\Jobs\TestJob;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class OctaneDiagnosticsController extends BaseApiController
{
    public function diag(Request $request)
    {
        // Touch the DB to ensure connection works under long-running workers
        try {
            $dbOk = DB::select('select 1 as ok');
            $dbOk = (bool) ($dbOk[0]->ok ?? false);
        } catch (\Throwable $e) {
            $dbOk = false;
        }

        return $this->success([
            'octane' => isset($_SERVER['LARAVEL_OCTANE']),
            'server' => config('octane.server'),
            'worker_pid' => getmypid(),
            'time' => now()->toIso8601String(),
            'db_default' => config('database.default'),
            'db_connection_ok' => $dbOk,
            'log_channel' => config('logging.default'),
            'request_id' => $request->header('X-Request-Id') ?? bin2hex(random_bytes(6)),
        ]);
    }

    public function queueTest(Request $request)
    {
        $message = 'Octane queue test at '.now()->toIso8601String();
        TestJob::dispatch($message)->onQueue('default');

        Log::info('Dispatched TestJob from /api/octane/queue-test', [
            'message' => $message,
        ]);

        return $this->success([
            'dispatched' => true,
            'message' => $message,
            'queue' => 'default',
        ]);
    }
}
