<?php

namespace Semizzy\Addons\Payments\Adapters;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Semizzy\Addons\Payments\Contracts\PaymentGatewayAdapter;
use Semizzy\Addons\Payments\Models\PaymentGatewayProvider;

final class SquadPaymentGatewayAdapter implements PaymentGatewayAdapter
{
    private function key(PaymentGatewayProvider $provider): string
    {
        $key = (string) ($provider->credentials['secret_key'] ?? $provider->credentials['secret'] ?? '');
        if ($key === '') throw new RuntimeException('Squad secret key is required.');
        return $key;
    }

    private function request(PaymentGatewayProvider $provider)
    {
        return Http::acceptJson()->withToken($this->key($provider))->asJson()->timeout(30);
    }

    private function result($response): array
    {
        $body = $response->json();
        if (!$response->successful() || !($body['success'] ?? false)) {
            throw new RuntimeException((string) ($body['message'] ?? 'Squad request failed.'));
        }
        return is_array($body['data'] ?? null) ? $body['data'] : [];
    }

    public function initializeCollection(PaymentGatewayProvider $provider, array $payload): array
    {
        return $this->result($this->request($provider)->post(
            rtrim($provider->base_url ?: 'https://api-d.squadco.com', '/').'/transaction/initiate',
            [
                'amount' => (int) $payload['amount_minor'],
                'email' => $payload['customer_email'],
                'key' => $provider->credentials['public_key'] ?? null,
                'currency' => strtoupper($payload['currency'] ?? 'NGN'),
                'initiate_type' => 'inline',
                'CallBack_URL' => $payload['redirect_url'] ?? null,
                'transaction_reference' => $payload['reference'],
                'webhook_url' => $payload['webhook_url'] ?? ($payload['callback_url'] ?? null),
                'customer_name' => $payload['customer_name'] ?? null,
                'payment_channels' => $payload['channels'] ?? ['card', 'bank', 'ussd', 'transfer'],
                'pass_charge' => (bool) ($payload['merchant_bears_cost'] ?? false) === false,
            ]
        ));
    }

    public function verifyCollection(PaymentGatewayProvider $provider, string $reference): array
    {
        return $this->result($this->request($provider)->get(
            rtrim($provider->base_url ?: 'https://api-d.squadco.com', '/').'/transaction/verify/'.$reference
        ));
    }

    public function nameEnquiry(PaymentGatewayProvider $provider, string $bankCode, string $accountNumber): array
    {
        throw new RuntimeException('Squad name enquiry is not enabled in this collection adapter.');
    }

    public function singlePayout(PaymentGatewayProvider $provider, array $payload): array
    {
        throw new RuntimeException('Squad payout is not enabled in this collection adapter.');
    }

    public function bulkPayout(PaymentGatewayProvider $provider, array $payload): array
    {
        throw new RuntimeException('Squad bulk payout is not enabled in this collection adapter.');
    }

    public function refund(PaymentGatewayProvider $provider, array $payload): array
    {
        throw new RuntimeException('Squad refund is not enabled in this collection adapter.');
    }

    public function healthCheck(PaymentGatewayProvider $provider): bool
    {
        $response = $this->request($provider)->get(
            rtrim($provider->base_url ?: 'https://api-d.squadco.com', '/').'/virtual-account/merchant/transactions'
        );
        $this->result($response);
        return true;
    }
}
