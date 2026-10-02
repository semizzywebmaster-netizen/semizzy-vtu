<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_authenticated_user_can_view_profile_without_exposing_password(): void
    {
        $user = User::create([
            'name' => 'Profile User',
            'email' => 'profile@example.test',
            'password' => 'Strong-Password-123!',
            'role' => 'USER',
            'status' => 'active',
        ]);

        $this->actingAs($user)
            ->get('/profile')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Profile')
                ->where('user.name', 'Profile User')
                ->where('user.email', 'profile@example.test')
                ->missing('user.password')
            );
    }

    public function test_guest_cannot_view_profile(): void
    {
        $this->get('/profile')->assertRedirect('/login');
    }
}
