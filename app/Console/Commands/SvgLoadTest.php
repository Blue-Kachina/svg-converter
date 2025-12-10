<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;
use App\Support\Stats;

class SvgLoadTest extends Command
{
    protected $signature = 'svg:load-test
        {--url=http://localhost/api/convert : Base URL to POST to}
        {--requests=100 : Total number of requests}
        {--concurrency=10 : Number of concurrent requests}
        {--timeout=10 : Per-request timeout seconds}
        {--format=json : Response format: json|base64|png}
        {--width=128}
        {--height=128}
        {--density=300}
        {--quality=85}
    ';

    protected $description = 'Run a simple load test against the /api/convert endpoint and report latency & success metrics.';

    public function handle(): int
    {
        $url = (string) $this->option('url');
        $total = max(1, (int) $this->option('requests'));
        $conc = max(1, (int) $this->option('concurrency'));
        $timeout = max(1, (int) $this->option('timeout'));
        $format = (string) $this->option('format');
        $width = (int) $this->option('width');
        $height = (int) $this->option('height');
        $density = (int) $this->option('density');
        $quality = (int) $this->option('quality');

        if (!in_array($format, ['json', 'png', 'base64'], true)) {
            $this->error('Invalid --format. Use json|png|base64');
            return self::FAILURE;
        }
        if ($conc > $total) $conc = $total;

        $svg = $this->makeSvg($width, $height, '#09f');
        $payload = [
            'svg' => $svg,
            'options' => compact('width', 'height', 'density', 'quality'),
            'format' => $format,
        ];

        $this->info("Running {$total} requests to {$url} with concurrency={$conc}, timeout={$timeout}s...");
        $latencies = [];
        $statuses = [];
        $errors = [];
        $ok = 0;
        $fail = 0;
        $tAll = microtime(true);

        $remaining = $total;
        while ($remaining > 0) {
            $batch = min($conc, $remaining);
            $remaining -= $batch;

            $responses = Http::timeout($timeout)
                ->accept($format === 'png' ? 'image/png' : 'application/json')
                ->pool(function ($pool) use ($batch, $url, $payload) {
                    for ($i = 0; $i < $batch; $i++) {
                        $pool->as('r'.$i)->post($url, $payload);
                    }
                });

            foreach ($responses as $res) {
                $transferTime = $res->transferStats?->getTransferTime() ?? null;
                if ($transferTime !== null) {
                    $latencies[] = $transferTime * 1000.0; // ms
                }
                $code = $res->status();
                $statuses[$code] = ($statuses[$code] ?? 0) + 1;
                if ($res->successful()) {
                    $ok++;
                } else {
                    $fail++;
                    $err = $res->json('error.code') ?? (string)$code;
                    $errors[$err] = ($errors[$err] ?? 0) + 1;
                }
            }
        }

        $elapsed = (microtime(true) - $tAll) * 1000.0;
        $qps = ($total / max(0.001, $elapsed)) * 1000.0;
        $stats = Stats::summarize($latencies);

        $this->line('--- Summary ---');
        $this->line(sprintf('Total: %d, Success: %d, Fail: %d, Success Rate: %.1f%%', $total, $ok, $fail, 100.0 * $ok / $total));
        $this->line(sprintf('Overall time: %.1f ms, Throughput: %.2f req/s', $elapsed, $qps));
        $this->line(sprintf('Latency (ms): min=%.2f avg=%.2f p50=%.2f p90=%.2f p95=%.2f p99=%.2f max=%.2f',
            $stats['min'], $stats['avg'], $stats['p50'], $stats['p90'], $stats['p95'], $stats['p99'], $stats['max']));
        if ($fail > 0) {
            $this->line('Errors:');
            foreach ($errors as $code => $count) {
                $this->line("  {$code}: {$count}");
            }
        }
        $this->line('Status codes:');
        ksort($statuses);
        foreach ($statuses as $code => $count) {
            $this->line("  {$code}: {$count}");
        }

        return self::SUCCESS;
    }

    private function makeSvg(int $w, int $h, string $fill): string
    {
        $w = max(1, $w); $h = max(1, $h);
        $fill = htmlspecialchars($fill, ENT_QUOTES, 'UTF-8');
        return <<<SVG
        <svg xmlns="http://www.w3.org/2000/svg" width="{$w}" height="{$h}" viewBox="0 0 {$w} {$h}">
            <rect width="100%" height="100%" fill="{$fill}" />
        </svg>
        SVG;
    }
}
