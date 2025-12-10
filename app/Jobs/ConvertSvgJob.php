<?php

namespace App\Jobs;

use App\Exceptions\SvgConversionException;
use App\Services\Results\ResultStore;
use App\Services\Svg\SvgConversionService;
use Illuminate\Bus\Batchable;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ConvertSvgJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels, Batchable;

    public int $tries = 3;

    /**
     * The number of seconds the job can run before timing out.
     */
    public int $timeout = 60;

    /**
     * Exponential-ish backoff (in seconds) between attempts.
     *
     * @return array<int,int>|int
     */
    public function backoff(): array|int
    {
        return [2, 5, 15];
    }

    /**
     * @param int $index The index in the submitted items array
     * @param string $svg The SVG string to convert
     * @param array{width?:int,height?:int,density?:int,background?:string,quality?:int} $options
     */
    public function __construct(
        public int $index,
        public string $svg,
        public array $options = [],
    ) {
    }

    public function handle(SvgConversionService $service): void
    {
        if ($this->batch()?->cancelled()) {
            $this->storeStatus('cancelled');
            return;
        }

        Log::info('ConvertSvgJob started', [
            'batch_id' => $this->batchId ?? null,
            'index' => $this->index,
            'attempt' => $this->attempts(),
        ]);

        try {
            $base64 = $service->convertToBase64Png($this->svg, $this->options);
            $this->storeStatus('succeeded', $base64);
            Log::info('ConvertSvgJob succeeded', [
                'batch_id' => $this->batchId ?? null,
                'index' => $this->index,
                'bytes' => strlen(base64_decode($base64, true) ?: ''),
            ]);
        } catch (SvgConversionException $e) {
            Log::warning('ConvertSvgJob conversion error', [
                'batch_id' => $this->batchId ?? null,
                'index' => $this->index,
                'error' => $e->getMessage(),
            ]);
            $this->storeStatus('failed', null, $e->getMessage());
            throw $e; // allow retry policy to apply
        } catch (\Throwable $e) {
            Log::error('ConvertSvgJob unexpected failure', [
                'batch_id' => $this->batchId ?? null,
                'index' => $this->index,
                'error' => $e->getMessage(),
            ]);
            $this->storeStatus('failed', null, 'Unexpected error during conversion');
            throw $e; // allow retry policy to apply
        }
    }

    /**
     * The job has failed permanently after exhausting all retries.
     */
    public function failed(\Throwable $e): void
    {
        Log::error('ConvertSvgJob failed after retries', [
            'batch_id' => $this->batchId ?? null,
            'index' => $this->index,
            'error' => $e->getMessage(),
        ]);
        $this->storeStatus('failed', null, $e->getMessage());
    }

    private function storeStatus(string $status, ?string $base64 = null, ?string $error = null): void
    {
        $store = ResultStore::fromConfig();
        $payload = [
            'status' => $status,
            'error' => $error,
        ];
        if ($base64 !== null) {
            $payload['result_base64'] = $base64;
            $payload['result_bytes'] = strlen(base64_decode($base64, true) ?: '');
        }
        $store->putItem((string) $this->batchId, $this->index, $payload);
    }
}
