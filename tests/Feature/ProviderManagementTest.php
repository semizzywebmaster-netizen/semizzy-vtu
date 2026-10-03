<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Models\AuditEvent;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_admin_can_add_provider_but_it_starts_disabled_and_unverified(): void
    {
        $admin = $this->makeAdmin();

        $this->actingAs($admin)->post('/admin/providers', [
            'identifier' => 'future-provider',
            'display_name' => 'Future Provider',
            'environment' => 'sandbox',
            'auth_type' => 'bearer',
            'base_url' => 'https://8.8.8.8',
            'capabilities' => ['health'],
            'endpoints' => [],
            'service_categories' => [],
            'credentials' => ['token' => 'never-log-this'],
        ])->assertRedirect();

        $provider = ApiProvider::query()->where('identifier', 'future-provider')->firstOrFail();
        $this->assertFalse($provider->enabled);
        $this->assertTrue($provider->paused);
        $this->assertSame('unverified', $provider->verification_status);
        $this->assertSame('draft', $provider->integration_status);
        $this->assertSame('never-log-this', $provider->credentials['token']);
        $this->assertDatabaseHas('audit_events', ['event' => 'provider.created']);
        $this->assertStringNotContainsString('never-log-this', json_encode(AuditEvent::query()->where('event', 'provider.created')->firstOrFail()->context));
    }

    public function test_admin_can_remove_provider_without_hard_deleting_history_record(): void
    {
        $admin = $this->makeAdmin();
        $provider = ApiProvider::create([
            'identifier' => 'remove-me',
            'display_name' => 'Remove Me',
            'enabled' => true,
            'paused' => false,
            'verification_status' => 'live_verified',
            'integration_status' => 'live_verified',
        ]);

        $this->actingAs($admin)->delete('/admin/providers/'.$provider->id)->assertRedirect();

        $this->assertSoftDeleted('api_providers', ['id' => $provider->id]);
        $this->assertDatabaseHas('api_providers', ['id' => $provider->id, 'enabled' => 0, 'paused' => 1]);
        $this->assertDatabaseHas('audit_events', ['event' => 'provider.removed', 'auditable_id' => $provider->id]);
        $this->assertSame(0, ApiProvider::query()->whereKey($provider->id)->count());
        $this->assertSame(1, ApiProvider::withTrashed()->whereKey($provider->id)->count());
    }

    public function test_provider_configuration_change_forces_reverification(): void
    {
        $admin = $this->makeAdmin();
        $provider = ApiProvider::create([
            'identifier' => 'reverify-provider',
            'display_name' => 'Reverify Provider',
            'base_url' => 'https://8.8.8.8',
            'environment' => 'production',
            'auth_type' => 'bearer',
            'verification_status' => 'live_verified',
            'integration_status' => 'live_verified',
            'enabled' => true,
            'paused' => false,
        ]);

        $this->actingAs($admin)->patch('/admin/providers/'.$provider->id, [
            'base_url' => 'https://1.1.1.1',
        ])->assertRedirect();

        $provider->refresh();
        $this->assertFalse($provider->enabled);
        $this->assertTrue($provider->paused);
        $this->assertSame('unverified', $provider->verification_status);
        $this->assertSame('draft', $provider->integration_status);
    }

    public function test_non_admin_cannot_remove_provider(): void
    {
        $user = User::create([
            'name' => 'Regular User',
            'email' => 'provider-remove-user@example.test',
            'password' => 'Strong-Password-123!',
            'role' => 'USER',
            'status' => 'active',
        ]);
        $provider = ApiProvider::create(['identifier' => 'protected-provider', 'display_name' => 'Protected Provider']);

        $this->actingAs($user)->delete('/admin/providers/'.$provider->id)->assertForbidden();
        $this->assertNotSoftDeleted('api_providers', ['id' => $provider->id]);
    }

    private function makeAdmin(): User
    {
        $admin = User::create([
            'name' => 'Provider Admin',
            'email' => 'provider-admin@example.test',
            'password' => 'Strong-Password-123!',
            'role' => 'ADMIN',
            'status' => 'active',
        ]);
        $admin->forceFill(['email_verified_at' => now()])->save();

        return $admin;
    }
}
