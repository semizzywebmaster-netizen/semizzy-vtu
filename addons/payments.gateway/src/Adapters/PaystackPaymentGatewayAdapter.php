<?php

namespace Semizzy\Addons\Payments\Adapters;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Semizzy\Addons\Payments\Contracts\PaymentGatewayAdapter;
use Semizzy\Addons\Payments\Models\PaymentGatewayProvider;

final class PaystackPaymentGatewayAdapter implements PaymentGatewayAdapter
{
    private function request(PaymentGatewayProvider $provider)
    {
        $key = (string) ($provider->credentials['secret_key'] ?? $provider->credentials['secret'] ?? '');
        if ($key === '') throw new RuntimeException('Paystack secret key is required.');

        return Http::acceptJson()
            ->withToken($key)
            ->asJson()
            ->timeout(30);
    }

    private function result($response): array
    {
        $body = $response->json();
        if (!$response->successful() || !($body['status'] ?? false)) {
            throw new RuntimeException((string) ($body['message'] ?? 'Paystack request failed.'));
        }
        return is_array($body['data'] ?? null) ? $body['data'] : [];
    }

    public function initializeCollection(PaymentGatewayProvider $provider, array $payload): array
    {
        return $this->result($this->request($provider)->post(
            rtrim($provider->base_url ?: 'https://api.paystack.co', '/').'/transaction/initialize',
            [
                'email' => $payload['customer_email'],
                'amount' => (string) $payload['amount_minor'],
                'currency' => strtoupper($payload['currency'] ?? 'NGN'),
                'reference' => $payload['reference'],
                'callback_url' => $payload['redirect_url'] ?? null,
                'metadata' => $payload['metadata'] ?? [],
                'channels' => $payload['channels'] ?? ['card', 'bank', 'ussd', 'bank_transfer'],
            ]
        ));
    }

    public function verifyCollection(PaymentGatewayProvider $provider, string $reference): array
    {
        return $this->result($this->request($provider)->get(
            rtrim($provider->base_url ?: 'https://api.paystack.co', '/').'/transaction/verify/'.rawurlencode($reference)
        ));
    }

    public function nameEnquiry(PaymentGatewayProvider $provider, string $bankCode, string $accountNumber): array { throw new RuntimeException('Paystack name enquiry adapter not enabled in this bulk action.'); }
    public function singlePayout(PaymentGatewayProvider $provider, array $payload): array { throw new RuntimeException('Paystack payout adapter not enabled in this bulk action.'); }
    public function bulkPayout(PaymentGatewayProvider $provider, array $payload): array { throw new RuntimeException('Paystack bulk payout adapter not enabled in this bulk action.'); }
    public function refund(PaymentGatewayProvider $provider, array $payload): array { throw new RuntimeException('Paystack refund adapter not enabled in this bulk action.'); }

    public function healthCheck(PaymentGatewayProvider $provider): bool
    {
        $response = $this->request($provider)->get(rtrim($provider->base_url ?: 'https://api.paystack.co', '/').'/bank');
        $this->result($response);
        return true;
    }
}
