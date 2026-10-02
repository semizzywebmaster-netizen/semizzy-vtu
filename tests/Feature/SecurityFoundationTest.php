<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\Security\WebhookSignatureService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class SecurityFoundationTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_token_can_be_created_listed_and_revoked(): void
    {
        $user = User::factory()->create();

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

    public function test_webhook_signature_rejects_tampering_and_stale_signatures(): void
    {
        $service = app(WebhookSignatureService::class);
        $signature = $service->sign('payload', 'secret', 1000);

        $this->assertTrue($service->verify('payload', $signature, 'secret', 300, 1100));
        $this->assertFalse($service->verify('tampered', $signature, 'secret', 300, 1100));
        $this->assertFalse($service->verify('payload', $signature, 'secret', 300, 1401));
    }
}
