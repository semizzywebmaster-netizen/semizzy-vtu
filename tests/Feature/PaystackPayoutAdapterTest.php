<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Http;
use Semizzy\Addons\Payments\Adapters\PaystackPaymentGatewayAdapter;
use Semizzy\Addons\Payments\Models\PaymentGatewayProvider;
use Tests\TestCase;

class PaystackPayoutAdapterTest extends TestCase
{
    private function provider(): PaymentGatewayProvider
    {
        return new PaymentGatewayProvider(['base_url' => 'https://paystack.test', 'credentials' => ['secret_key' => 'sk_test_example']]);
    }

    public function test_single_payout_resolves_account_creates_recipient_and_starts_transfer(): void
    {
        Http::fake([
            'https://paystack.test/transfer/verify/semizzy_payout_000001' => Http::response(['status' => false, 'message' => 'Transfer not found'], 404),
            'https://paystack.test/bank/resolve*' => Http::response(['status' => true, 'data' => ['account_name' => 'Ada Okafor', 'account_number' => '0123456789']], 200),
            'https://paystack.test/transferrecipient' => Http::response(['status' => true, 'data' => ['recipient_code' => 'RCP_test123']], 200),
            'https://paystack.test/transfer' => Http::response(['status' => true, 'data' => ['reference' => 'semizzy_payout_000001', 'transfer_code' => 'TRF_test123', 'status' => 'otp', 'amount' => 250000, 'currency' => 'NGN']], 200),
        ]);

        $result = (new PaystackPaymentGatewayAdapter())->singlePayout($this->provider(), [
            'bank_code' => '044', 'account_number' => '0123456789', 'amount_minor' => 250000,
            'reference' => 'semizzy_payout_000001', 'reason' => 'User withdrawal',
        ]);

        $this->assertSame('otp', $result['status']);
        $this->assertTrue($result['requires_otp']);
        $this->assertSame('RCP_test123', $result['recipient_code']);
        Http::assertSentCount(4);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/transfer') && ($request->data()['reference'] ?? null) === 'semizzy_payout_000001' && ($request->data()['amount'] ?? null) === 250000);
    }

    public function test_bulk_transfer_rejects_duplicate_references_before_sending(): void
    {
        Http::fake();
        try {
            (new PaystackPaymentGatewayAdapter())->bulkPayout($this->provider(), ['transfers' => [
                ['amount_minor' => 1000, 'recipient_code' => 'RCP_a', 'reference' => 'semizzy_bulk_000001'],
                ['amount_minor' => 2000, 'recipient_code' => 'RCP_b', 'reference' => 'semizzy_bulk_000001'],
            ]]);
            $this->fail('Duplicate references must be rejected.');
        } catch (\RuntimeException $exception) {
            $this->assertStringContainsString('references must be unique', $exception->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_single_payout_requires_valid_reference_and_positive_kobo_amount(): void
    {
        Http::fake();
        $this->expectException(\RuntimeException::class);
        (new PaystackPaymentGatewayAdapter())->singlePayout($this->provider(), ['bank_code' => '044', 'account_number' => '0123456789', 'amount_minor' => 0, 'reference' => 'short']);
    }


    public function test_bulk_payout_blocks_if_any_reference_verification_is_ambiguous(): void
    {
        Http::fake(['https://paystack.test/transfer/verify/semizzy_bulk_000001' => Http::response(['message' => 'temporarily unavailable'], 503)]);
        try {
            (new PaystackPaymentGatewayAdapter())->bulkPayout($this->provider(), ['transfers' => [
                ['amount_minor' => 1000, 'recipient_code' => 'RCP_a', 'reference' => 'semizzy_bulk_000001'],
            ]]);
            $this->fail('An uncertain reference must block bulk payout submission.');
        } catch (\Semizzy\\Addons\\Payments\\Exceptions\\AmbiguousPaymentGatewayException $exception) {
            $this->assertStringContainsString('uncertain state', $exception->getMessage());
        }
        Http::assertSentCount(1);
        Http::assertNotSent(fn ($request) => str_ends_with($request->url(), '/transfer/bulk'));
    }


    public function test_bulk_payout_does_not_treat_an_unrelated_404_as_unused_reference(): void
    {
        Http::fake(['https://paystack.test/transfer/verify/semizzy_bulk_000002' => Http::response(['message' => 'Route not found'], 404)]);
        $this->expectException(\RuntimeException::class);
        (new PaystackPaymentGatewayAdapter())->bulkPayout($this->provider(), ['transfers' => [
            ['amount_minor' => 1000, 'recipient_code' => 'RCP_a', 'reference' => 'semizzy_bulk_000002'],
        ]]);
    }

    public function test_verify_payout_uses_reference_verification_endpoint(): void
    {
        Http::fake(['https://paystack.test/transfer/verify/semizzy_payout_000001' => Http::response(['status' => true, 'data' => ['reference' => 'semizzy_payout_000001', 'status' => 'success']], 200)]);
        $result = (new PaystackPaymentGatewayAdapter())->verifyPayout($this->provider(), 'semizzy_payout_000001');
        $this->assertSame('success', $result['status']);
        Http::assertSent(fn ($request) => str_ends_with($request->url(), '/transfer/verify/semizzy_payout_000001'));
    }
    public function test_existing_transfer_reference_is_returned_without_creating_another_payout(): void
    {
        Http::fake(['https://paystack.test/transfer/verify/semizzy_payout_000001' => Http::response(['status' => true, 'data' => ['reference' => 'semizzy_payout_000001', 'transfer_code' => 'TRF_existing', 'status' => 'success', 'amount' => 250000, 'currency' => 'NGN']], 200)]);
        $result = (new PaystackPaymentGatewayAdapter())->singlePayout($this->provider(), [
            'bank_code' => '044', 'account_number' => '0123456789', 'amount_minor' => 250000,
            'reference' => 'semizzy_payout_000001',
        ]);
        $this->assertTrue($result['replayed']);
        $this->assertSame('TRF_existing', $result['transfer_code']);
        Http::assertSentCount(1);
    }

    public function test_ambiguous_transfer_reference_check_suppresses_retry_before_creating_a_recipient(): void
    {
        Http::fake(['https://paystack.test/transfer/verify/semizzy_payout_000001' => Http::response(['message' => 'temporarily unavailable'], 503)]);
        $this->expectException(\Semizzy\Addons\Payments\Exceptions\AmbiguousPaymentGatewayException::class);
        (new PaystackPaymentGatewayAdapter())->singlePayout($this->provider(), [
            'bank_code' => '044', 'account_number' => '0123456789', 'amount_minor' => 250000,
            'reference' => 'semizzy_payout_000001',
        ]);
    }

}
