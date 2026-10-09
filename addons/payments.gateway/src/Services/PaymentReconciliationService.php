<?php

namespace Semizzy\Addons\Payments\Services;

use App\Models\User;
use RuntimeException;
use Semizzy\Addons\Payments\Models\PaymentGatewayProvider;
use Semizzy\Addons\Payments\Models\PaymentIntent;

final class PaymentReconciliationService
{
    public function __construct(private PaymentGatewayManager $gateways) {}

    public function requery(PaymentIntent $payment): PaymentIntent
    {
        if (!$payment->provider_id) throw new RuntimeException('This payment has no assigned gateway provider.');
        $provider = PaymentGatewayProvider::query()->findOrFail($payment->provider_id);
        $reference = (string) ($payment->provider_reference ?: $payment->reference);
        $verified = $this->gateways->adapter($provider)->verifyCollection($provider, $reference);
        $status = strtolower((string) match ($provider->driver) {
            'paystack' => data_get($verified, 'status', ''),
            'monnify' => data_get($verified, 'paymentStatus', ''),
            'opay' => data_get($verified, 'status', ''),
            'kora' => data_get($verified, 'status', ''),
            'squad' => data_get($verified, 'transaction_status', data_get($verified, 'status', '')),
            'flutterwave' => data_get($verified, 'status', ''),
            default => data_get($verified, 'status', ''),
        });
        $success = in_array($status, ['success', 'successful', 'completed', 'complete', 'paid', 'approved'], true);
        $payment->forceFill(['metadata' => array_merge((array) $payment->metadata, [
            'last_requery_at' => now()->toISOString(),
            'last_requery_status' => $status,
            'last_requery_provider' => $provider->code,
        ])])->saveOrFail();
        if ($success && $payment->status !== 'paid') {
            throw new RuntimeException('Provider requery confirms payment success, but automatic wallet credit is intentionally blocked here. Use the verified webhook/reconciliation path to credit the Core wallet.');
        }
        return $payment->fresh();
    }

    public function requestRefund(PaymentIntent $payment, array $payload = []): array
    {
        if (!$payment->provider_id) throw new RuntimeException('This payment has no assigned gateway provider.');
        if ($payment->status !== 'paid') throw new RuntimeException('Only paid payments can be refunded.');
        if ($payment->refunded_at) throw new RuntimeException('This payment is already marked refunded.');

        $provider = PaymentGatewayProvider::query()->findOrFail($payment->provider_id);
        if (!$provider->supports('refund') || !app(PaymentGatewayAdapterRegistry::class)->has($provider->driver)) {
            throw new RuntimeException('The assigned provider does not expose a verified refund capability.');
        }

        // Payment-bound values override all caller-supplied fields. Provider transaction
        // identifiers come from the verified collection response stored on the intent.
        $providerPayload = array_merge($payload, [
            'amount_minor' => (string) $payment->amount_minor,
            'amount' => $this->majorFromMinor((string) $payment->amount_minor),
            'currency' => $payment->currency,
            'original_reference' => $payment->provider_reference ?: $payment->reference,
            'reference' => 'REF-'.$payment->reference,
            'transaction_id' => (string) data_get($payment->metadata, 'provider_transaction_id', ''),
            'reason' => (string) ($payload['reason'] ?? 'Customer refund request'),
        ]);

        $result = $this->gateways->adapter($provider)->refund($provider, $providerPayload);
        $metadata = array_merge((array) $payment->metadata, [
            'refund_requested_at' => now()->toISOString(),
            'refund_provider' => $provider->code,
            'refund_result' => $result,
            'refund_provider_reference' => data_get($result, 'flw_ref')
                ?? data_get($result, 'refund_reference')
                ?? data_get($result, 'reference'),
            'refund_provider_refund_id' => data_get($result, 'id'),
            'refund_accounting_status' => 'pending_provider_confirmation',
        ]);
        $payment->forceFill(['metadata' => $metadata])->saveOrFail();

        return $result;
    }

