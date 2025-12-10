<?php

namespace App\Http\Controllers\Api;

use App\Jobs\ConvertSvgJob;
use App\Services\Results\ResultStore;
use Illuminate\Bus\Batch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Log;
use Illuminate\Validation\ValidationException;

class BatchConvertController extends BaseApiController
{
    /**
     * POST /api/batch-convert
     * Body: { items: [ { svg: string, options?: {...} }, ... ] }
     * Returns: { batch_id: string, count: int }
     */
    public function batchConvert(Request $request): JsonResponse
    {
        $data = $request->validate([
            'items' => ['required', 'array', 'min:1', 'max:50'],
            'items.*.svg' => ['required', 'string'],
            'items.*.options' => ['array'],
            'items.*.options.width' => ['integer', 'min:1', 'max:8192'],
            'items.*.options.height' => ['integer', 'min:1', 'max:8192'],
            'items.*.options.density' => ['integer', 'min:1', 'max:600'],
            'items.*.options.background' => ['string'],
            'items.*.options.quality' => ['integer', 'min:1', 'max:100'],
        ]);

        // Create a batch of jobs
        $jobs = [];
        foreach ($data['items'] as $i => $item) {
            $jobs[] = new ConvertSvgJob(index: $i, svg: $item['svg'], options: $item['options'] ?? []);
        }

        $store = ResultStore::fromConfig();
        /** @var Batch $batch */
        $batch = Bus::batch($jobs)
            ->allowFailures()
            ->then(function (Batch $batch) use ($store) {
                // mark batch completion in result store for quick polling
                $store->putBatchMeta($batch->id, [
                    'status' => 'finished',
                    'finished_at' => now()->toIso8601String(),
                ]);
            })
            ->catch(function (Batch $batch, \Throwable $e) {
                Log::error('Batch convert failed', [
                    'batch_id' => $batch->id,
                    'error' => $e->getMessage(),
                ]);
            })
            ->dispatch();

        // Pre-store a started flag
        $store->putBatchMeta($batch->id, [
            'status' => 'started',
            'created_at' => now()->toIso8601String(),
            'total' => count($jobs),
        ]);

        return $this->success([
            'batch_id' => $batch->id,
            'count' => count($jobs),
        ], 202);
    }

    /**
     * GET /api/batch/{id}
     * Returns batch progress and per-item statuses if available.
     */
    public function batchStatus(string $id): JsonResponse
    {
        $batch = Bus::findBatch($id);
        if (!$batch) {
            return $this->error('Batch not found', 404, 'batch_not_found');
        }

        $store = ResultStore::fromConfig();
        $meta = $store->getBatchMeta($id);

        $total = $batch->totalJobs;
        $processed = $batch->processedJobs();
        $failed = $batch->failedJobs;
        $pending = max(0, $total - $processed - $failed);

        // Gather per-item statuses from result store
        $items = [];
        for ($i = 0; $i < $total; $i++) {
            $payload = $store->getItem($id, $i);
            if ($payload) {
                $items[$i] = $payload;
            } else {
                $items[$i] = [ 'status' => 'pending' ];
            }
        }

        return $this->success([
            'batch_id' => $id,
            'status' => $batch->finished() ? 'finished' : ($batch->cancelled() ? 'cancelled' : 'running'),
            'progress' => [
                'total' => $total,
                'processed' => $processed,
                'failed' => $failed,
                'pending' => $pending,
            ],
            'items' => $items,
            'meta' => $meta,
        ]);
    }

    public static function batchCacheKey(string $batchId): string
    {
        return "svgconv:batch:{$batchId}:meta";
    }
}
