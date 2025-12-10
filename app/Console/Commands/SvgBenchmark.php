<?php

namespace App\Console\Commands;

use App\Services\Svg\SvgConversionService;
use Illuminate\Console\Command;
use App\Support\Stats;

class SvgBenchmark extends Command
{
    protected $signature = 'svg:benchmark
        {--iterations=50 : Number of conversions to run}
        {--width=256 : SVG width}
        {--height=256 : SVG height}
        {--density=300 : Render density (DPI)}
        {--quality=90 : Initial PNG quality}
        {--background=#ffffff : Background fill}
        {--warmup=5 : Discard first N warm-up runs from stats}
    ';

    protected $description = 'Benchmark SVG->PNG conversion speed and memory usage within the app process.';

    public function handle(SvgConversionService $service): int
    {
        $iterations = (int) $this->option('iterations');
        $warmup = (int) $this->option('warmup');
        $width = (int) $this->option('width');
        $height = (int) $this->option('height');
        $density = (int) $this->option('density');
        $quality = (int) $this->option('quality');
        $background = (string) $this->option('background');

        if ($iterations < 1) {
            $this->error('Iterations must be >= 1');
            return self::FAILURE;
        }
        $warmup = max(0, min($warmup, $iterations - 1));

        $svg = $this->makeSvg($width, $height, '#09f');
        $opts = [
            'width' => $width,
            'height' => $height,
            'density' => $density,
            'quality' => $quality,
            'background' => $background,
        ];

        $this->info("Running {$iterations} conversions (warmup={$warmup})...");
        $durations = [];
        $sizes = [];
        $startAll = microtime(true);
        $peakMem = 0;

        for ($i = 0; $i < $iterations; $i++) {
            $t0 = microtime(true);
            $png = $service->convertToPngBytes($svg, $opts);
            $dt = (microtime(true) - $t0) * 1000.0; // ms
            if ($i >= $warmup) {
                $durations[] = $dt;
                $sizes[] = strlen($png);
            }
            $peakMem = max($peakMem, memory_get_peak_usage(true));
        }

        $elapsed = (microtime(true) - $startAll) * 1000.0;
        $stats = Stats::summarize($durations);
        $sizeStats = Stats::summarize(array_map(fn($b) => (float)$b, $sizes));

        $this->line('--- Results ---');
        $this->line(sprintf('Total time: %.2f ms (%.2f ms/op avg incl. warmup)', $elapsed, $elapsed / $iterations));
        $this->line(sprintf('Latency (ms): min=%.2f avg=%.2f p50=%.2f p90=%.2f p95=%.2f p99=%.2f max=%.2f',
            $stats['min'], $stats['avg'], $stats['p50'], $stats['p90'], $stats['p95'], $stats['p99'], $stats['max']));
        $this->line(sprintf('Output size (bytes): min=%.0f avg=%.1f p95=%.0f max=%.0f',
            $sizeStats['min'], $sizeStats['avg'], $sizeStats['p95'], $sizeStats['max']));
        $this->line(sprintf('Peak memory (process): %.2f MB', $peakMem / (1024 * 1024)));

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
