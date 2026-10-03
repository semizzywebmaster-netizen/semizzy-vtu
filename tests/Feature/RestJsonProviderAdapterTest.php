<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Services\Providers\RestJsonProviderAdapter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
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

        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('query string');

        app(RestJsonProviderAdapter::class)->execute($provider, 'transaction_status', ['reference' => 'ref-1']);
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
