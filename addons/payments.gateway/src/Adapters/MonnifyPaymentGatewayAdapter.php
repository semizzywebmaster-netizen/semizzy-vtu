<?php

namespace Semizzy\Addons\Payments\Adapters;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Semizzy\Addons\Payments\Contracts\PaymentGatewayAdapter;
use Semizzy\Addons\Payments\Models\PaymentGatewayProvider;

class MonnifyPaymentGatewayAdapter implements PaymentGatewayAdapter
{
    private function token(PaymentGatewayProvider $provider): string
    {
        $credentials = $provider->credentials;
        $apiKey = (string) ($credentials['api_key'] ?? '');
        $secretKey = (string) ($credentials['secret_key'] ?? '');

        if ($apiKey === '' || $secretKey === '') {
            throw new RuntimeException('Monnify API key and secret key are required.');
        }

        $cacheKey = 'payment-gateway:monnify:token:'.$provider->id;
        return Cache::remember($cacheKey, now()->addMinutes(50), function () use ($provider, $apiKey, $secretKey) {
            $response = Http::acceptJson()
                ->withBasicAuth($apiKey, $secretKey)
                ->timeout(20)
                ->post(rtrim($provider->base_url, '/').'/api/v1/auth/login');

            if (!$response->successful() || !($response->json('requestSuccessful') ?? false)) {
                throw new RuntimeException('Monnify authentication failed: '.mb_substr($response->body(), 0, 500));
            }

            $token = $response->json('responseBody.accessToken');
            if (!$token) {
                throw new RuntimeException('Monnify did not return an access token.');
            }

            return $token;
        });
    }

    private function request(PaymentGatewayProvider $provider)
    {
        return Http::acceptJson()
            ->withToken($this->token($provider))
            ->asJson()
            ->timeout(30);
    }

    private function result($response): array
    {
        $body = $response->json();
        if (!$response->successful() || !($body['requestSuccessful'] ?? false)) {
            throw new RuntimeException($body['responseMessage'] ?? 'Monnify request failed.');
        }

        return $body['responseBody'] ?? [];
    }

    public function initializeCollection(PaymentGatewayProvider $provider, array $payload): array
    {
        return $this->result($this->request($provider)->post(
            rtrim($provider->base_url, '/').'/api/v1/merchant/transactions/init-transaction',
            [
                'amount' => $payload['amount'],
                'customerEmail' => $payload['customer_email'],
                'paymentReference' => $payload['reference'],
                'paymentDescription' => $payload['description'] ?? 'SEMIZZY ONE payment',
                'currencyCode' => $payload['currency'] ?? 'NGN',
                'contractCode' => $provider->credentials['contract_code'] ?? throw new RuntimeException('Monnify contract code is required.'),
                'redirectUrl' => $payload['redirect_url'] ?? null,
                'paymentMethods' => $payload['payment_methods'] ?? ['CARD', 'ACCOUNT_TRANSFER', 'USSD', 'PHONE_NUMBER'],
                'metadata' => $payload['metadata'] ?? [],
            ]
        ));
    }

    public function verifyCollection(PaymentGatewayProvider $provider, string $reference): array
    {
        return $this->result($this->request($provider)->get(
            rtrim($provider->base_url, '/').'/api/v2/merchant/transactions/query',
            ['paymentReference' => $reference]
        ));
    }

    public function nameEnquiry(PaymentGatewayProvider $provider, string $bankCode, string $accountNumber): array
    {
        return $this->result($this->request($provider)->get(
            rtrim($provider->base_url, '/').'/api/v2/disbursements/account/validate',
            ['accountNumber' => $accountNumber, 'bankCode' => $bankCode]
        ));
    }

    public function singlePayout(PaymentGatewayProvider $provider, array $payload): array
    {
        return $this->result($this->request($provider)->post(
            rtrim($provider->base_url, '/').'/api/v2/disbursements/single',
            [
                'amount' => $payload['amount'],
                'reference' => $payload['reference'],
                'narration' => $payload['narration'] ?? 'SEMIZZY ONE transfer',
                'destinationBankCode' => $payload['bank_code'],
                'destinationAccountNumber' => $payload['account_number'],
                'destinationAccountName' => $payload['account_name'],
                'currency' => $payload['currency'] ?? 'NGN',
                'sourceAccountNumber' => $payload['source_account_number'] ?? ($provider->credentials['source_account_number'] ?? null),
                'async' => $payload['async'] ?? true,
            ]
        ));
    }

    public function bulkPayout(PaymentGatewayProvider $provider, array $payload): array
    {
        return $this->result($this->request($provider)->post(
            rtrim($provider->base_url, '/').'/api/v2/disbursements/batch',
            [
                'title' => $payload['title'] ?? 'SEMIZZY ONE bulk transfer',
                'batchReference' => $payload['batch_reference'],
                'narration' => $payload['narration'] ?? 'SEMIZZY ONE bulk transfer',
                'sourceAccountNumber' => $payload['source_account_number'] ?? ($provider->credentials['source_account_number'] ?? null),
                'onValidationFailure' => $payload['on_validation_failure'] ?? 'CONTINUE',
                'notificationInterval' => $payload['notification_interval'] ?? 25,
                'transactionList' => $payload['transactions'],
            ]
        ));
    }

    public function refund(PaymentGatewayProvider $provider, array $payload): array
    {
        throw new RuntimeException('Monnify refunds require provider-side activation; use the adapter capability only after activation.');
    }

    public function verifyRefund(PaymentGatewayProvider $provider, string $refundReference, array $context = []): array
    {
        throw new RuntimeException('Verified refund status lookup is not implemented for this provider; do not settle the wallet from a refund request response.');
    }

    public function healthCheck(PaymentGatewayProvider $provider): bool
    {
        $this->token($provider);
        return true;
    }
}