    /**
     * Verify the refund directly with the assigned provider before attempting
     * Core wallet accounting. Unsupported provider lookups fail closed.
     */
    public function verifyAndSettleRefund(
        PaymentIntent $payment,
        string $providerRefundReference,
        string $reason,
        ?User $actor = null,
        array $context = []
    ): PaymentIntent {
        if (!$payment->provider_id) throw new RuntimeException('This payment has no assigned gateway provider.');
        if ($payment->status !== 'paid' && $payment->status !== 'refunded') {
            throw new RuntimeException('Only paid or already-refunded payment intents can be reconciled for a refund.');
        }

        $provider = PaymentGatewayProvider::query()->findOrFail($payment->provider_id);
        if (!$provider->supports('refund')) {
            throw new RuntimeException('The assigned provider does not declare refund capability.');
        }

        $verified = $this->gateways->adapter($provider)->verifyRefund($provider, $providerRefundReference, $context);
        $returnedReference = (string) (
            data_get($verified, 'flw_ref')
            ?? data_get($verified, 'refund_reference')
            ?? data_get($verified, 'reference')
            ?? ''
        );
        if ($returnedReference === '' || !hash_equals(trim($providerRefundReference), $returnedReference)) {
            throw new RuntimeException('Provider refund verification did not match the requested refund reference.');
        }

        $providerTransactionId = (string) (data_get($verified, 'transaction_id') ?? data_get($verified, 'tx_id') ?? '');
        $expectedTransactionId = (string) data_get($payment->metadata, 'provider_transaction_id', '');
        if ($expectedTransactionId !== '' && $providerTransactionId !== '' && !hash_equals($expectedTransactionId, $providerTransactionId)) {
            throw new RuntimeException('Verified refund belongs to a different provider transaction.');
        }

        $amount = data_get($verified, 'amount_refunded');
        if ($amount !== null && $this->majorToMinor((string) $amount) !== (string) $payment->amount_minor) {
            throw new RuntimeException('Verified refund amount does not match the full payment amount; partial refunds require a separate ledger workflow.');
        }
        $currency = strtoupper((string) (data_get($verified, 'currency') ?? data_get($verified, 'currency_code') ?? ''));
        if ($currency !== '' && $currency !== strtoupper((string) $payment->currency)) {
            throw new RuntimeException('Verified refund currency does not match the payment currency.');
        }

        $status = strtolower(trim((string) data_get($verified, 'status', '')));
        $finalSuccessStatuses = [
            'success', 'successful', 'succeeded', 'refunded',
            'completed-bank-transfer', 'completed-momo', 'completed-mpgs',
            'completed-offline', 'completed-preauth',
        ];
        if (!in_array($status, $finalSuccessStatuses, true)) {
            $metadata = (array) $payment->metadata;
            $metadata['refund_last_verified_status'] = $status;
            $metadata['refund_last_verified_at'] = now()->toISOString();
            $metadata['refund_accounting_status'] = in_array($status, ['failed', 'failure', 'cancelled', 'canceled'], true)
                ? 'provider_failed'
                : 'pending_provider_confirmation';
            $payment->forceFill(['metadata' => $metadata])->saveOrFail();
            throw new RuntimeException('Provider refund is not confirmed finally successful; wallet accounting was not changed.');
        }

        return app(PaymentRefundSettlementService::class)->settleVerified(
            $payment,
            $providerRefundReference,
            $status,
            $reason,
            $actor
        );
    }

    private function majorFromMinor(string $minor): string
    {
        $minor = ltrim($minor, '0') ?: '0';
        if (strlen($minor) === 1) return '0.0'.$minor;
        if (strlen($minor) === 2) return '0.'.$minor;
        return substr($minor, 0, -2).'.'.substr($minor, -2);
    }

    private function majorToMinor(string $major): string
    {
        $major = trim($major);
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $major)) {
            throw new RuntimeException('Provider refund amount is malformed.');
        }
        [$whole, $fraction] = array_pad(explode('.', $major, 2), 2, '');
        return ltrim($whole.str_pad($fraction, 2, '0'), '0') ?: '0';
    }
}
