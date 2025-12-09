<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class TestJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /**
     * The number of times the job may be attempted.
     *
     * @var int
     */
    public int $tries = 3;

    /**
     * The number of seconds to wait before retrying the job.
     *
     * @return array<int, int>|int
     */
    public function backoff(): array|int
    {
        return [1, 5, 10];
    }

    /**
     * Create a new job instance.
     */
    public function __construct(public readonly string $message = 'Queue system is configured and working.')
    {
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        // Log a simple message to confirm the queue worker processed this job
        Log::info('TestJob processed', [
            'message' => $this->message,
            'job' => static::class,
            'connection' => config('queue.default'),
            'queue' => $this->queue,
        ]);
    }
}
