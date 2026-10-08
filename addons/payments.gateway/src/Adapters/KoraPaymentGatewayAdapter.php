<?php

namespace Semizzy\Addons\Payments\Adapters;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Semizzy\Addons\Payments\Contracts\PaymentGatewayAdapter;
use Semizzy\Addons\Payments\Models\PaymentGatewayProvider;

final class KoraPaymentGatewayAdapter implements PaymentGatewayAdapter
{
    private function secret(PaymentGatewayProvider $provider): string
    {
        $key = (string) ($provider->credentials['secret_key'] ?? $provider->credentials['secret'] ?? '');
        if ($key === '') throw new RuntimeException('Kora secret key is required.');
        return $key;
    }

    private function request(PaymentGatewayProvider $provider)
    {
        return Http::acceptJson()->withToken($this->secret($provider))->asJson()->timeout(30);
    }

    private function result($response): array
    {
        $body = $response->json();
        if (!$response->successful() || !($body['status'] ?? false)) {
            throw new RuntimeException((string) ($body['message'] ?? 'Kora request failed.'));
        }
        return is_array($body['data'] ?? null) ? $body['data'] : [];
    }

    public function initializeCollection(PaymentGatewayProvider $provider, array $payload): array
    {
        $metadata = $payload['metadata'] ?? [];
        if ($metadata === []) $metadata = ['source' => 'semizzy-one'];

        return $this->result($this->request($provider)->post(
            rtrim($provider->base_url ?: 'https://api.korapay.com', '/').'/merchant/api/v1/charges/initialize',
            [
                'amount' => (float) $payload['amount'],
                'currency' => strtoupper($payload['currency'] ?? 'NGN'),
                'reference' => $payload['reference'],
                'redirect_url' => $payload['redirect_url'] ?? null,
                'notification_url' => $payload['webhook_url'] ?? ($payload['callback_url'] ?? null),
                'narration' => $payload['description'] ?? 'SEMIZZY ONE payment',
                'channels' => $payload['channels'] ?? ['card', 'bank_transfer', 'pay_with_bank'],
                'metadata' => $metadata,
                'customer' => [
                    'email' => $payload['customer_email'],
                    'name' => $payload['customer_name'] ?? null,
                ],
                'merchant_bears_cost' => $payload['merchant_bears_cost'] ?? true,
            ]
        ));
    }

    public function verifyCollection(PaymentGatewayProvider $provider, string $reference): array
    {
        return $this->result($this->request($provider)->get(
            rtrim($provider->base_url ?: 'https://api.korapay.com', '/').'/merchant/api/v1/charges/'.rawurlencode($reference)
        ));
    }

    public function nameEnquiry(PaymentGatewayProvider $provider, string $bankCode, string $accountNumber): array
    {
        throw new RuntimeException('Kora account name enquiry is not enabled in this collection gateway adapter.');
    }

    public function singlePayout(PaymentGatewayProvider $provider, array $payload): array
    {
        throw new RuntimeException('Kora payout is not enabled in this collection gateway adapter.');
    }

    public function bulkPayout(PaymentGatewayProvider $provider, array $payload): array
    {
        throw new RuntimeException('Kora bulk payout is not enabled in this collection gateway adapter.');
    }

    public function refund(PaymentGatewayProvider $provider, array $payload): array
    {
        throw new RuntimeException('Kora refund is not enabled in this collection gateway adapter.');
    }

    public function healthCheck(PaymentGatewayProvider $provider): bool
    {
        $response = $this->request($provider)->get(
            rtrim($provider->base_url ?: 'https://api.korapay.com', '/').'/merchant/api/v1/misc/banks',
            ['countryCode' => 'NG']
        );
        $this->result($response);
        return true;
    }
}
