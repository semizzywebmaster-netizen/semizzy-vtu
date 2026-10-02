<?php

namespace Tests\Feature;

use App\Services\System\SystemHealthService;
use Tests\TestCase;

class SystemHealthTest extends TestCase
{
    public function test_health_service_returns_real_runtime_checks(): void
    {
        $result = app(SystemHealthService::class)->check();

        $this->assertArrayHasKey('status', $result);
        $this->assertContains($result['status'], ['PASS', 'WARN', 'FAIL']);
        $this->assertNotEmpty($result['checks']);

        $keys = collect($result['checks'])->pluck('key');
        $this->assertTrue($keys->contains('php'));
        $this->assertTrue($keys->contains('database'));
        $this->assertTrue($keys->contains('storage'));
        $this->assertTrue($keys->contains('cache'));
    }
}
