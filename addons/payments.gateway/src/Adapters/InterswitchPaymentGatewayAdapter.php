<?php

namespace Semizzy\Addons\Payments\Adapters;

use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Semizzy\Addons\Payments\Contracts\PaymentGatewayAdapter;
use Semizzy\Addons\Payments\Models\PaymentGatewayProvider;

/**
 * Interswitch Payouts API adapter.
 *
 * Uses the documented Payouts API (not the separate legacy Quickteller Send Money API).
 * Collection, refunds and bulk payouts remain explicitly unsupported until their
 * exact product contracts are configured and verified.
 */
final class InterswitchPaymentGatewayAdapter implements PaymentGatewayAdapter
{
    private function base(PaymentGatewayProvider $provider): string
    {
        return rtrim($provider->base_url ?: 'https://payouts-sandbox.interswitchng.com/api/v1/payouts', '/');
    }

    private function credentials(PaymentGatewayProvider $provider): array
    {
        $credentials = $provider->credentials;
        $clientId = (string) ($credentials['client_id'] ?? '');
        $clientSecret = (string) ($credentials['client_secret'] ?? '');
        if ($clientId === '' || $clientSecret === '') {
            throw new RuntimeException('Interswitch client_id and client_secret are required.');
        }
        return [$credentials, $clientId, $clientSecret];
    }

    private function token(PaymentGatewayProvider $provider): string
    {
        [$credentials, $clientId, $clientSecret] = $this->credentials($provider);
        $cacheKey = 'payment-gateway:interswitch:token:'.$provider->id;
        return Cache::remember($cacheKey, now()->addMinutes(45), function () use ($credentials, $clientId, $clientSecret) {
            $tokenUrl = (string) ($credentials['token_url'] ?? 'https://passport-sandbox.interswitchng.com/passport/oauth/token');
            $response = Http::acceptJson()
                ->withBasicAuth($clientId, $clientSecret)
                ->asForm()
                ->timeout(20)
                ->post($tokenUrl, ['grant_type' => 'client_credentials', 'scope' => 'profile']);

            $body = $response->json();
            $token = is_array($body) ? (string) ($body['access_token'] ?? '') : '';
            if (!$response->successful() || $token === '') {
                throw new RuntimeException('Interswitch authentication failed; verify the configured client credentials and environment.');
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

    private function successful($response): array
    {
        $body = $response->json();
        if (!$response->successful() || !is_array($body)) {
            throw new RuntimeException('Interswitch payout request failed; check the provider status before retrying.');
        }
        $code = (string) ($body['responseCode'] ?? '');
        $description = strtoupper((string) ($body['responseDescription'] ?? ''));
        if ($code !== '' && $code !== '00' && !in_array($description, ['SUCCESS', 'SUCCESSFUL', 'APPROVED OR COMPLETED SUCCESSFULLY'], true)) {
            throw new RuntimeException('Interswitch returned a non-success response.');
        }
        return $body;
    }

    public function initializeCollection(PaymentGatewayProvider $provider, array $payload): array
    {
        throw new RuntimeException('Interswitch collection is a separate product and is not implemented by the Payouts API adapter.');
    }

    public function verifyCollection(PaymentGatewayProvider $provider, string $reference): array
    {
        throw new RuntimeException('Interswitch collection verification is not implemented by the Payouts API adapter.');
    }

    public function nameEnquiry(PaymentGatewayProvider $provider, string $bankCode, string $accountNumber): array
    {
        if (!preg_match('/^\d{10}$/', $accountNumber)) {
            throw new RuntimeException('Interswitch Nigerian account number must contain exactly 10 digits.');
        }
        $reference = 'SEMIZZY-LOOKUP-'.strtoupper(bin2hex(random_bytes(8)));
        return $this->successful($this->request($provider)->post($this->base($provider).'/customer-lookup', [
            'payoutChannel' => 'BANK_TRANSFER',
            'transactionReference' => $reference,
            'recipient' => [
                'recipientAccount' => $accountNumber,
                'recipientBank' => $bankCode,
                'currencyCode' => 'NGN',
            ],
        ]));
    }

    public function singlePayout(PaymentGatewayProvider $provider, array $payload): array
    {
        [$credentials] = $this->credentials($provider);
        $amount = $payload['amount'] ?? null;
        if (!is_numeric($amount) || (float) $amount <= 0) {
            throw new RuntimeException('Interswitch payout amount must be a positive amount in major currency units.');
        }
        $reference = trim((string) ($payload['reference'] ?? ''));
        $account = (string) ($payload['account_number'] ?? '');
        $bank = (string) ($payload['bank_code'] ?? '');
        $walletId = (string) ($payload['wallet_id'] ?? $credentials['wallet_id'] ?? '');
        $pin = (string) ($payload['wallet_pin'] ?? $credentials['wallet_pin'] ?? '');
        if ($reference === '' || !preg_match('/^\d{10}$/', $account) || $bank === '' || $walletId === '' || $pin === '') {
            throw new RuntimeException('Interswitch payout requires reference, 10-digit account number, bank code, wallet ID, and wallet PIN.');
        }

        $body = [
            'transactionReference' => $reference,
            'payoutChannel' => 'BANK_TRANSFER',
            'currencyCode' => strtoupper($payload['currency'] ?? 'NGN'),
            'amount' => round((float) $amount, 2),
            'narration' => mb_substr((string) ($payload['narration'] ?? 'SEMIZZY ONE transfer'), 0, 200),
            'walletDetails' => ['pin' => $pin, 'walletId' => $walletId],
            'recipient' => [
                'recipientAccount' => $account,
                'recipientBank' => $bank,
                'currencyCode' => strtoupper($payload['currency'] ?? 'NGN'),
            ],
            'singleCall' => true,
        ];
        if (!empty($payload['source_account_name'])) $body['sourceAccountName'] = $payload['source_account_name'];
        if (!empty($payload['source_account_number'])) $body['sourceAccountNumber'] = $payload['source_account_number'];

        return $this->successful($this->request($provider)->post($this->base($provider), $body));
    }

    public function bulkPayout(PaymentGatewayProvider $provider, array $payload): array
    {
        throw new RuntimeException('Interswitch bulk payout is not enabled in this adapter; the documented Payouts API bulk contract must be verified before use.');
    }

    public function refund(PaymentGatewayProvider $provider, array $payload): array
    {
        throw new RuntimeException('Interswitch refunds are not available through the documented Payouts API adapter.');
    }

    public function verifyRefund(PaymentGatewayProvider $provider, string $refundReference, array $context = []): array
    {
        throw new RuntimeException('Interswitch refund verification is not implemented; no refund should be marked settled through this adapter.');
    }

    public function healthCheck(PaymentGatewayProvider $provider): bool
    {
        $this->token($provider);
        return true;
    }
}
