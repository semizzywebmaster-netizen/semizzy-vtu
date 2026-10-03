<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\RateLimiter;
use Tests\TestCase;

class SecurityHardeningTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_login_route_uses_configured_path(): void
    {
        $expectedPath = trim((string) config('semizzy.admin_login_path', 'admin/login'), '/') ?: 'admin/login';
        $route = app('router')->getRoutes()->getByName('admin.login');

        $this->assertNotNull($route);
        $this->assertSame($expectedPath, $route->uri());
        $this->assertSame($expectedPath, app('router')->getRoutes()->getByName('admin.login.store')->uri());
    }

    public function test_api_token_routes_are_rate_limited_and_log_throttling(): void
    {
        $u = User::create([
            'name' => 'Throttle Test',
            'email' => 'throttle@example.test',
            'password' => 'Strong-Test-Password-123!',
            'role' => 'USER',
            'status' => 'active',
        ]);

        $this->actingAs($u);

        for ($i = 0; $i < 10; $i++) {
            $this->getJson('/api/v1/tokens')->assertStatus(200);
        }

        $this->getJson('/api/v1/tokens')->assertStatus(429);
        $this->assertDatabaseHas('security_events', [
            'event' => 'security.rate_limited',
            'severity' => 'warning',
        ]);

        RateLimiter::clear('security:api.tokens:'.sha1((string) $u->id));
    }

    public function test_normal_users_cannot_access_admin_area(): void
    {
        $u = User::create([
            'name' => 'Normal User',
            'email' => 'normal@example.test',
            'password' => 'Strong-Test-Password-123!',
            'role' => 'USER',
            'status' => 'active',
        ]);

        $this->actingAs($u)->get('/admin/health')->assertStatus(403);
    }

    public function test_admin_can_access_security_events_page(): void
    {
        $admin = User::create([
            'name' => 'Security Admin',
            'email' => 'security-admin@example.test',
            'password' => 'Strong-Test-Password-123!',
            'role' => 'ADMIN',
            'status' => 'active',
        ]);

        $this->actingAs($admin)->get('/admin/security-events')->assertOk();
    }
}
