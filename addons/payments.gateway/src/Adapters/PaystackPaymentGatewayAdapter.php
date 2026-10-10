<?php

namespace Semizzy\Addons\Payments\Adapters;

use Illuminate\Support\Facades\Http;
use RuntimeException;
use Semizzy\Addons\Payments\Contracts\PaymentGatewayAdapter;
use Semizzy\Addons\Payments\Contracts\PayoutReconciliationAdapter;
use Semizzy\Addons\Payments\Models\PaymentGatewayProvider;

final class PaystackPaymentGatewayAdapter implements PaymentGatewayAdapter, PayoutReconciliationAdapter
{
    private function base(PaymentGatewayProvider $provider): string
    {
        return rtrim($provider->base_url ?: 'https://api.paystack.co', '/');
    }

    private function request(PaymentGatewayProvider $provider)
    {
        $key = (string) ($provider->credentials['secret_key'] ?? $provider->credentials['secret'] ?? '');
        if ($key === '') {
            throw new RuntimeException('Paystack secret key is required.');
        }

        return Http::acceptJson()->withToken($key)->asJson()->timeout(30);
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
        return $this->result($this->request($provider)->post($this->base($provider).'/transaction/initialize', [
            'email' => $payload['customer_email'],
            'amount' => (string) $payload['amount_minor'],
            'currency' => strtoupper($payload['currency'] ?? 'NGN'),
            'reference' => $payload['reference'],
            'callback_url' => $payload['redirect_url'] ?? null,
            'metadata' => $payload['metadata'] ?? [],
            'channels' => $payload['channels'] ?? ['card', 'bank', 'ussd', 'bank_transfer'],
        ]));
    }

    public function verifyCollection(PaymentGatewayProvider $provider, string $reference): array
    {
        return $this->result($this->request($provider)->get($this->base($provider).'/transaction/verify/'.rawurlencode($reference)));
    }

    public function nameEnquiry(PaymentGatewayProvider $provider, string $bankCode, string $accountNumber): array
    {
        if (!preg_match('/^\d{10}$/', $accountNumber)) {
            throw new RuntimeException('Paystack Nigerian account number must contain exactly 10 digits.');
        }

        return $this->result($this->request($provider)->get($this->base($provider).'/bank/resolve', [
            'account_number' => $accountNumber,
            'bank_code' => $bankCode,
        ]));
    }

    private function recipient(PaymentGatewayProvider $provider, array $payload): string
    {
        $existing = trim((string) ($payload['recipient_code'] ?? ''));
        if ($existing !== '') {
            return $existing;
        }

        $accountNumber = (string) ($payload['account_number'] ?? '');
        $bankCode = (string) ($payload['bank_code'] ?? '');
        $name = trim((string) ($payload['account_name'] ?? $payload['name'] ?? ''));
        if (!preg_match('/^\d{10}$/', $accountNumber) || $bankCode === '' || $name === '') {
            throw new RuntimeException('Paystack payout requires a 10-digit account number, bank code, and beneficiary name or a verified recipient_code.');
        }

        $data = $this->result($this->request($provider)->post($this->base($provider).'/transferrecipient', [
            'type' => 'nuban',
            'name' => $name,
            'account_number' => $accountNumber,
            'bank_code' => $bankCode,
            'currency' => strtoupper($payload['currency'] ?? 'NGN'),
            'metadata' => $payload['metadata'] ?? [],
        ]));
        $code = trim((string) ($data['recipient_code'] ?? ''));
        if ($code === '') {
            throw new RuntimeException('Paystack did not return a transfer recipient code.');
        }

        return $code;
    }

    public function singlePayout(PaymentGatewayProvider $provider, array $payload): array
    {
        $amount = $payload['amount_minor'] ?? $payload['amount'] ?? null;
        if (!is_numeric($amount) || (float) $amount <= 0 || (string) (int) $amount !== (string) $amount) {
            throw new RuntimeException('Paystack payout amount must be a positive integer in minor currency units.');
        }
        $reference = strtolower(trim((string) ($payload['reference'] ?? '')));
        if (!preg_match('/^[a-z0-9_-]{16,50}$/', $reference)) {
            throw new RuntimeException('Paystack payout reference must be 16–50 lowercase letters, digits, underscores or hyphens.');
        }

        $recipient = $this->recipient($provider, $payload);
        return $this->result($this->request($provider)->post($this->base($provider).'/transfer', [
            'source' => 'balance',
            'amount' => (int) $amount,
            'recipient' => $recipient,
            'reference' => $reference,
            'reason' => $payload['reason'] ?? $payload['narration'] ?? 'SEMIZZY ONE payout',
            'currency' => strtoupper($payload['currency'] ?? 'NGN'),
        ]));
    }

