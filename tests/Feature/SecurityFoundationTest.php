<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Security\WebhookSignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class SecurityFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_token_can_be_created_listed_and_revoked(): void
    {
        $user = User::create([
            'name' => 'Security Test User',
            'email' => 'security-test@example.test',
            'password' => 'Strong-Test-Password-123!',
            'role' => 'USER',
            'status' => 'active',
        ]);

        $response = $this->actingAs($user)->postJson('/api/v1/tokens', [
            'name' => 'Test client',
            'abilities' => ['core.read'],
        ]);

        $response->assertCreated()
            ->assertJsonStructure(['token', 'expires_at']);

        $plain = $response->json('token');
        $this->assertStringStartsWith('1|', $plain);

        $this->assertDatabaseCount('personal_access_tokens', 1);

        $this->actingAs($user)->getJson('/api/v1/tokens')
            ->assertOk()
            ->assertJsonPath('data.0.name', 'Test client');

        $id = $user->tokens()->firstOrFail()->getKey();

        $this->actingAs($user)->deleteJson('/api/v1/tokens/'.$id)
            ->assertOk();

        $this->assertDatabaseCount('personal_access_tokens', 0);
    }

    public function test_expired_api_token_is_rejected(): void
    {
        $user = User::create([
            'name' => 'Expired Token User',
            'email' => 'expired-token@example.test',
            'password' => 'Strong-Test-Password-123!',
            'role' => 'USER',
            'status' => 'active',
        ]);

        $token = $user->createToken('Expired client', ['core.read'], now()->subMinute());

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/v1/core-check')
            ->assertUnauthorized();
    }

    public function test_api_token_lifetime_limit_is_enforced(): void
    {
        $user = User::create([
            'name' => 'Lifetime Test User',
            'email' => 'token-lifetime@example.test',
            'password' => 'Strong-Test-Password-123!',
            'role' => 'USER',
            'status' => 'active',
        ]);

        $tooFar = Carbon::now()->addDays(366)->toIso8601String();

        $this->actingAs($user)->postJson('/api/v1/tokens', [
            'name' => 'Too long',
            'abilities' => ['core.read'],
            'expires_at' => $tooFar,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('expires_at');
    }

    public function test_webhook_signature_rejects_tampering_and_stale_signatures(): void
    {
        $service = app(WebhookSignatureService::class);
        $signature = $service->sign('payload', 'secret', 1000);

        $this->assertTrue($service->verify('payload', $signature, 'secret', 300, 1100));
        $this->assertFalse($service->verify('tampered', $signature, 'secret', 300, 1100));
        $this->assertFalse($service->verify('payload', $signature, 'secret', 300, 1401));
    }
}
