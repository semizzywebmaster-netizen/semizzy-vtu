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
        $this->assertSame('Provider request failed. Check the provider configuration and server logs.', $result->message);
        $this->assertStringNotContainsString('provider-token-secret', $result->message);
        $this->assertStringNotContainsString('private-db', $result->message);
    }
}
