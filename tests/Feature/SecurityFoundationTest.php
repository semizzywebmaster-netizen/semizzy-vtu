<?php

namespace Tests\Feature;

use App\Models\SecurityEvent;
use App\Models\User;
use App\Services\Security\SecurityEventLogger;
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
        $user->forceFill(['email_verified_at' => now()])->save();

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

    public function test_api_token_cannot_use_an_unassigned_ability(): void
    {
        $user = User::create([
            'name' => 'Ability Test User',
            'email' => 'ability-test@example.test',
            'password' => 'Strong-Test-Password-123!',
            'role' => 'USER',
            'status' => 'active',
            ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $token = $user->createToken('Limited client', ['profile.read']);

        $this->withHeader('Authorization', 'Bearer '.$token->plainTextToken)
            ->getJson('/api/v1/core-check')
            ->assertForbidden();
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
        $user->forceFill(['email_verified_at' => now()])->save();

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
        $user->forceFill(['email_verified_at' => now()])->save();

        $tooFar = Carbon::now()->addDays(366)->toIso8601String();

        $this->actingAs($user)->postJson('/api/v1/tokens', [
            'name' => 'Too long',
            'abilities' => ['core.read'],
            'expires_at' => $tooFar,
        ])->assertUnprocessable()
            ->assertJsonValidationErrors('expires_at');
    }

    public function test_security_event_context_is_sanitized_recursively(): void
    {
        $user = User::create([
            'name' => 'Event Test User',
            'email' => 'event-test@example.test',
            'password' => 'Strong-Test-Password-123!',
            'role' => 'USER',
            'status' => 'active',
            ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($user);

        app(SecurityEventLogger::class)->record('security.sanitization_test', 'warning', [
            'safe' => 'kept',
            'secret' => 'top-level-secret',
            'nested' => [
                'password' => 'nested-password',
                'safe' => 'nested-kept',
                'deep' => [
                    'api_key' => 'nested-key',
                    'value' => 'kept',
                ],
            ],
        ], request());

        $event = $user->securityEvents()->latest('id')->firstOrFail();

        $this->assertSame('kept', $event->context['safe']);
        $this->assertArrayNotHasKey('secret', $event->context);
        $this->assertArrayNotHasKey('password', $event->context['nested']);
        $this->assertSame('nested-kept', $event->context['nested']['safe']);
        $this->assertArrayNotHasKey('api_key', $event->context['nested']['deep']);
        $this->assertSame('kept', $event->context['nested']['deep']['value']);
    }

    public function test_security_event_context_redacts_variant_sensitive_key_names(): void
    {
        $user = User::create([
            'name' => 'Variant Event User',
            'email' => 'variant-event@example.test',
            'password' => 'Strong-Test-Password-123!',
            'role' => 'USER',
            'status' => 'active',
        ]);
        $user->forceFill(['email_verified_at' => now()])->save();

        $this->actingAs($user);

        app(SecurityEventLogger::class)->record('security.variant_sanitization_test', 'warning', [
            'x-api-key' => 'header-secret',
            'webhook_secret' => 'webhook-secret',
            'client-secret' => 'client-secret',
            'refresh_token_value' => 'refresh-secret',
            'safe_value' => 'kept',
        ], request());

        $event = $user->securityEvents()->latest('id')->firstOrFail();

        $this->assertArrayNotHasKey('x-api-key', $event->context);
        $this->assertArrayNotHasKey('webhook_secret', $event->context);
        $this->assertArrayNotHasKey('client-secret', $event->context);
        $this->assertArrayNotHasKey('refresh_token_value', $event->context);
        $this->assertSame('kept', $event->context['safe_value']);
    }

    public function test_security_event_admin_view_redacts_sensitive_context_recursively(): void
    {
        $admin = User::create([
            'name' => 'Security Admin',
            'email' => 'security-admin@example.test',
            'password' => 'Strong-Test-Password-123!',
            'role' => 'ADMIN',
            'status' => 'active',
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();

        $event = SecurityEvent::create([
            'user_id' => $admin->id,
            'event' => 'security.view_redaction_test',
            'severity' => 'warning',
            'context' => [
                'safe' => 'visible',
                'password' => 'top-secret',
                'nested' => [
                    'api_key' => 'nested-secret',
                    'safe' => 'nested-visible',
                ],
            ],
        ]);

        $this->actingAs($admin)->get('/admin/security-events')
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('events.data.0.id', $event->id)
                ->where('events.data.0.context.safe', 'visible')
                ->where('events.data.0.context.password', '[REDACTED]')
                ->where('events.data.0.context.nested.api_key', '[REDACTED]')
                ->where('events.data.0.context.nested.safe', 'nested-visible')
            );
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
