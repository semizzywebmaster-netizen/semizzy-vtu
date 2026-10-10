<?php

namespace Tests\Feature\Payments;

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Semizzy\Addons\Payments\Adapters\InterswitchPaymentGatewayAdapter;
use Semizzy\Addons\Payments\Adapters\PaystackPaymentGatewayAdapter;
use Semizzy\Addons\Payments\Models\PaymentGatewayProvider;
use Tests\TestCase;

class ProviderAdapterIntegrationTest extends TestCase
{
    use RefreshDatabase;

    private bool $createdProviderTable = false;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();

        if (!Schema::hasTable('payment_gateway_providers')) {
            Schema::create('payment_gateway_providers', function (Blueprint $table): void {
                $table->id();
                $table->string('name');
                $table->string('code')->unique();
                $table->string('driver');
                $table->string('base_url')->nullable();
                $table->text('credentials')->nullable();
                $table->json('capabilities')->nullable();
                $table->unsignedInteger('priority')->default(100);
                $table->unsignedInteger('weight')->default(100);
                $table->boolean('enabled')->default(false);
                $table->boolean('paused')->default(false);
                $table->boolean('maintenance')->default(false);
                $table->unsignedInteger('failure_count')->default(0);
                $table->timestamp('cooldown_until')->nullable();
                $table->timestamp('last_health_check_at')->nullable();
                $table->timestamp('last_success_at')->nullable();
                $table->timestamp('last_failure_at')->nullable();
                $table->text('last_error')->nullable();
                $table->json('settings')->nullable();
                $table->timestamps();
                $table->index(['enabled', 'paused', 'maintenance', 'priority']);
            });
            $this->createdProviderTable = true;
        }
    }

    protected function tearDown(): void
    {
        if ($this->createdProviderTable && Schema::hasTable('payment_gateway_providers')) {
            Schema::drop('payment_gateway_providers');
        }
        parent::tearDown();
    }

    public function test_paystack_name_enquiry_uses_documented_bank_resolve_endpoint(): void
    {
        $provider = $this->provider('paystack');
        Http::fake(['https://api.paystack.co/bank/resolve*' => Http::response([
            'status' => true, 'data' => ['account_number' => '0123456789', 'account_name' => 'TEST CUSTOMER', 'bank_id' => 1],
        ], 200)]);

        $result = app(PaystackPaymentGatewayAdapter::class)->nameEnquiry($provider, '044', '0123456789');

        $this->assertSame('TEST CUSTOMER', $result['account_name']);
        Http::assertSent(fn ($request) => str_contains($request->url(), '/bank/resolve')
            && str_contains($request->url(), 'bank_code=044')
            && str_contains($request->url(), 'account_number=0123456789'));
    }

    public function test_paystack_single_payout_creates_recipient_then_initiates_transfer(): void
    {
        $provider = $this->provider('paystack');
        Http::fake([
            'https://api.paystack.co/transferrecipient' => Http::response(['status' => true, 'data' => ['recipient_code' => 'RCP_TEST123']], 200),
            'https://api.paystack.co/transfer' => Http::response(['status' => true, 'data' => ['reference' => 'semizzy-test-ref-001', 'status' => 'pending', 'transfer_code' => 'TRF_TEST']], 200),
        ]);

        $result = app(PaystackPaymentGatewayAdapter::class)->singlePayout($provider, [
            'amount_minor' => 15000, 'reference' => 'semizzy-test-ref-001',
            'account_number' => '0123456789', 'bank_code' => '044', 'account_name' => 'TEST CUSTOMER',
        ]);

        $this->assertSame('TRF_TEST', $result['transfer_code']);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/transferrecipient')
            && $request['type'] === 'nuban' && $request['bank_code'] === '044');
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/transfer')
            && $request['amount'] === 15000 && $request['recipient'] === 'RCP_TEST123');
    }

    public function test_paystack_refund_is_requested_and_verified_by_provider_refund_id(): void
    {
        $provider = $this->provider('paystack');
        Http::fake([
            'https://api.paystack.co/refund*' => Http::sequence()
                ->push(['status' => true, 'data' => ['id' => 910, 'status' => 'processing']], 200)
                ->push(['status' => true, 'data' => ['id' => 910, 'status' => 'processed', 'amount' => 500]], 200),
        ]);

        $requested = app(PaystackPaymentGatewayAdapter::class)->refund($provider, [
            'transaction' => 'TXN-123', 'amount_minor' => 500, 'merchant_note' => 'Approved by support',
        ]);
        $verified = app(PaystackPaymentGatewayAdapter::class)->verifyRefund($provider, '910');

        $this->assertSame('processing', $requested['status']);
        $this->assertSame('processed', $verified['status']);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/refund')
            && $request['transaction'] === 'TXN-123' && $request['amount'] === 500);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/refund/910'));
    }

    public function test_interswitch_name_enquiry_uses_payout_customer_lookup_contract(): void
    {
        $provider = $this->provider('interswitch');
        Http::fake([
            'https://passport-sandbox.interswitchng.com/passport/oauth/token*' => Http::response(['access_token' => 'test-access-token', 'expires_in' => 3600], 200),
            'https://payouts-sandbox.interswitchng.com/api/v1/payouts/customer-lookup' => Http::response([
                'responseCode' => '00', 'responseDescription' => 'Approved or completed successfully', 'recipientName' => 'TEST CUSTOMER',
            ], 200),
        ]);

        $result = app(InterswitchPaymentGatewayAdapter::class)->nameEnquiry($provider, '044', '0123456789');

        $this->assertSame('TEST CUSTOMER', $result['recipientName']);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/customer-lookup')
            && $request['payoutChannel'] === 'BANK_TRANSFER'
            && $request['recipient']['recipientBank'] === '044'
            && $request['recipient']['recipientAccount'] === '0123456789');
    }

    public function test_interswitch_single_payout_uses_documented_payout_payload(): void
    {
        $provider = $this->provider('interswitch');
        Http::fake([
            'https://passport-sandbox.interswitchng.com/passport/oauth/token*' => Http::response(['access_token' => 'test-access-token', 'expires_in' => 3600], 200),
            'https://payouts-sandbox.interswitchng.com/api/v1/payouts' => Http::response([
                'responseCode' => '00', 'responseDescription' => 'Approved or completed successfully',
                'transactionReference' => 'SEMIZZY-PAYOUT-001', 'status' => 'PROCESSING',
            ], 200),
        ]);

        $result = app(InterswitchPaymentGatewayAdapter::class)->singlePayout($provider, [
            'reference' => 'SEMIZZY-PAYOUT-001', 'amount' => 1250.50,
            'account_number' => '0123456789', 'bank_code' => '044', 'narration' => 'Test payout',
        ]);

        $this->assertSame('PROCESSING', $result['status']);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/api/v1/payouts')
            && $request['transactionReference'] === 'SEMIZZY-PAYOUT-001'
            && $request['amount'] === 1250.5
            && $request['recipient']['recipientBank'] === '044'
            && $request['walletDetails']['walletId'] === 'test-wallet');
    }

    public function test_paystack_payout_is_reconciled_by_reference_and_remains_processing_until_confirmed(): void
    {
        $provider = $this->provider('paystack');
        Http::fake(['https://api.paystack.co/transfer/verify/semizzy-test-ref-002' => Http::sequence()
            ->push(['status' => true, 'data' => ['reference' => 'semizzy-test-ref-002', 'status' => 'processing']], 200)
            ->push(['status' => true, 'data' => ['reference' => 'semizzy-test-ref-002', 'status' => 'success', 'transfer_code' => 'TRF_TEST']], 200)]);

        $adapter = app(PaystackPaymentGatewayAdapter::class);
        $pending = $adapter->verifyPayout($provider, 'semizzy-test-ref-002');
        $settled = $adapter->verifyPayout($provider, 'semizzy-test-ref-002');

        $this->assertSame('processing', $pending['status']);
        $this->assertSame('success', $settled['status']);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/transfer/verify/semizzy-test-ref-002'));
    }

    public function test_paystack_payout_verification_rejects_mismatched_reference(): void
    {
        $provider = $this->provider('paystack');
        Http::fake(['https://api.paystack.co/transfer/verify/semizzy-test-ref-003' => Http::response([
            'status' => true, 'data' => ['reference' => 'some-other-reference', 'status' => 'success'],
        ], 200)]);

        $this->expectException(\\RuntimeException::class);
        app(PaystackPaymentGatewayAdapter::class)->verifyPayout($provider, 'semizzy-test-ref-003');
    }

    public function test_interswitch_account_verification_uses_the_separate_signed_name_enquiry_contract(): void
    {
        $provider = $this->provider('interswitch');
        $credentials = $provider->credentials;
        $credentials['secret_key'] = 'test-secret-key';
        $credentials['terminal_id'] = 'SEMIZZY-TEST-001';
        $credentials['account_verification_url'] = 'https://sandbox.interswitchng.com/api/v1/nameenquiry/banks/accounts/names';
        $provider->credentials = $credentials;
        $provider->save();

        Http::fake(['https://sandbox.interswitchng.com/api/v1/nameenquiry/banks/accounts/names*' => Http::response([
            'responseCode' => '00', 'accountName' => 'TEST CUSTOMER',
        ], 200)]);

        $result = app(InterswitchPaymentGatewayAdapter::class)->verifyAccount($provider, '044', '0123456789');

        $this->assertSame('TEST CUSTOMER', $result['account_name']);
        $this->assertTrue($result['verified']);
        Http::assertSent(function ($request): bool {
            return str_contains($request->url(), '/nameenquiry/banks/accounts/names')
                && str_contains($request->url(), 'bankCode=044')
                && str_contains($request->url(), 'accountId=0123456789')
                && str_starts_with((string) $request->header('Authorization')[0], 'InterswitchAuth ')
                && $request->hasHeader('Signature')
                && $request->hasHeader('Timestamp')
                && $request->hasHeader('Nonce')
                && $request->hasHeader('TerminalID');
        });
    }

    public function test_interswitch_account_verification_fails_closed_when_no_account_name_is_returned(): void
    {
        $provider = $this->provider('interswitch');
        $credentials = $provider->credentials;
        $credentials['secret_key'] = 'test-secret-key';
        $credentials['terminal_id'] = 'SEMIZZY-TEST-001';
        $provider->credentials = $credentials;
        $provider->save();
        Http::fake(['https://sandbox.interswitchng.com/api/v1/nameenquiry/banks/accounts/names*' => Http::response(['responseCode' => '00'], 200)]);

        $this->expectException(\\RuntimeException::class);
        app(InterswitchPaymentGatewayAdapter::class)->verifyAccount($provider, '044', '0123456789');
    }

    private function provider(string $driver): PaymentGatewayProvider
    {
        $credentials = $driver === 'paystack'
            ? ['secret_key' => 'sk_test_fake']
            : ['client_id' => 'test-client', 'client_secret' => 'test-secret', 'wallet_id' => 'test-wallet', 'wallet_pin' => '1234'];

        return PaymentGatewayProvider::query()->create([
            'name' => strtoupper($driver).' Test',
            'code' => strtoupper($driver).'-TEST-'.strtoupper(bin2hex(random_bytes(3))),
            'driver' => $driver,
            'base_url' => $driver === 'paystack' ? 'https://api.paystack.co' : 'https://payouts-sandbox.interswitchng.com/api/v1/payouts',
            'credentials' => $credentials,
            'capabilities' => [],
            'enabled' => false,
            'paused' => true,
            'maintenance' => false,
            'settings' => [],
        ]);
    }
}
