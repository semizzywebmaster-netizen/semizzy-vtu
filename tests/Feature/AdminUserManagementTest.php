<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AdminUserManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_search_users_without_exposing_passwords(): void
    {
        $admin = $this->makeUser('admin-users@example.test', 'ADMIN');
        $target = $this->makeUser('target-users@example.test', 'USER', 'Target Customer');

        $this->actingAs($admin)->get('/admin/users?search=Target')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Admin/Users')
                ->where('users.total', 1)
                ->where('users.data.0.email', $target->email)
                ->missing('users.data.0.password'));
    }

    public function test_staff_cannot_manage_user_accounts(): void
    {
        $staff = $this->makeUser('staff-users@example.test', 'STAFF');
        $this->actingAs($staff)->get('/admin/users')->assertForbidden();
    }

    public function test_admin_cannot_deactivate_themselves(): void
    {
        $admin = $this->makeUser('self-admin@example.test', 'ADMIN');
        $this->actingAs($admin)->patch('/admin/users/'.$admin->id, [
            'role' => 'ADMIN', 'status' => 'suspended',
        ])->assertUnprocessable();

        $this->assertSame('active', $admin->fresh()->status);
    }

    public function test_admin_cannot_demote_themselves_when_another_admin_exists(): void
    {
        $admin = $this->makeUser('self-demote-admin@example.test', 'ADMIN');
        $this->makeUser('other-self-demote-admin@example.test', 'ADMIN');

        $this->actingAs($admin)->patch('/admin/users/'.$admin->id, [
            'role' => 'USER',
            'status' => 'active',
        ])->assertUnprocessable();

        $this->assertSame('ADMIN', $admin->fresh()->role);
    }

    public function test_last_active_admin_cannot_be_demoted_or_deactivated(): void
    {
        $admin = $this->makeUser('last-admin@example.test', 'ADMIN');
        $this->actingAs($admin)->patch('/admin/users/'.$admin->id, [
            'role' => 'USER', 'status' => 'active',
        ])->assertUnprocessable();

        $this->assertSame('ADMIN', $admin->fresh()->role);
    }

    public function test_admin_can_change_a_user_role_and_status(): void
    {
        $admin = $this->makeUser('manage-admin@example.test', 'ADMIN');
        $target = $this->makeUser('manage-target@example.test', 'USER');

        $this->actingAs($admin)->patch('/admin/users/'.$target->id, [
            'role' => 'SUPPORT', 'status' => 'suspended',
        ])->assertRedirect();

        $this->assertSame('SUPPORT', $target->fresh()->role);
        $this->assertSame('suspended', $target->fresh()->status);
        $this->assertDatabaseHas('audit_events', ['event' => 'admin.user.updated', 'auditable_id' => $target->id]);
    }

    private function makeUser(string $email, string $role, string $name = 'Test User'): User
    {
        $user = User::create([
            'name' => $name,
            'email' => $email,
            'password' => 'Strong-Password-123!',
            'role' => $role,
            'status' => 'active',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
