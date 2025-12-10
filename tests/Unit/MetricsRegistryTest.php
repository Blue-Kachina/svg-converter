<?php

namespace Tests\Unit;

use App\Services\Metrics\MetricsRegistry;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

class MetricsRegistryTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        // Isolate metrics keys by unique prefix per test class
        config([
            'metrics.enabled' => true,
            'metrics.prefix' => 'test:metrics:'.uniqid('', true).':',
            'metrics.expose_endpoint' => true,
        ]);
    }

    public function test_counter_increment_and_read(): void
    {
        $r = new MetricsRegistry();
        $r->incrementCounter('demo_counter', ['label' => 'A'], 2);
        $r->incrementCounter('demo_counter', ['label' => 'A'], 3);

        $val = $r->getCounterValue('demo_counter', ['label' => 'A']);
        $this->assertSame(5, $val);

        $text = $r->exportPrometheus();
        $this->assertStringContainsString('demo_counter{label="A"} 5', $text);
    }

    public function test_histogram_bucketing_and_export(): void
    {
        config(['metrics.histogram_buckets' => [10, 100]]);
        $r = new MetricsRegistry();
        // Observe values across buckets
        $r->observeHistogram('latency_ms', 5.0, ['path' => '/x']);   // should count in 10,100,+Inf
        $r->observeHistogram('latency_ms', 50.0, ['path' => '/x']);  // should count in 100,+Inf
        $r->observeHistogram('latency_ms', 500.0, ['path' => '/x']); // only +Inf

        $text = $r->exportPrometheus();
        // Buckets
        $this->assertStringContainsString('latency_ms__bucket{le="10",path="/x"} 1', $text);
        $this->assertStringContainsString('latency_ms__bucket{le="100",path="/x"} 2', $text);
        $this->assertStringContainsString('latency_ms__bucket{le="+Inf",path="/x"} 3', $text);
        // Sum and count
        $this->assertStringContainsString('latency_ms__count{path="/x"} 3', $text);
        // Rounded sum: 5 + 50 + 500 = 555
        $this->assertStringContainsString('latency_ms__sum{path="/x"} 555', $text);
    }
}
