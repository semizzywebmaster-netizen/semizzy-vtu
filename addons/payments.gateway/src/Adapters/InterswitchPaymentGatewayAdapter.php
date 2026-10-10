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
 * Collection and refunds remain explicitly unsupported until their
 * exact product contracts are configured and verified.
 */
final class InterswitchPaymentGatewayAdapter implements PaymentGatewayAdapter, BankAccountVerificationAdapter
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

    /**
     * Verify a Nigerian bank account using Interswitch's separate account-name
     * validation contract. This intentionally does not use the Payouts OAuth API.
     * Configure account_verification_url, client_id, secret_key (or client_secret),
     * and terminal_id with the credentials issued for this product.
     */
    public function verifyAccount(PaymentGatewayProvider $provider, string $bankCode, string $accountNumber): array
    {
        $credentials = $provider->credentials;
        $clientId = trim((string) ($credentials['client_id'] ?? ''));
        $secret = (string) ($credentials['secret_key'] ?? $credentials['client_secret'] ?? '');
        $terminalId = trim((string) ($credentials['terminal_id'] ?? ''));
        if ($clientId === '' || $secret === '' || $terminalId === '') {
            throw new RuntimeException('Interswitch account verification requires client_id, secret_key, and terminal_id.');
        }
        if (!preg_match('/^\d{10}$/', $accountNumber) || trim($bankCode) === '') {
            throw new RuntimeException('Interswitch account verification requires a bank code and a 10-digit account number.');
        }

        $url = rtrim((string) ($credentials['account_verification_url'] ?? 'https://sandbox.interswitchng.com/api/v1/nameenquiry/banks/accounts/names'), '/');
        $query = ['bankCode' => trim($bankCode), 'accountId' => $accountNumber];
        $endpoint = $url.'?'.http_build_query($query, '', '&', PHP_QUERY_RFC3986);
        $timestamp = (string) time();
        $nonce = bin2hex(random_bytes(16));
        $signatureBase = 'GET&'.urlencode($endpoint).'&'.$timestamp.'&'.$nonce.'&'.$clientId.'&'.$secret;
        $signature = base64_encode(sha1($signatureBase, true));

        $response = Http::acceptJson()->withHeaders([
            'Authorization' => 'InterswitchAuth '.base64_encode($clientId),
            'Signature' => $signature,
            'Timestamp' => $timestamp,
            'Nonce' => $nonce,
            'SignatureMethod' => 'SHA1',
            'TerminalID' => $terminalId,
        ])->timeout(20)->get($endpoint);

        $body = $response->json();
        if (!$response->successful() || !is_array($body)) {
            throw new RuntimeException('Interswitch account verification failed; do not assume the beneficiary is valid.');
        }
        $code = (string) ($body['responseCode'] ?? $body['ResponseCode'] ?? $body['code'] ?? '');
        if ($code !== '00') {
            throw new RuntimeException('Interswitch did not verify the bank account.');
        }
        $name = trim((string) ($body['accountName'] ?? $body['AccountName'] ?? $body['account_name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('Interswitch account verification returned no account name; keep the beneficiary unverified.');
        }

        return $body + ['account_name' => $name, 'account_number' => $accountNumber, 'bank_code' => trim($bankCode), 'verified' => true];
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
        [$credentials] = $this->credentials($provider);
        $transactions = $payload['transactions'] ?? null;
        $walletId = (string) ($payload['wallet_id'] ?? $credentials['wallet_id'] ?? '');
        $pin = (string) ($payload['wallet_pin'] ?? $credentials['wallet_pin'] ?? '');
        if (!is_array($transactions) || count($transactions) < 2 || $walletId === '' || $pin === '') {
            throw new RuntimeException('Interswitch batch payout requires at least two recipients, wallet ID, and wallet PIN.');
        }

        $recipients = [];
        $total = 0.0;
        foreach ($transactions as $item) {
            if (!is_array($item)) throw new RuntimeException('Each Interswitch batch recipient must be an object.');
            $amount = $item['amount'] ?? null;
            $account = (string) ($item['account_number'] ?? '');
            $bank = (string) ($item['bank_code'] ?? '');
            $reference = trim((string) ($item['reference'] ?? ''));
            if (!is_numeric($amount) || (float) $amount <= 0 || !preg_match('/^\\d{10}$/', $account) || $bank === '' || $reference === '') {
                throw new RuntimeException('Each Interswitch batch recipient requires a positive amount, 10-digit account number, bank code, and unique reference.');
            }
            $amount = round((float) $amount, 2);
            $total += $amount;
            $recipients[] = [
                'transactionReference' => $reference,
                'recipientName' => (string) ($item['account_name'] ?? ''),
                'recipientAccount' => $account,
                'recipientBank' => $bank,
                'amount' => $amount,
                'currencyCode' => strtoupper($item['currency'] ?? $payload['currency'] ?? 'NGN'),
            ];
        }

        return $this->successful($this->request($provider)->post($this->base($provider).'/batch', [
            'payoutChannel' => 'BANK_TRANSFER',
            'narration' => mb_substr((string) ($payload['narration'] ?? 'SEMIZZY ONE bulk payout'), 0, 200),
            'currencyCode' => strtoupper($payload['currency'] ?? 'NGN'),
            'amount' => round($total, 2),
            'walletDetails' => ['pin' => $pin, 'walletId' => $walletId],
            'recipients' => $recipients,
        ]));
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
