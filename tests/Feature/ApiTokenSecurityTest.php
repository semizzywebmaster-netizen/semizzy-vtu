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

    public function test_core_check_accepts_a_core_read_token(): void
    {
        $user = $this->makeUser();
        $token = $user->createToken('Core client', ['core.read']);

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/v1/core-check')
            ->assertOk()
            ->assertJsonPath('status', 'ok');
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
