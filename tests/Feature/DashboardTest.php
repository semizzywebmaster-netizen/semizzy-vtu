<?php

namespace Tests\Feature;

use App\Models\SupportTicket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_dashboard_uses_real_database_counts_and_admin_links(): void
    {
        $admin = $this->makeUser('dashboard-admin@example.test', 'ADMIN');
        $this->makeUser('dashboard-user@example.test', 'USER');

        $this->actingAs($admin)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('role', 'ADMIN')
                ->where('metrics.0.value', 2)
                ->has('quickLinks', 9)
                ->where('quickLinks.0.url', '/admin/users')
                ->where('quickLinks.5.url', '/admin/settings')
            );
    }

    public function test_regular_user_only_receives_personal_metrics_and_links(): void
    {
        $user = $this->makeUser('dashboard-member@example.test', 'USER');
        $other = $this->makeUser('dashboard-other@example.test', 'USER');

        SupportTicket::create([
            'user_id' => $user->id,
            'reference' => 'SUP-DASHBOARD1',
            'subject' => 'My ticket',
            'category' => 'general',
            'priority' => 'normal',
            'status' => 'open',
        ]);
        SupportTicket::create([
            'user_id' => $other->id,
            'reference' => 'SUP-DASHBOARD2',
            'subject' => 'Other ticket',
            'category' => 'general',
            'priority' => 'normal',
            'status' => 'open',
        ]);
        $user->notifications()->create([
            'id' => (string) Str::uuid(),
            'type' => 'App\\Notifications\\CoreNotification',
            'data' => ['title' => 'Account update'],
        ]);

        $this->actingAs($user)->get('/dashboard')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Dashboard')
                ->where('role', 'USER')
                ->where('metrics.0.label', 'My support tickets')
                ->where('metrics.0.value', 1)
                ->where('metrics.1.label', 'Unread notifications')
                ->where('metrics.1.value', 1)
                ->has('quickLinks', 3)
                ->where('quickLinks.0.url', '/notifications')
            );
    }

    private function makeUser(string $email, string $role): User
    {
        $user = User::create([
            'name' => 'Dashboard Test User',
            'email' => $email,
            'password' => 'Strong-Password-123!',
            'role' => $role,
            'status' => 'active',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
