<?php

namespace Tests\Feature;

use App\Models\ApiProvider;
use App\Services\Providers\ProviderUrlGuard;
use App\Services\Providers\RestJsonProviderAdapter;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class InterswitchAccountVerificationTest extends TestCase
{
    public function test_it_signs_account_enquiry_and_returns_normalized_account_details(): void
    {
        Http::fake([
            'https://example.com/api/v1/nameenquiry/banks/accounts/names' => Http::response(['accountName' => 'Ada Okafor'], 200),
        ]);
        $provider = new ApiProvider([
            'identifier' => 'interswitch', 'base_url' => 'https://example.com/api/v1', 'timeout_seconds' => 10,
            'credentials' => ['client_id' => 'client-123', 'secret_key' => 'secret-456', 'terminal_id' => 'terminal-789'],
        ]);

        $result = (new RestJsonProviderAdapter(new ProviderUrlGuard()))->execute($provider, 'account_verification', ['bank_code' => '044', 'account_number' => '0123456789']);

        $this->assertTrue($result->accepted);
        $this->assertSame('ACCEPTED', $result->status);
        $this->assertSame(['account_name' => 'Ada Okafor', 'bank_code' => '044', 'account_number' => '0123456789'], $result->data);
        Http::assertSent(function ($request): bool {
            $headers = $request->headers();
            return $request->method() === 'GET'
                && $request->url() === 'https://example.com/api/v1/nameenquiry/banks/accounts/names'
                && ($headers['Authorization'][0] ?? '') === 'InterswitchAuth ' . base64_encode('client-123')
                && ($headers['bankCode'][0] ?? '') === '044'
                && ($headers['accountId'][0] ?? '') === '0123456789'
                && ($headers['SignatureMethod'][0] ?? '') === 'SHA1'
                && ($headers['TerminalID'][0] ?? '') === 'terminal-789'
                && isset($headers['Signature'][0], $headers['Timestamp'][0], $headers['Nonce'][0])
                && ($headers['Signature'][0] ?? '') === base64_encode(sha1(
                    'GET&' . urlencode('https://example.com/api/v1/nameenquiry/banks/accounts/names') . '&'
                    . $headers['Timestamp'][0] . '&' . $headers['Nonce'][0] . '&client-123&secret-456',
                    true
                ));
        });
    }

    public function test_it_fails_closed_when_required_credentials_or_account_fields_are_missing(): void
    {
        Http::fake();
        $provider = new ApiProvider(['identifier' => 'interswitch', 'base_url' => 'https://example.com/api/v1', 'credentials' => ['client_id' => 'client-123']]);
        $result = (new RestJsonProviderAdapter(new ProviderUrlGuard()))->execute($provider, 'account_verification', ['bank_code' => '044', 'account_number' => '0123456789']);
        $this->assertFalse($result->accepted);
        $this->assertSame('FAILED', $result->status);
        Http::assertNothingSent();
    }


    public function test_it_rejects_malformed_bank_inputs_before_calling_interswitch(): void
    {
        Http::fake();
        $provider = new ApiProvider([
            'identifier' => 'interswitch', 'base_url' => 'https://example.com/api/v1',
            'credentials' => ['client_id' => 'client-123', 'secret_key' => 'secret-456', 'terminal_id' => 'terminal-789'],
        ]);
        $result = (new RestJsonProviderAdapter(new ProviderUrlGuard()))->execute($provider, 'account_verification', [
            'bank_code' => 'not-a-code', 'account_number' => '0123456789',
        ]);
        $this->assertFalse($result->accepted);
        $this->assertSame('FAILED', $result->status);
        Http::assertNothingSent();
    }

    public function test_server_errors_remain_unknown_instead_of_being_treated_as_verification(): void
    {
        Http::fake(['https://example.com/api/v1/nameenquiry/banks/accounts/names' => Http::response(['message' => 'temporarily unavailable'], 503)]);
        $provider = new ApiProvider(['identifier' => 'interswitch', 'base_url' => 'https://example.com/api/v1', 'credentials' => ['client_id' => 'client-123', 'secret_key' => 'secret-456', 'terminal_id' => 'terminal-789']]);
        $result = (new RestJsonProviderAdapter(new ProviderUrlGuard()))->execute($provider, 'account_verification', ['bank_code' => '044', 'account_number' => '0123456789']);
        $this->assertFalse($result->accepted);
        $this->assertSame('UNKNOWN', $result->status);
    }
}
