<?php

namespace Semizzy\Addons\Payments\Adapters;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Semizzy\Addons\Payments\Contracts\PaymentGatewayAdapter;
use Semizzy\Addons\Payments\Models\PaymentGatewayProvider;

final class OpayPaymentGatewayAdapter implements PaymentGatewayAdapter
{
    private function secret(PaymentGatewayProvider $provider): string
    {
        $key = (string) ($provider->credentials['secret_key'] ?? $provider->credentials['secret'] ?? $provider->credentials['private_key'] ?? '');
        if ($key === '') throw new RuntimeException('OPay secret/private key is required.');
        return $key;
    }

    private function merchantId(PaymentGatewayProvider $provider): string
    {
        $id = (string) ($provider->credentials['merchant_id'] ?? '');
        if ($id === '') throw new RuntimeException('OPay merchant ID is required.');
        return $id;
    }

    private function request(PaymentGatewayProvider $provider, array $payload): \Illuminate\Http\Client\PendingRequest
    {
        $body = json_encode($payload, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
        $signature = hash_hmac('sha512', $body, $this->secret($provider));

        return Http::acceptJson()->asJson()->withHeaders([
            'Authorization' => 'Bearer '.$signature,
            'MerchantId' => $this->merchantId($provider),
        ])->timeout(30);
    }

    private function result($response): array
    {
        $body = $response->json();
        if (!$response->successful() || !is_array($body) || (string) ($body['code'] ?? '') !== '00000') {
            throw new RuntimeException((string) ($body['message'] ?? 'OPay request failed.'));
        }
        return is_array($body['data'] ?? null) ? $body['data'] : [];
    }

    public function initializeCollection(PaymentGatewayProvider $provider, array $payload): array
    {
        $data = array_filter([
            'amount' => [
                'currency' => strtoupper($payload['currency'] ?? 'NGN'),
                'total' => (int) $payload['amount_minor'],
            ],
            'callbackUrl' => $payload['callback_url'] ?? $payload['webhook_url'] ?? null,
            'country' => 'NG',
            'expireAt' => (int) ($payload['expiry_minutes'] ?? 30),
            'payMethod' => $payload['pay_method'] ?? 'BankCard',
            'product' => [
                'name' => (string) ($payload['product_name'] ?? 'Wallet Funding'),
                'description' => (string) ($payload['product_description'] ?? 'SEMIZZY ONE wallet funding'),
            ],
            'reference' => $payload['reference'],
            'returnUrl' => $payload['redirect_url'] ?? null,
            'cancelUrl' => $payload['cancel_url'] ?? null,
        ], fn ($value) => $value !== null);

        return $this->result($this->request($provider, $data)->post(
            rtrim($provider->base_url ?: 'https://liveapi.opaycheckout.com', '/').'/api/v1/international/payment/create',
            $data
        ));
    }

    public function verifyCollection(PaymentGatewayProvider $provider, string $reference): array
    {
        $data = ['country' => 'NG', 'reference' => $reference];

        return $this->result($this->request($provider, $data)->post(
            rtrim($provider->base_url ?: 'https://liveapi.opaycheckout.com', '/').'/api/v1/international/cashier/status',
            $data
        ));
    }

    public function nameEnquiry(PaymentGatewayProvider $provider, string $bankCode, string $accountNumber): array
    {
        throw new RuntimeException('OPay name enquiry is not enabled by this payment gateway adapter.');
    }

    public function singlePayout(PaymentGatewayProvider $provider, array $payload): array
    {
        throw new RuntimeException('OPay payout is not enabled by this payment gateway adapter.');
    }

    public function bulkPayout(PaymentGatewayProvider $provider, array $payload): array
    {
        throw new RuntimeException('OPay bulk payout is not enabled by this payment gateway adapter.');
    }

    public function refund(PaymentGatewayProvider $provider, array $payload): array
    {
        $data = array_filter([
            'amount' => [
                'currency' => strtoupper($payload['currency'] ?? 'NGN'),
                'total' => (int) $payload['amount_minor'],
            ],
            'callbackUrl' => $payload['callback_url'] ?? $payload['webhook_url'] ?? null,
            'country' => 'NG',
            'originalReference' => $payload['original_reference'],
            'reference' => $payload['reference'],
            'refundWay' => $payload['refund_way'] ?? 'Original',
            'bankCode' => $payload['bankCode'] ?? null,
            'bankAccountNo' => $payload['bankAccountNo'] ?? null,
            'refundReason' => $payload['refundReason'] ?? null,
        ], fn ($value) => $value !== null);

        return $this->result($this->request($provider, $data)->post(
            rtrim($provider->base_url ?: 'https://liveapi.opaycheckout.com', '/').'/api/v1/international/payment/refund/create',
            $data
        ));
    }

    public function healthCheck(PaymentGatewayProvider $provider): bool
    {
        $this->verifyCollection($provider, 'SEMIZZY_HEALTHCHECK_'.bin2hex(random_bytes(6)));
        return true;
    }
}
