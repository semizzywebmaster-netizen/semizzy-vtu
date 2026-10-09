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

        // Payment-bound values override caller-supplied fields. Transaction IDs
        // are taken from the verified collection response, never from the request.
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

    public function verifyAndSettleRefund(
        PaymentIntent $payment,
        string $providerRefundReference,
        string $reason,
        ?User $actor = null,
        array $context = []
    ): PaymentIntent {
        // Settlement service performs the authenticated provider lookup itself;
        // a caller cannot pass an arbitrary "success" status to release accounting.
        return app(PaymentRefundSettlementService::class)->settleVerified(
            $payment,
            $providerRefundReference,
            $reason,
            $actor,
            $context
        );
    }

    private function majorFromMinor(string $minor): string
    {
        $minor = ltrim($minor, '0') ?: '0';
        if (strlen($minor) === 1) return '0.0'.$minor;
        if (strlen($minor) === 2) return '0.'.$minor;
        return substr($minor, 0, -2).'.'.substr($minor, -2);
    }
}
