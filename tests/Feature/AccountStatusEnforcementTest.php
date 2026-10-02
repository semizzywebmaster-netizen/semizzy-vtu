<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccountStatusEnforcementTest extends TestCase
{
    use RefreshDatabase;

    public function test_suspended_user_session_is_revoked_on_next_web_request(): void
    {
        $user = $this->makeUser('suspended@example.test', 'suspended');

        $this->actingAs($user)->get('/profile')->assertRedirect('/login');

        $this->assertGuest();
        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'event' => 'auth.inactive_account.session_revoked',
            'severity' => 'warning',
        ]);
    }

    public function test_disabled_user_cannot_access_protected_pages(): void
    {
        $user = $this->makeUser('disabled@example.test', 'disabled');

        $this->actingAs($user)->get('/notifications')->assertRedirect('/login');
        $this->assertGuest();
    }

    public function test_inactive_user_cannot_use_api_token_endpoints(): void
    {
        $user = $this->makeUser('api-suspended@example.test', 'suspended');

        $this->actingAs($user)->getJson('/api/v1/tokens')
            ->assertForbidden()
            ->assertJsonPath('message', 'Your account is not active. Contact support if you need assistance.');

        $this->assertDatabaseHas('security_events', [
            'user_id' => $user->id,
            'event' => 'auth.inactive_account.api_denied',
            'severity' => 'warning',
        ]);
    }

    public function test_active_user_can_access_profile(): void
    {
        $user = $this->makeUser('active@example.test', 'active');

        $this->actingAs($user)->get('/profile')->assertOk();
    }

    private function makeUser(string $email, string $status): User
    {
        return User::create([
            'name' => 'Account Status Test',
            'email' => $email,
            'password' => 'Strong-Password-123!',
            'role' => 'USER',
            'status' => $status,
        ]);
    }
}
