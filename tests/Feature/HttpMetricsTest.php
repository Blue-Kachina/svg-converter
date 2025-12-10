<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\Metrics\MetricsRegistry;

class HttpMetricsTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        config([
            'metrics.enabled' => true,
            'metrics.expose_endpoint' => true,
            'metrics.prefix' => 'test:httpmetrics:'.uniqid('', true).':',
        ]);
    }

    public function test_http_metrics_are_recorded_for_success_and_error(): void
    {
        // Hit a successful endpoint
        $this->get('/api/ping')->assertStatus(200);

        // Hit an endpoint that returns 422 (missing svg)
        $this->postJson('/api/convert', [])->assertStatus(422);

        // Read metrics
        $resp = $this->get('/api/metrics');
        $resp->assertStatus(200);
        $text = $resp->getContent();

        // We expect counters for http_requests_total and histogram lines
        $this->assertStringContainsString('http_requests_total{method="GET",path="/api/ping",status_class="2xx"}', $text);
        $this->assertStringContainsString('http_requests_total{method="POST",path="/api/convert",status_class="4xx"}', $text);
        $this->assertStringContainsString('http_request_duration_ms__count{method="GET",path="/api/ping"}', $text);
    }
}
