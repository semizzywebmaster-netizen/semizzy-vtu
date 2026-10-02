<?php

namespace Tests\Feature;

use App\Services\System\SystemHealthService;
use Illuminate\Contracts\Cache\Repository as CacheRepository;
use Illuminate\Database\DatabaseManager;
use Mockery;
use RuntimeException;
use Tests\TestCase;

class SystemHealthTest extends TestCase
{
    public function test_database_exception_details_are_not_exposed_to_admin_ui(): void
    {
        $database = Mockery::mock(DatabaseManager::class);
        $database->shouldReceive('connection')->once()->andThrow(new RuntimeException('SQLSTATE password=super-secret database=private-db'));
        $cache = Mockery::mock(CacheRepository::class);
        $cache->shouldReceive('put')->once();
        $cache->shouldReceive('get')->once()->andReturn('ok');
        $cache->shouldReceive('forget')->once();

        $result = (new SystemHealthService($database, $cache))->check();
        $databaseCheck = collect($result['checks'])->firstWhere('key', 'database');

        $this->assertSame('FAIL', $databaseCheck['status']);
        $this->assertStringContainsString('Database connection failed', $databaseCheck['message']);
        $this->assertStringNotContainsString('super-secret', $databaseCheck['message']);
        $this->assertStringNotContainsString('private-db', $databaseCheck['message']);
    }

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
