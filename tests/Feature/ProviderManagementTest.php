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

    public function test_provider_connection_and_endpoint_secret_configuration_is_encrypted_and_masked(): void
    {
        $admin = $this->makeAdmin();
        $provider = ApiProvider::create([
            'identifier' => 'secret-provider',
            'display_name' => 'Secret Provider',
            'environment' => 'production',
            'verification_status' => 'unverified',
            'integration_status' => 'draft',
            'enabled' => false,
            'paused' => true,
        ]);

        $connectionResponse = $this->actingAs($admin)->postJson('/admin/providers/'.$provider->id.'/connections', [
            'name' => 'Production',
            'environment' => 'production',
            'base_url' => 'https://example.com/api',
            'auth_type' => 'api_key',
            'headers' => ['Authorization' => 'Bearer SUPER-CONNECTION-SECRET'],
            'query_params' => ['api_key' => 'SUPER-QUERY-SECRET'],
            'proxy' => ['password' => 'SUPER-PROXY-SECRET'],
            'auth_options' => ['client_secret' => 'SUPER-AUTH-SECRET'],
        ])->assertCreated();

        $connection = \App\Models\ProviderConnection::query()->where('api_provider_id', $provider->id)->firstOrFail();
        $raw = \DB::table('provider_connections')->where('id', $connection->id)->first();

        $this->assertStringNotContainsString('SUPER-CONNECTION-SECRET', (string) $raw->headers);
        $this->assertStringNotContainsString('SUPER-QUERY-SECRET', (string) $raw->query_params);
        $this->assertStringNotContainsString('SUPER-PROXY-SECRET', (string) $raw->proxy);
        $this->assertStringNotContainsString('SUPER-AUTH-SECRET', (string) $raw->auth_options);

        $connectionResponse->assertJsonPath('data.headers.Authorization', '[CONFIGURED]');
        $connectionResponse->assertJsonPath('data.query_params.api_key', '[CONFIGURED]');
        $connectionResponse->assertJsonPath('data.proxy.password', '[CONFIGURED]');
        $connectionResponse->assertJsonPath('data.auth_options.client_secret', '[REDACTED]');

        $endpointResponse = $this->actingAs($admin)->postJson('/admin/providers/'.$provider->id.'/endpoints', [
            'name' => 'Health',
            'operation' => 'health',
            'method' => 'GET',
            'path' => '/health',
            'content_type' => 'json',
            'auth_mode' => 'connection',
            'headers' => ['X-Provider-Secret' => 'SUPER-ENDPOINT-SECRET'],
            'query_params' => ['token' => 'SUPER-ENDPOINT-TOKEN'],
            'webhook_config' => ['webhook_secret' => 'SUPER-WEBHOOK-SECRET'],
        ])->assertCreated();

        $endpoint = \App\Models\ProviderEndpoint::query()->where('api_provider_id', $provider->id)->firstOrFail();
        $rawEndpoint = \DB::table('provider_endpoints')->where('id', $endpoint->id)->first();

        $this->assertStringNotContainsString('SUPER-ENDPOINT-SECRET', (string) $rawEndpoint->headers);
        $this->assertStringNotContainsString('SUPER-ENDPOINT-TOKEN', (string) $rawEndpoint->query_params);
        $this->assertStringNotContainsString('SUPER-WEBHOOK-SECRET', (string) $rawEndpoint->webhook_config);

        $endpointResponse->assertJsonPath('data.headers.X-Provider-Secret', '[CONFIGURED]');
        $endpointResponse->assertJsonPath('data.query_params.token', '[CONFIGURED]');
        $endpointResponse->assertJsonPath('data.webhook_config.webhook_secret', '[REDACTED]');
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

    public function test_admin_removal_disables_provider_product_mappings(): void
    {
        $admin = $this->makeAdmin();
        $provider = ApiProvider::create([
            'identifier' => 'product-remove-provider',
            'display_name' => 'Product Remove Provider',
            'enabled' => true,
            'paused' => false,
            'verification_status' => 'live_verified',
            'integration_status' => 'live_verified',
        ]);
        $categoryId = \DB::table('service_categories')->insertGetId([
            'key' => 'test-category', 'name' => 'Test Category', 'enabled' => true,
            'sort_order' => 100, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $serviceId = \DB::table('services')->insertGetId([
            'category_id' => $categoryId, 'key' => 'test-service', 'name' => 'Test Service',
            'enabled' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $productId = \DB::table('service_products')->insertGetId([
            'service_id' => $serviceId, 'key' => 'test-product', 'name' => 'Test Product',
            'currency' => 'NGN', 'enabled' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);
        $mappingId = \DB::table('provider_service_products')->insertGetId([
            'api_provider_id' => $provider->id, 'service_product_id' => $productId,
            'provider_product_id' => 'provider-product-1', 'provider_cost' => '100.000000',
            'currency' => 'NGN', 'enabled' => true, 'created_at' => now(), 'updated_at' => now(),
        ]);

        $this->actingAs($admin)->delete('/admin/providers/'.$provider->id)->assertRedirect();

        $this->assertDatabaseHas('provider_service_products', [
            'id' => $mappingId, 'enabled' => 0,
        ]);
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
