<?php

namespace Tests\\Feature;

use Tests\TestCase;

class ApiHealthTest extends TestCase
{
    public function test_versioned_api_health_endpoint_returns_status(): void
    {
        $this->getJson('/api/v1/health')
            ->assertOk()
            ->assertJsonPath('status', 'ok')
            ->assertJsonStructure(['status', 'application', 'timestamp']);
    }
}
