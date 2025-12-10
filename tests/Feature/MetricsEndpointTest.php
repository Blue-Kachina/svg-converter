<?php

namespace Tests\Feature;

use App\Services\Metrics\MetricsRegistry;
use Tests\TestCase;

class MetricsEndpointTest extends TestCase
{
    public function test_metrics_endpoint_renders_prometheus_text(): void
    {
        config([
            'metrics.enabled' => true,
            'metrics.expose_endpoint' => true,
            'metrics.prefix' => 'test:metrics:feature:'.uniqid('', true).':',
            'metrics.basic_auth' => null,
        ]);

        // Preload a metric so endpoint has content
        $r = new MetricsRegistry();
        $r->incrementCounter('svg_conversion_success_total', ['source' => 'test'], 1);

        $response = $this->get('/api/metrics');
        $response->assertStatus(200);
        // Content-Type may include a charset; assert prefix to be tolerant across versions
        $this->assertStringStartsWith('text/plain; version=0.0.4', $response->headers->get('Content-Type'));
        $response->assertSee('svg_conversion_success_total{source="test"} 1', false);
    }

    public function test_metrics_endpoint_respects_disable_flag(): void
    {
        config([
            'metrics.enabled' => false,
            'metrics.expose_endpoint' => true,
        ]);

        $response = $this->get('/api/metrics');
        $response->assertStatus(404);
    }
}
