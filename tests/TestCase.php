<?php

namespace Tests;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Mockery\Adapter\Phpunit\MockeryPHPUnitIntegration;

abstract class TestCase extends BaseTestCase
{
    use CreatesApplication;
    use MockeryPHPUnitIntegration;
    use RefreshDatabase; // Ensures fresh DB for each test case

    /**
     * Common JSON headers for API requests.
     */
    protected function jsonHeaders(): array
    {
        return [
            'Accept' => 'application/json',
            'Content-Type' => 'application/json',
        ];
    }
}
