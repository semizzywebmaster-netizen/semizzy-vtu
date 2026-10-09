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

    public function nameEnquiry(PaymentGatewayProvider $provider, string $bankCode, string $accountNumber): array
    {
        if (!preg_match('/^\\d{3,10}$/', $bankCode) || !preg_match('/^\\d{6,20}$/', $accountNumber)) {
            throw new RuntimeException('A valid bank code and account number are required.');
        }

        $data = $this->result($this->request($provider)->get(
            rtrim($provider->base_url ?: 'https://api.paystack.co', '/') . '/bank/resolve',
            ['account_number' => $accountNumber, 'bank_code' => $bankCode]
        ));

        if (empty($data['account_name'])) {
            throw new RuntimeException('Paystack did not return a verified account name.');
        }

        return $data;
    }

    public function singlePayout(PaymentGatewayProvider $provider, array $payload): array
    {
        $reference = (string) ($payload['reference'] ?? '');
        $amount = filter_var($payload['amount_minor'] ?? null, FILTER_VALIDATE_INT);
        $bankCode = (string) ($payload['bank_code'] ?? '');
        $accountNumber = (string) ($payload['account_number'] ?? '');

        if (!preg_match('/^[a-z0-9_-]{16,50}$/', $reference)) {
            throw new RuntimeException('A unique Paystack transfer reference of 16-50 lowercase letters, digits, dashes or underscores is required.');
        }
        if ($amount === false || $amount < 1) {
            throw new RuntimeException('Payout amount_minor must be a positive integer in kobo.');
        }
        if (strtoupper((string) ($payload['currency'] ?? 'NGN')) !== 'NGN') {
            throw new RuntimeException('This Paystack payout adapter currently permits NGN bank transfers only.');
        }

        $account = $this->nameEnquiry($provider, $bankCode, $accountNumber);
        $name = trim((string) ($payload['account_name'] ?? $account['account_name'] ?? ''));
        if ($name === '') {
            throw new RuntimeException('A verified recipient account name is required before payout.');
        }

        $recipient = $this->result($this->request($provider)->post(
            rtrim($provider->base_url ?: 'https://api.paystack.co', '/') . '/transferrecipient',
            [
                'type' => 'nuban',
                'name' => $name,
                'account_number' => $accountNumber,
                'bank_code' => $bankCode,
                'currency' => 'NGN',
            ]
        ));
        $recipientCode = (string) ($recipient['recipient_code'] ?? '');
        if ($recipientCode === '') {
            throw new RuntimeException('Paystack did not return a transfer recipient code; payout was not initiated.');
        }

        // The caller must persist this unique reference before calling. A retry must
        // verify this reference first rather than blindly starting another transfer.
        $transfer = $this->result($this->request($provider)->post(
            rtrim($provider->base_url ?: 'https://api.paystack.co', '/') . '/transfer',
            [
                'source' => 'balance',
                'amount' => $amount,
                'reference' => $reference,
                'recipient' => $recipientCode,
                'reason' => mb_substr((string) ($payload['reason'] ?? 'SEMIZZY ONE payout'), 0, 100),
                'currency' => 'NGN',
            ]
        ));

        return [
            'reference' => $transfer['reference'] ?? $reference,
            'transfer_code' => $transfer['transfer_code'] ?? null,
            'recipient_code' => $recipientCode,
            'status' => strtolower((string) ($transfer['status'] ?? 'unknown')),
            'amount_minor' => (int) ($transfer['amount'] ?? $amount),
            'currency' => strtoupper((string) ($transfer['currency'] ?? 'NGN')),
            'requires_otp' => strtolower((string) ($transfer['status'] ?? '')) === 'otp',
            'provider_response' => $transfer,
        ];
    }

    public function bulkPayout(PaymentGatewayProvider $provider, array $payload): array
    {
        $transfers = $payload['transfers'] ?? null;
        if (!is_array($transfers) || count($transfers) < 1 || count($transfers) > 100) {
            throw new RuntimeException('Paystack bulk transfer requires 1-100 pre-created recipient transfers.');
        }

        $normalized = [];
        foreach ($transfers as $transfer) {
            if (!is_array($transfer)) throw new RuntimeException('Each bulk transfer must be an object.');
            $reference = (string) ($transfer['reference'] ?? '');
            $amount = filter_var($transfer['amount_minor'] ?? null, FILTER_VALIDATE_INT);
            $recipient = (string) ($transfer['recipient_code'] ?? '');
            if (!preg_match('/^[a-z0-9_-]{16,50}$/', $reference) || $amount === false || $amount < 1 || $recipient === '') {
                throw new RuntimeException('Every bulk transfer requires a unique valid reference, positive amount_minor in kobo, and existing recipient_code.');
            }
            $normalized[] = [
                'amount' => $amount,
                'recipient' => $recipient,
                'reference' => $reference,
                'reason' => mb_substr((string) ($transfer['reason'] ?? 'SEMIZZY ONE bulk bank transfer'), 0, 100),
            ];
        }
        if (count(array_unique(array_column($normalized, 'reference'))) !== count($normalized)) {
            throw new RuntimeException('Bulk transfer references must be unique within the batch.');
        }

        return $this->result($this->request($provider)->post(
            rtrim($provider->base_url ?: 'https://api.paystack.co', '/') . '/transfer/bulk',
            ['source' => 'balance', 'currency' => 'NGN', 'transfers' => $normalized]
        ));
    }

    /** Query Paystack by the caller's persisted reference before retrying an ambiguous payout. */
    public function verifyPayout(PaymentGatewayProvider $provider, string $reference): array
    {
        if (!preg_match('/^[a-z0-9_-]{16,50}$/', $reference)) {
            throw new RuntimeException('A valid Paystack transfer reference is required.');
        }
        return $this->result($this->request($provider)->get(
            rtrim($provider->base_url ?: 'https://api.paystack.co', '/') . '/transfer/verify/' . rawurlencode($reference)
        ));
    }
    public function refund(PaymentGatewayProvider $provider, array $payload): array { throw new RuntimeException('Paystack refund adapter not enabled in this bulk action.'); }

    public function verifyRefund(PaymentGatewayProvider $provider, string $refundReference, array $context = []): array
    {
        throw new RuntimeException('Verified refund status lookup is not implemented for this provider; do not settle the wallet from a refund request response.');
    }

    public function healthCheck(PaymentGatewayProvider $provider): bool
    {
        $response = $this->request($provider)->get(rtrim($provider->base_url ?: 'https://api.paystack.co', '/').'/bank');
        $this->result($response);
        return true;
    }
}
