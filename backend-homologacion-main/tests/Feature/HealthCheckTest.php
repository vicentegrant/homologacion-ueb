<?php

namespace Tests\Feature;

use Tests\TestCase;

class HealthCheckTest extends TestCase
{
    public function test_health_endpoint_returns_the_expected_json(): void
    {
        $response = $this->getJson('/api/v1/health');

        $response->assertStatus(200);
        $response->assertExactJson([
            'success' => true,
            'message' => 'API backend operativa',
            'data' => [
                'service' => 'backend-homologacion',
                'status' => 'ok',
            ],
        ]);
    }
}
