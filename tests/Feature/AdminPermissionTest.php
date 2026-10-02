<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminPermissionTest extends TestCase
{
    use RefreshDatabase;

    public function test_staff_can_view_provider_registry_but_cannot_modify_providers(): void
    {
        $staff = $this->makeUser('staff@example.test', 'STAFF');

        $this->actingAs($staff)->get('/admin/providers')->assertOk();
        $this->actingAs($staff)->post('/admin/providers', [
            'identifier' => 'sample-provider',
            'display_name' => 'Sample Provider',
            'environment' => 'sandbox',
            'auth_type' => 'custom',
        ])->assertForbidden();
    }

    public function test_support_role_cannot_access_provider_administration(): void
    {
        $support = $this->makeUser('support@example.test', 'SUPPORT');

        $this->actingAs($support)->get('/admin/providers')->assertForbidden();
    }

    public function test_admin_can_access_provider_administration(): void
    {
        $admin = $this->makeUser('admin@example.test', 'ADMIN');

        $this->actingAs($admin)->get('/admin/providers')->assertOk();
    }

    private function makeUser(string $email, string $role): User
    {
        return User::create([
            'name' => 'Permission Test',
            'email' => $email,
            'password' => 'Strong-Password-123!',
            'role' => $role,
            'status' => 'active',
            'email_verified_at' => now(),
        ]);
    }
}
