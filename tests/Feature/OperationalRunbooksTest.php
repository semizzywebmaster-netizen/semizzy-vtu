<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class OperationalRunbooksTest extends TestCase
{
    use RefreshDatabase;

    public function test_authorized_admin_can_view_operational_runbooks(): void
    {
        $admin = User::factory()->create([
            'role' => 'ADMIN',
            'email_verified_at' => now(),
        ]);

        $this->actingAs($admin)
            ->get('/admin/runbooks')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Admin/OperationalRunbooks')
                ->has('runbooks', 6)
                ->where('runbooks.0.id', 'provider-outage'));
    }

    public function test_regular_user_cannot_view_operational_runbooks(): void
    {
        $user = User::factory()->create(['role' => 'USER']);

        $this->actingAs($user)->get('/admin/runbooks')->assertForbidden();
    }
}
