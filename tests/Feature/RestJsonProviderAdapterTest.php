<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Services\Providers\RestJsonProviderAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use InvalidArgumentException;
use RuntimeException;
use Tests\TestCase;

class RestJsonProviderAdapterTest extends TestCase
{
    use RefreshDatabase;

    public function test_absolute_endpoint_urls_are_rejected(): void
    {
        $provider = ApiProvider::create([
            'identifier' => 'adapter-endpoint-guard',
            'display_name' => 'Adapter Endpoint Guard',
            'base_url' => 'https://8.8.8.8',
            'endpoints' => ['transaction_status' => 'http://127.0.0.1/private'],
            'auth_type' => 'bearer',
            'credentials' => ['token' => 'provider-token-secret'],
        ]);

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('relative path');

        app(RestJsonProviderAdapter::class)->execute($provider, 'transaction_status', ['reference' => 'ref-1']);
    }

    public function test_endpoint_query_strings_are_rejected(): void
    {
        $provider = ApiProvider::create([
            'identifier' => 'adapter-endpoint-query-guard',
            'display_name' => 'Adapter Endpoint Query Guard',
            'base_url' => 'https://8.8.8.8',
            'endpoints' => ['transaction_status' => '/status?token=secret'],
            'auth_type' => 'bearer',
            'credentials' => ['token' => 'provider-token-secret'],
        ]);

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('queries');

        app(RestJsonProviderAdapter::class)->execute($provider, 'transaction_status', ['reference' => 'ref-1']);
    }

    public function test_definitive_http_4xx_rejection_is_not_marked_as_duplicate_risk_for_failover(): void
    {
        $provider = ApiProvider::create([
            'identifier' => 'adapter-4xx-failover',
            'display_name' => 'Adapter 4xx Failover',
            'base_url' => 'https://8.8.8.8',
            'endpoints' => ['transaction_initiation' => '/purchase'],
            'auth_type' => 'bearer',
            'credentials' => ['token' => 'provider-token-secret'],
            'timeout_seconds' => 3,
        ]);

        Http::fake([
            'https://8.8.8.8/purchase' => Http::response(['status' => 'rejected'], 400),
        ]);

        $result = app(RestJsonProviderAdapter::class)->execute(
            $provider,
            'transaction_initiation',
            ['phone' => '08000000000'],
            'idem-4xx'
        );

        $this->assertFalse($result->accepted);
        $this->assertSame('FAILED', $result->status);
        $this->assertFalse($result->duplicateRisk);
        $this->assertFalse($result->retryable);
    }

    public function test_successful_http_with_definitive_failed_status_is_not_marked_as_duplicate_risk(): void
    {
        $provider = ApiProvider::create([
            'identifier' => 'adapter-2xx-failed',
            'display_name' => 'Adapter 2xx Failed',
            'base_url' => 'https://8.8.8.8',
            'endpoints' => ['transaction_initiation' => '/purchase'],
            'auth_type' => 'bearer',
            'credentials' => ['token' => 'provider-token-secret'],
            'timeout_seconds' => 3,
        ]);

        Http::fake([
            'https://8.8.8.8/purchase' => Http::response(['status' => 'declined'], 200),
        ]);

        $result = app(RestJsonProviderAdapter::class)->execute(
            $provider,
            'transaction_initiation',
            ['phone' => '08000000000'],
            'idem-2xx-failed'
        );

        $this->assertFalse($result->accepted);
        $this->assertSame('FAILED', $result->status);
        $this->assertFalse($result->duplicateRisk);
    }

    public function test_transport_exception_details_are_not_returned_to_callers(): void
    {
        $provider = ApiProvider::create([
            'identifier' => 'adapter-exception-test',
            'display_name' => 'Adapter Exception Test',
            'base_url' => 'https://8.8.8.8',
            'endpoints' => ['transaction_status' => '/status'],
            'auth_type' => 'bearer',
            'credentials' => ['token' => 'provider-token-secret'],
            'timeout_seconds' => 3,
        ]);

        Http::fake(function () {
            throw new RuntimeException('Authorization: provider-token-secret internal-host=private-db');
        });

        $result = app(RestJsonProviderAdapter::class)->execute($provider, 'transaction_status', ['reference' => 'ref-1']);

        $this->assertFalse($result->accepted);
        $this->assertSame('UNKNOWN', $result->status);
        $this->assertSame('Provider request failed; provider state must be rechecked before retry.', $result->message);
        $this->assertStringNotContainsString('provider-token-secret', $result->message);
        $this->assertStringNotContainsString('private-db', $result->message);
    }