    public function bulkPayout(PaymentGatewayProvider $provider, array $payload): array
    {
        $transactions = $payload['transactions'] ?? null;
        if (!is_array($transactions) || $transactions === []) {
            throw new RuntimeException('Paystack bulk payout requires a non-empty transactions list.');
        }
        $normalized = [];
        foreach ($transactions as $item) {
            if (!is_array($item)) {
                throw new RuntimeException('Each Paystack bulk payout item must be an object.');
            }
            $amount = $item['amount_minor'] ?? $item['amount'] ?? null;
            if (!is_numeric($amount) || (float) $amount <= 0 || (string) (int) $amount !== (string) $amount) {
                throw new RuntimeException('Each Paystack bulk payout amount must be a positive integer in minor currency units.');
            }
            $reference = strtolower(trim((string) ($item['reference'] ?? '')));
            if (!preg_match('/^[a-z0-9_-]{16,50}$/', $reference)) {
                throw new RuntimeException('Each Paystack bulk payout reference must be 16–50 lowercase letters, digits, underscores or hyphens.');
            }
            $normalized[] = [
                'amount' => (int) $amount,
                'recipient' => $this->recipient($provider, $item),
                'reference' => $reference,
                'reason' => $item['reason'] ?? $item['narration'] ?? 'SEMIZZY ONE bulk payout',
            ];
        }

        return $this->result($this->request($provider)->post($this->base($provider).'/transfer/bulk', [
            'source' => 'balance',
            'currency' => strtoupper($payload['currency'] ?? 'NGN'),
            'transfers' => $normalized,
        ]));
    }

    public function refund(PaymentGatewayProvider $provider, array $payload): array
    {
        $transaction = trim((string) ($payload['transaction'] ?? $payload['transaction_id'] ?? $payload['transaction_reference'] ?? ''));
        if ($transaction === '') {
            throw new RuntimeException('Paystack refund requires the original transaction ID or reference.');
        }
        $body = ['transaction' => $transaction];
        if (isset($payload['amount_minor'])) {
            if (!is_numeric($payload['amount_minor']) || (float) $payload['amount_minor'] <= 0 || (string) (int) $payload['amount_minor'] !== (string) $payload['amount_minor']) {
                throw new RuntimeException('Paystack refund amount must be a positive integer in minor currency units.');
            }
            $body['amount'] = (int) $payload['amount_minor'];
        }
        if (isset($payload['customer_note'])) $body['customer_note'] = (string) $payload['customer_note'];
        if (isset($payload['merchant_note'])) $body['merchant_note'] = (string) $payload['merchant_note'];

        return $this->result($this->request($provider)->post($this->base($provider).'/refund', $body));
    }

    public function verifyRefund(PaymentGatewayProvider $provider, string $refundReference, array $context = []): array
    {
        $refundReference = trim($refundReference);
        if ($refundReference === '') {
            throw new RuntimeException('Paystack refund ID is required for status verification.');
        }

        if (ctype_digit($refundReference)) {
            return $this->result($this->request($provider)->get($this->base($provider).'/refund/'.rawurlencode($refundReference)));
        }

        $query = ['perPage' => 100, 'page' => 1];
        if (!empty($context['transaction'])) $query['transaction'] = $context['transaction'];
        if (!empty($context['from'])) $query['from'] = $context['from'];
        if (!empty($context['to'])) $query['to'] = $context['to'];
        $data = $this->result($this->request($provider)->get($this->base($provider).'/refund', $query));
        if (isset($data['id']) && hash_equals($refundReference, (string) $data['id'])) {
            return $data;
        }
        $rows = is_array($data['data'] ?? null) ? $data['data'] : $data;
        foreach (is_array($rows) ? $rows : [] as $row) {
            if (is_array($row) && hash_equals($refundReference, (string) ($row['id'] ?? $row['refund_reference'] ?? ''))) {
                return $row;
            }
        }

        throw new RuntimeException('Paystack did not return a refund matching the requested refund ID.');
    }

    /**
     * Reconcile a payout using Paystack's transfer verification endpoint.
     * The returned status is provider-reported; callers must only settle on an
     * explicitly terminal success state and must never infer success from initiation.
     */
    public function verifyPayout(PaymentGatewayProvider $provider, string $reference): array
    {
        $reference = strtolower(trim($reference));
        if (!preg_match('/^[a-z0-9_-]{16,50}$/', $reference)) {
            throw new RuntimeException('A valid Paystack transfer reference is required for reconciliation.');
        }

        $data = $this->result($this->request($provider)->get(
            $this->base($provider).'/transfer/verify/'.rawurlencode($reference)
        ));
        $returnedReference = strtolower(trim((string) ($data['reference'] ?? '')));
        if ($returnedReference === '' || !hash_equals($reference, $returnedReference)) {
            throw new RuntimeException('Paystack transfer verification returned a different reference.');
        }
        if (trim((string) ($data['status'] ?? '')) === '') {
            throw new RuntimeException('Paystack transfer verification returned no status; keep the transaction unresolved.');
        }

        return $data;
    }

    public function healthCheck(PaymentGatewayProvider $provider): bool
    {
        $this->result($this->request($provider)->get($this->base($provider).'/bank'));
        return true;
    }
}
