<?php

namespace Semizzy\Addons\Payments\Adapters;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;
use Semizzy\Addons\Payments\Exceptions\AmbiguousPaymentGatewayException;
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

        // Idempotency guard: if this reference already exists, return its provider state
        // instead of creating another transfer. Only a clear not-found response allows creation.
        $verifyUrl = rtrim($provider->base_url ?: 'https://api.paystack.co', '/') . '/transfer/verify/' . rawurlencode($reference);
        try {
            $existingResponse = $this->request($provider)->get($verifyUrl);
        } catch (Throwable $exception) {
            throw new AmbiguousPaymentGatewayException('Paystack could not verify the transfer reference; failover is suppressed to prevent a duplicate payout.', 0, $exception);
        }
        $existingBody = $existingResponse->json();
        if ($existingResponse->successful() && ($existingBody['status'] ?? false) && is_array($existingBody['data'] ?? null)) {
            $existing = $existingBody['data'];
            return [
                'reference' => $existing['reference'] ?? $reference,
                'transfer_code' => $existing['transfer_code'] ?? null,
                'recipient_code' => null,
                'status' => strtolower((string) ($existing['status'] ?? 'unknown')),
                'amount_minor' => (int) ($existing['amount'] ?? $amount),
                'currency' => strtoupper((string) ($existing['currency'] ?? 'NGN')),
                'requires_otp' => strtolower((string) ($existing['status'] ?? '')) === 'otp',
                'provider_response' => $existing,
                'replayed' => true,
            ];
        }
        if ($existingResponse->status() === 408 || $existingResponse->status() === 429 || $existingResponse->status() >= 500 || $existingResponse->successful()) {
            throw new AmbiguousPaymentGatewayException('Paystack could not establish whether this transfer reference already exists; failover is suppressed.');
        }
        // Only a documented not-found response proves that this reference has not
        // been used. Other 4xx responses may indicate auth/configuration problems.
        if ($existingResponse->status() !== 404) {
            throw new RuntimeException('Paystack transfer-reference verification failed before payout initiation.');
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
        try {
            $transferResponse = $this->request($provider)->post(
                rtrim($provider->base_url ?: 'https://api.paystack.co', '/') . '/transfer',
                [
                    'source' => 'balance',
                    'amount' => $amount,
                    'reference' => $reference,
                    'recipient' => $recipientCode,
                    'reason' => mb_substr((string) ($payload['reason'] ?? 'SEMIZZY ONE payout'), 0, 100),
                    'currency' => 'NGN',
                ]
            );
        } catch (Throwable $exception) {
            throw new AmbiguousPaymentGatewayException('Paystack transfer request outcome is unknown; failover is suppressed until this reference is verified.', 0, $exception);
        }
        if ($transferResponse->status() === 408 || $transferResponse->status() === 429 || $transferResponse->status() >= 500) {
            throw new AmbiguousPaymentGatewayException('Paystack transfer outcome is unknown; failover is suppressed until this reference is verified.');
        }
        $transfer = $this->result($transferResponse);
        if (empty($transfer['reference']) && empty($transfer['transfer_code'])) {
            throw new AmbiguousPaymentGatewayException('Paystack response did not provide a transfer reference; failover is suppressed.');
        }

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

        // Preflight every persisted reference. Existing or uncertain references block
        // the entire batch so a retry cannot pay the same recipient twice.
        $base = rtrim($provider->base_url ?: 'https://api.paystack.co', '/');
        foreach ($normalized as $transfer) {
            try {
                $existingResponse = $this->request($provider)->get($base . '/transfer/verify/' . rawurlencode($transfer['reference']));
            } catch (Throwable $exception) {
                throw new AmbiguousPaymentGatewayException('Paystack could not verify every bulk reference; no batch was submitted.', 0, $exception);
            }
            if ($existingResponse->status() !== 404) {
                if ($existingResponse->successful() || $existingResponse->status() === 408 || $existingResponse->status() === 429 || $existingResponse->status() >= 500) {
                    throw new AmbiguousPaymentGatewayException('At least one Paystack bulk reference already exists or has an uncertain state; no batch was submitted.');
                }
                throw new RuntimeException('Paystack could not verify a bulk transfer reference; no batch was submitted.');
            }
        }

        try {
            $response = $this->request($provider)->post(
                rtrim($provider->base_url ?: 'https://api.paystack.co', '/') . '/transfer/bulk',
                ['source' => 'balance', 'transfers' => $normalized]
            );
        } catch (Throwable $exception) {
            throw new AmbiguousPaymentGatewayException('Paystack bulk transfer outcome is unknown; automatic failover is suppressed.', 0, $exception);
        }
        if ($response->status() === 408 || $response->status() === 429 || $response->status() >= 500) {
            throw new AmbiguousPaymentGatewayException('Paystack bulk transfer outcome is unknown; automatic failover is suppressed.');
        }
        return $this->result($response);
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