    public function test_configured_endpoint_uses_connection_credentials_query_parameters_and_idempotency(): void
    {
        $provider = ApiProvider::create([
            'identifier' => 'configured-endpoint-auth',
            'display_name' => 'Configured Endpoint Auth',
            'base_url' => 'https://legacy.example.test',
            'capabilities' => ['transaction_initiation', 'transaction_status'],
            'verification_status' => 'live_verified',
            'integration_status' => 'live_verified',
            'enabled' => true,
            'paused' => false,
        ]);

        $connection = $provider->connections()->create([
            'name' => 'Live',
            'environment' => 'live',
            'base_url' => 'https://8.8.8.8',
            'api_prefix' => 'v1',
            'auth_type' => 'token',
            'request_timeout_seconds' => 10,
            'connect_timeout_seconds' => 3,
            'verify_ssl' => true,
            'query_params' => ['tenant' => 'semizzy'],
            'enabled' => true,
            'is_default' => true,
        ]);

        $connection->credentials()->create([
            'field_key' => 'token',
            'label' => 'API Token',
            'field_type' => 'password',
            'required' => true,
            'secret' => true,
            'placement' => 'authorization',
            'prefix' => 'Bearer',
            'value' => 'configured-secret',
        ]);

        $provider->endpoints()->create([
            'name' => 'Purchase',
            'operation' => 'transaction_initiation',
            'method' => 'POST',
            'path' => 'purchase',
            'content_type' => 'json',
            'auth_mode' => 'connection',
            'enabled' => true,
        ]);

        Http::fake([
            'https://8.8.8.8/v1/purchase*' => Http::response([
                'status' => 'success',
                'reference' => 'CFG-1',
            ], 200),
        ]);

        $result = app(RestJsonProviderAdapter::class)->execute(
            $provider,
            'transaction_initiation',
            ['recipient' => '08000000000'],
            'configured-idem-1'
        );

        $this->assertTrue($result->accepted);
        $this->assertSame('CFG-1', $result->providerReference);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://8.8.8.8/v1/purchase?tenant=semizzy' &&
                $request->method() === 'POST' &&
                $request->hasHeader('Authorization', 'Bearer configured-secret') &&
                $request->hasHeader('Idempotency-Key', 'configured-idem-1') &&
                $request['recipient'] === '08000000000';
        });
    }

    public function test_configured_endpoint_honors_put_and_query_content_type_without_injecting_credentials_when_auth_is_none(): void
    {
        $provider = ApiProvider::create([
            'identifier' => 'configured-endpoint-none',
            'display_name' => 'Configured Endpoint None',
            'base_url' => 'https://legacy.example.test',
            'capabilities' => ['transaction_status'],
            'verification_status' => 'live_verified',
            'integration_status' => 'live_verified',
            'enabled' => true,
            'paused' => false,
        ]);

        $connection = $provider->connections()->create([
            'name' => 'Default',
            'environment' => 'live',
            'base_url' => 'https://8.8.8.8',
            'api_prefix' => 'v2',
            'auth_type' => 'token',
            'query_params' => ['tenant' => 'semizzy'],
            'request_timeout_seconds' => 10,
            'connect_timeout_seconds' => 3,
            'verify_ssl' => true,
            'enabled' => true,
            'is_default' => true,
        ]);

        $connection->credentials()->create([
            'field_key' => 'token',
            'label' => 'API Token',
            'field_type' => 'password',
            'required' => true,
            'secret' => true,
            'placement' => 'authorization',
            'prefix' => 'Bearer',
            'value' => 'connection-secret',
        ]);

        $provider->endpoints()->create([
            'name' => 'Status',
            'operation' => 'transaction_status',
            'method' => 'PUT',
            'path' => 'status',
            'content_type' => 'query',
            'auth_mode' => 'none',
            'enabled' => true,
        ]);

        Http::fake([
            'https://8.8.8.8/v2/status*' => Http::response([
                'status' => 'pending',
            ], 200),
        ]);

        $result = app(RestJsonProviderAdapter::class)->execute(
            $provider,
            'transaction_status',
            ['reference' => 'REF-1']
        );

        $this->assertSame('PENDING', $result->status);

        Http::assertSent(function ($request): bool {
            return $request->url() === 'https://8.8.8.8/v2/status?tenant=semizzy&reference=REF-1' &&
                $request->method() === 'PUT' &&
                !$request->hasHeader('Authorization');
        });
    }
}
