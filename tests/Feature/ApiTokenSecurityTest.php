<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\PersonalAccessToken;
use Tests\TestCase;

class ApiTokenSecurityTest extends TestCase
{
    use RefreshDatabase;

    public function test_token_without_expiry_request_gets_configured_default_expiry(): void
    {
        config(['sanctum.token_max_lifetime_days' => 365]);

        $user = $this->makeUser();

        $response = $this->actingAs($user)->postJson('/api/v1/tokens', [
            'name' => 'Core client',
        ]);

        $response->assertCreated();

        $tokenId = PersonalAccessToken::query()
            ->where('tokenable_id', $user->id)
            ->latest('id')
            ->value('id');

        $token = PersonalAccessToken::query()->findOrFail($tokenId);

        $this->assertNotNull($token->expires_at);
        $this->assertEqualsWithDelta(
            now()->addDays(365)->timestamp,
            $token->expires_at->timestamp,
            5
        );
    }

    public function test_requested_token_expiry_cannot_exceed_configured_maximum(): void
    {
        config(['sanctum.token_max_lifetime_days' => 365]);

        $user = $this->makeUser();

        $this->actingAs($user)->postJson('/api/v1/tokens', [
            'name' => 'Too long',
            'expires_at' => now()->addDays(366)->toIso8601String(),
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['expires_at']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_empty_abilities_are_rejected(): void
    {
        $user = $this->makeUser();

        $this->actingAs($user)->postJson('/api/v1/tokens', [
            'name' => 'Empty abilities',
            'abilities' => [],
        ])->assertUnprocessable()
            ->assertJsonValidationErrors(['abilities']);

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_inactive_user_cannot_manage_api_tokens(): void
    {
        $user = $this->makeUser();
        $user->forceFill(['status' => 'suspended'])->save();

        $this->actingAs($user)->getJson('/api/v1/tokens')
            ->assertForbidden();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_core_check_accepts_a_core_read_token(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('Core client', ['core.read']);

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/v1/core-check')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
    }

    public function test_user_cannot_revoke_another_users_token(): void
    {
        $owner = $this->makeUser();
        $otherUser = $this->makeUser();
        $token = $otherUser->createToken('Other user token', ['core.read']);

        $this->actingAs($owner)
            ->deleteJson('/api/v1/tokens/'.$token->accessToken->getKey())
            ->assertNotFound();

        $this->assertDatabaseHas('personal_access_tokens', [
            'id' => $token->accessToken->getKey(),
            'tokenable_id' => $otherUser->id,
        ]);
    }

    public function test_user_can_revoke_only_their_own_token(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('Revocable token', ['core.read']);

        $this->actingAs($user)
            ->deleteJson('/api/v1/tokens/'.$token->accessToken->getKey())
            ->assertOk()
            ->assertJsonPath('message', 'Token revoked.');

        $this->assertDatabaseMissing('personal_access_tokens', [
            'id' => $token->accessToken->getKey(),
        ]);
    }

    public function test_revoke_all_removes_only_the_current_users_tokens(): void
    {
        $user = $this->makeUser();
        $otherUser = $this->makeUser();
        $first = $user->createToken('First token', ['core.read']);
        $second = $user->createToken('Second token', ['core.read']);
        $other = $otherUser->createToken('Other token', ['core.read']);

        $this->actingAs($user)
            ->deleteJson('/api/v1/tokens')
            ->assertOk()
            ->assertJsonPath('count', 2);

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $first->accessToken->getKey()]);
        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $second->accessToken->getKey()]);
        $this->assertDatabaseHas('personal_access_tokens', ['id' => $other->accessToken->getKey()]);
    }

    private function makeUser(): User
    {
        $user = User::create([
            'name' => 'API Token Test',
            'email' => fake()->unique()->safeEmail(),
            'password' => 'Strong-Password-123!',
            'role' => 'USER',
            'status' => 'active',
        ]);

        $user->forceFill(['email_verified_at' => now()])->save();

        return $user;
    }
}
