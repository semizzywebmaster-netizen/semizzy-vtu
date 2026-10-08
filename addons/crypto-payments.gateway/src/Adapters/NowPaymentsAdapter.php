<?php

namespace Semizzy\Addons\CryptoPayments\Adapters;

use Illuminate\Support\Facades\Http;
use Semizzy\Addons\CryptoPaymentsContracts\CryptoPaymentGatewayAdapter;
use Semizzy\Addons\CryptoPayments\Models\CryptoPaymentProvider;
use RuntimeException;

class NowPaymentsAdapter implements CryptoPaymentGatewayAdapter
{
    public function __construct(private CryptoPaymentProvider $provider) {}

    private function client()
    {
        $credentials = $this->provider->credentials ?? [];
        $apiKey = $credentials['api_key'] ?? null;

        if (! $apiKey) {
            throw new RuntimeException('NOWPayments API key is not configured.');
        }

        return Http::baseUrl(rtrim($this->provider->base_url ?: 'https://api.nowpayments.io', '/'))
            ->acceptJson()
            ->withHeaders(['x-api-key' => $apiKey])
            ->timeout(30)
            ->retry(2, 250, throw: false);
    }

    public function createPayment(array $payload): array
    {
        return $this->request('POST', '/v1/payment', $payload);
    }

    public function verifyPayment(array $payload): array
    {
        $paymentId = $payload['payment_id'] ?? null;
        if (! $paymentId) {
            throw new RuntimeException('NOWPayments payment_id is required.');
        }

        return $this->request('GET', '/v1/payment/' . rawurlencode((string) $paymentId));
    }

    public function createInvoice(array $payload): array
    {
        return $this->request('POST', '/v1/invoice', $payload);
    }

    public function createCheckout(array $payload): array
    {
        return $this->createInvoice($payload);
    }

    public function payout(array $payload): array
    {
        return $this->request('POST', '/v1/payout', $payload);
    }

    public function refund(array $payload): array
    {
        throw new RuntimeException('NOWPayments refunds require provider-specific handling and are not enabled by this adapter.');
    }

    public function healthCheck(): array
    {
        $response = $this->client()->get('/v1/status');
        return [
            'ok' => $response->successful(),
            'status' => $response->status(),
        ];
    }

    private function request(string $method, string $path, array $payload = []): array
    {
        $response = $method === 'GET'
            ? $this->client()->get($path, $payload)
            : $this->client()->send($method, $path, ['json' => $payload]);

        if ($response->failed()) {
            throw new RuntimeException('NOWPayments API request failed: HTTP ' . $response->status());
        }

        $data = $response->json();
        return is_array($data) ? $data : [];
    }
}