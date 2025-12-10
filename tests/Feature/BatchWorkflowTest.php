<?php

namespace Tests\Feature;

use App\Services\Svg\SvgConversionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\Support\InteractsWithSvg;
use Tests\TestCase;

class BatchWorkflowTest extends TestCase
{
    use RefreshDatabase;
    use InteractsWithSvg;

    protected function setUp(): void
    {
        parent::setUp();

        // Ensure auth is disabled for tests
        config()->set('svg.auth.enabled', false);

        // Use sync queue (phpunit.xml sets this), but we explicitly ensure here too
        config()->set('queue.default', 'sync');

        // Bind a fake conversion service that produces deterministic base64 results
        $this->app->bind(SvgConversionService::class, function () {
            return new class() extends SvgConversionService {
                public function __construct() {}
                public function convertToBase64Png(string $svg, array $options = []): string
                {
                    // Return a valid tiny PNG as base64 to simulate successful conversion
                    $b64 = 'iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAOa3j0YAAAAASUVORK5CYII=';
                    return $b64;
                }
            };
        });
    }

    public function test_batch_convert_and_status_workflow_succeeds(): void
    {
        $items = [
            ['svg' => $this->makeSvg(8, 8, '#111')],
            ['svg' => $this->makeSvg(12, 12, '#222')],
            ['svg' => $this->makeSvg(4, 4, '#333')],
        ];

        $resp = $this->postJson('/api/batch-convert', [ 'items' => $items ]);
        $resp->assertStatus(202)
            ->assertJsonPath('success', true)
            ->assertJsonPath('data.count', 3)
            ->assertJsonStructure(['data' => ['batch_id', 'count']]);

        $batchId = $resp->json('data.batch_id');
        $this->assertNotEmpty($batchId);

        // With sync queue, batch should be processed immediately after dispatch.
        $status = $this->getJson('/api/batch/' . $batchId)
            ->assertStatus(200)
            ->assertJsonPath('success', true)
            ->assertJsonStructure(['data' => ['items', 'progress', 'status']])
            ->json('data');

        $this->assertSame(3, $status['progress']['total']);
        $this->assertSame('finished', $status['status']);

        foreach ($status['items'] as $idx => $item) {
            $this->assertSame('succeeded', $item['status'], "Item {$idx} should have succeeded");
            $this->assertArrayHasKey('result_base64', $item);
            $this->assertNotEmpty($item['result_base64']);
        }
    }
}
