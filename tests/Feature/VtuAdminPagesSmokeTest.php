<?php

namespace Tests\Feature;

use App\Models\Addon;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class VtuAdminPagesSmokeTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_vtu_control_pages_render_without_server_errors(): void
    {
        $admin = User::create([
            'name' => 'VTU Smoke Admin',
            'email' => 'vtu-smoke-admin@example.test',
            'password' => 'Strong-Password-123!',
            'role' => 'ADMIN',
            'status' => 'active',
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();

        Addon::create([
            'identifier' => 'vtu.digital-services',
            'name' => 'VTU & Digital Services',
            'version' => '1.0.0',
            'status' => 'active',
            'navigation' => [],
            'permissions' => [],
            'dependencies' => [],
            'settings_schema' => [],
            'manifest' => [],
        ]);

        foreach ([
            '/admin/vtu',
            '/admin/vtu/services',
            '/admin/vtu/products',
            '/admin/vtu/mappings',
            '/admin/vtu/transactions',
            '/admin/vtu/bulk',
        ] as $url) {
            $this->actingAs($admin)->get($url)->assertOk();
        }
    }

    public function test_regular_user_can_open_vtu_services_from_dashboard(): void
    {
        $user = User::create([
            'name' => 'VTU Smoke User',
            'email' => 'vtu-smoke-user@example.test',
            'password' => 'Strong-Password-123!',
            'role' => 'USER',
            'status' => 'active',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        Addon::create([
            'identifier' => 'vtu.digital-services',
            'name' => 'VTU & Digital Services',
            'version' => '1.0.0',
            'status' => 'active',
            'navigation' => [],
            'permissions' => [],
            'dependencies' => [],
            'settings_schema' => [],
            'manifest' => [],
        ]);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('role', 'USER')
                ->where('quickLinks.0.url', '/notifications')
            );

        $this->actingAs($user)->get('/vtu')->assertOk();
    }
}
