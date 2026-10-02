<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Models\ProviderRequestLog;
use App\Services\Providers\ProviderRequestLogger;
use App\Services\Providers\ProviderResult;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProviderRequestLoggingTest extends TestCase
{
    use RefreshDatabase;

    public function test_provider_response_logs_recursively_redact_credentials_and_bound_payload_size(): void
    {
        $provider = ApiProvider::create([
            'identifier' => 'logging-test',
            'display_name' => 'Logging Test Provider',
            'credentials' => ['api_key' => 'provider-secret'],
        ]);

        $data = [
            'status' => 'success',
            'message' => 'Accepted',
            'access_token' => 'top-level-secret',
            'data' => [
                'authorization' => 'nested-secret',
                'customer' => ['password_hash' => 'password-secret'],
                'reference' => 'safe-reference',
            ],
        ];

        app(ProviderRequestLogger::class)->record(
            $provider,
            'transaction_status',
            'test-service',
            new ProviderResult(true, 'ACCEPTED', 'provider-reference', $data),
            42,
            'idempotency-test'
        );

        $log = ProviderRequestLog::query()->firstOrFail();
        $this->assertSame('[REDACTED]', $log->request_summary);
        $this->assertStringNotContainsString('top-level-secret', $log->response_summary);
        $this->assertStringNotContainsString('nested-secret', $log->response_summary);
        $this->assertStringNotContainsString('password-secret', $log->response_summary);
        $this->assertStringContainsString('[REDACTED]', $log->response_summary);
        $this->assertStringContainsString('safe-reference', $log->response_summary);
        $this->assertSame('provider-reference', $log->provider_reference);
    }
}
