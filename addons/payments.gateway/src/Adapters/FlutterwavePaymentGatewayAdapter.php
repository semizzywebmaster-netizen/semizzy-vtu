<?php

namespace Semizzy\Addons\Payments\Adapters;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Semizzy\Addons\Payments\Contracts\PaymentGatewayAdapter;
use Semizzy\Addons\Payments\Models\PaymentGatewayProvider;

final class FlutterwavePaymentGatewayAdapter implements PaymentGatewayAdapter
{
    private function request(PaymentGatewayProvider $provider)
    {
        $secretKey = (string) ($provider->credentials['secret_key'] ?? '');
        if ($secretKey === '') {
            throw new RuntimeException('Flutterwave secret key is required.');
        }

        return Http::acceptJson()
            ->withToken($secretKey)
            ->asJson()
            ->timeout(30);
    }

    private function result($response): array
    {
        $body = $response->json();
        if (!$response->successful() || ($body['status'] ?? '') !== 'success') {
            throw new RuntimeException($body['message'] ?? 'Flutterwave request failed.');
        }

        return is_array($body['data'] ?? null) ? $body['data'] : [];
    }

    private function base(PaymentGatewayProvider $provider): string
    {
        return rtrim($provider->base_url ?: 'https://api.flutterwave.com/v3', '/');
    }

    public function initializeCollection(PaymentGatewayProvider $provider, array $payload): array
    {
        return $this->result($this->request($provider)->post($this->base($provider).'/payments', [
            'amount' => $payload['amount'],
            'tx_ref' => $payload['reference'],
            'currency' => $payload['currency'] ?? 'NGN',
            'redirect_url' => $payload['redirect_url'] ?? null,
            'customer' => [
                'email' => $payload['customer_email'],
                'phone_number' => $payload['customer_phone'] ?? null,
                'name' => $payload['customer_name'] ?? null,
            ],
            'meta' => $payload['metadata'] ?? [],
        ]));
    }

    public function verifyCollection(PaymentGatewayProvider $provider, string $reference): array
    {
        return $this->result($this->request($provider)->get(
            $this->base($provider).'/transactions/verify_by_reference',
            ['tx_ref' => $reference]
        ));
    }

    public function nameEnquiry(PaymentGatewayProvider $provider, string $bankCode, string $accountNumber): array
    {
        return $this->result($this->request($provider)->post($this->base($provider).'/accounts/resolve', [
            'account_number' => $accountNumber,
            'account_bank' => $bankCode,
        ]));
    }

    public function singlePayout(PaymentGatewayProvider $provider, array $payload): array
    {
        return $this->result($this->request($provider)->post($this->base($provider).'/transfers', [
            'account_bank' => $payload['bank_code'],
            'account_number' => $payload['account_number'],
            'amount' => $payload['amount'],
            'currency' => $payload['currency'] ?? 'NGN',
            'debit_currency' => $payload['debit_currency'] ?? ($payload['currency'] ?? 'NGN'),
            'beneficiary_name' => $payload['account_name'] ?? null,
            'reference' => $payload['reference'],
            'narration' => $payload['narration'] ?? 'SEMIZZY ONE transfer',
            'callback_url' => $payload['callback_url'] ?? null,
            'meta' => $payload['metadata'] ?? [],
        ]));
    }

    public function bulkPayout(PaymentGatewayProvider $provider, array $payload): array
    {
        return $this->result($this->request($provider)->post($this->base($provider).'/bulk-transfers', [
            'title' => $payload['title'] ?? 'SEMIZZY ONE bulk transfer',
            'bulk_data' => $payload['transactions'] ?? [],
            'currency' => $payload['currency'] ?? 'NGN',
            'error_reporting' => $payload['error_reporting'] ?? false,
        ]));
    }

    public function refund(PaymentGatewayProvider $provider, array $payload): array
    {
        $transactionId = (string) ($payload['transaction_id'] ?? $payload['provider_transaction_id'] ?? '');
        if ($transactionId === '') {
            throw new RuntimeException('Flutterwave transaction ID is required for refunds.');
        }

        return $this->result($this->request($provider)->post(
            $this->base($provider).'/transactions/'.rawurlencode($transactionId).'/refund',
            array_filter([
                'amount' => $payload['amount'] ?? null,
                'comments' => $payload['reason'] ?? ($payload['comments'] ?? null),
                'callbackurl' => $payload['callback_url'] ?? null,
            ], static fn ($value) => $value !== null && $value !== '')
        ));
    }

    public function healthCheck(PaymentGatewayProvider $provider): bool
    {
        $this->result($this->request($provider)->get($this->base($provider).'/banks'));
        return true;
    }
}
