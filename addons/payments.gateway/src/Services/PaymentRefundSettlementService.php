<?php

namespace Semizzy\Addons\Payments\Services;

use App\Models\User;
use App\Models\WalletMovement;
use App\Services\Finance\WalletReversalService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Semizzy\Addons\Payments\Models\PaymentGatewayProvider;
use Semizzy\Addons\Payments\Models\PaymentIntent;

final class PaymentRefundSettlementService
{
    public function __construct(private PaymentGatewayManager $gateways) {}

    /**
     * Verify the refund directly with the assigned provider, then settle Core
     * wallet accounting. A caller cannot supply a status string as proof.
     */
    public function settleVerified(
        PaymentIntent $payment,
        string $providerRefundReference,
        string $reason,
        ?User $actor = null,
        array $context = []
    ): PaymentIntent {
        $providerRefundReference = trim($providerRefundReference);
        $reason = trim($reason);

        if ($providerRefundReference === '') {
            throw new RuntimeException('A provider refund reference is required.');
        }
        if ($reason === '') {
            throw new RuntimeException('A reason is required to settle a wallet refund.');
        }

        $current = PaymentIntent::query()->findOrFail($payment->id);
        $currentMetadata = (array) $current->metadata;
        if ($current->status === 'refunded' || $current->refunded_at !== null) {
            if (
                (string) ($currentMetadata['refund_provider_reference'] ?? '') === $providerRefundReference
                && ($currentMetadata['refund_accounting_status'] ?? null) === 'settled'
            ) {
                return $current;
            }
            throw new RuntimeException('This payment has already been refunded or settled with a different provider refund reference.');
        }
        if ($current->status !== 'paid') {
            throw new RuntimeException('Only paid payments can be settled as refunded.');
        }
        if (empty($currentMetadata['refund_requested_at']) || empty($currentMetadata['refund_provider'])) {
            throw new RuntimeException('A refund request must be recorded before refund settlement.');
        }
        if (!$current->provider_id) {
            throw new RuntimeException('This payment has no assigned gateway provider for refund verification.');
        }

        $provider = PaymentGatewayProvider::query()->findOrFail($current->provider_id);
        if (!hash_equals((string) $provider->code, (string) $currentMetadata['refund_provider'])) {
            throw new RuntimeException('Refund provider does not match the payment intent provider.');
        }
        if (!$provider->supports('refund')) {
            throw new RuntimeException('The assigned provider does not declare refund capability.');
        }

        // This adapter call is the verification boundary. Providers without an
        // authenticated status lookup must fail closed rather than trust a POST response.
        $verified = $this->gateways->adapter($provider)->verifyRefund($provider, $providerRefundReference, $context);
        $returnedReference = (string) (
            data_get($verified, 'flw_ref')
            ?? data_get($verified, 'refund_reference')
            ?? data_get($verified, 'reference')
            ?? ''
        );
        if ($returnedReference === '' || !hash_equals($providerRefundReference, $returnedReference)) {
            throw new RuntimeException('Provider refund verification did not match the requested refund reference.');
        }

        $providerTransactionId = (string) (data_get($verified, 'transaction_id') ?? data_get($verified, 'tx_id') ?? '');
        $expectedTransactionId = (string) data_get($currentMetadata, 'provider_transaction_id', '');
        if ($expectedTransactionId !== '' && ($providerTransactionId === '' || !hash_equals($expectedTransactionId, $providerTransactionId))) {
            throw new RuntimeException('Verified refund belongs to a different provider transaction.');
        }

        $amount = data_get($verified, 'amount_refunded');
        if ($amount === null || $this->majorToMinor((string) $amount) !== $this->normalizeMinor((string) $current->amount_minor)) {
            throw new RuntimeException('Verified refund amount is missing or does not match the full payment amount; partial refunds require a separate ledger workflow.');
        }
        $currency = strtoupper((string) (data_get($verified, 'currency') ?? data_get($verified, 'currency_code') ?? ''));
        if ($currency !== '' && $currency !== strtoupper((string) $current->currency)) {
            throw new RuntimeException('Verified refund currency does not match the payment currency.');
        }

        $status = strtolower(trim((string) data_get($verified, 'status', '')));
        $finalSuccessStatuses = [
            'success', 'successful', 'succeeded', 'refunded',
            'completed-bank-transfer', 'completed-momo', 'completed-mpgs',
            'completed-offline', 'completed-preauth',
        ];
        if (!in_array($status, $finalSuccessStatuses, true)) {
            $metadata = $currentMetadata;
            $metadata['refund_last_verified_status'] = $status;
            $metadata['refund_last_verified_at'] = now()->toISOString();
            $metadata['refund_accounting_status'] = in_array($status, ['failed', 'failure', 'cancelled', 'canceled'], true)
                ? 'provider_failed'
                : 'pending_provider_confirmation';
            $current->forceFill(['metadata' => $metadata])->saveOrFail();
            throw new RuntimeException('Provider refund is not confirmed finally successful; wallet accounting was not changed.');
        }

        try {
            return DB::transaction(function () use ($current, $providerRefundReference, $status, $reason, $actor): PaymentIntent {
                $locked = PaymentIntent::query()->whereKey($current->id)->lockForUpdate()->firstOrFail();
                $metadata = (array) $locked->metadata;

                if ($locked->status === 'refunded' || $locked->refunded_at !== null) {
                    if (
                        (string) ($metadata['refund_provider_reference'] ?? '') === $providerRefundReference
                        && ($metadata['refund_accounting_status'] ?? null) === 'settled'
                    ) {
                        return $locked;
                    }
                    throw new RuntimeException('This payment has already been refunded or settled with a different provider refund reference.');
                }
                if ($locked->status !== 'paid') {
                    throw new RuntimeException('Only paid payments can be settled as refunded.');
                }

                $movement = WalletMovement::query()
                    ->where('wallet_account_id', $locked->wallet_account_id)
                    ->where('operation_key', 'payment:intent:'.$locked->id)
                    ->where('type', 'payment_funding')
                    ->first();

                if ($movement === null) {
                    throw new RuntimeException('The original wallet funding movement was not found; refund settlement is blocked.');
                }

                app(WalletReversalService::class)->reverse(
                    $movement,
                    'Payment refund '.$providerRefundReference.': '.$reason,
                    $actor
                );

                $metadata['refund_accounting_status'] = 'settled';
                $metadata['refund_provider_reference'] = $providerRefundReference;
                $metadata['refund_verified_status'] = $status;
                $metadata['refund_settled_at'] = now()->toISOString();
                $metadata['refund_settled_by'] = $actor?->id;
                $metadata['refund_reconciliation_required'] = false;

                $locked->forceFill([
                    'status' => 'refunded',
                    'refunded_at' => now(),
                    'metadata' => $metadata,
                ])->saveOrFail();

                return $locked->fresh();
            });
        } catch (\Throwable $exception) {
            $blockedAccounting = in_array($exception->getMessage(), [
                'Insufficient available wallet balance to reverse the original credit.',
                'The original wallet funding movement was not found; refund settlement is blocked.',
                'The wallet must be active before a movement can be reversed.',
            ], true);

            if ($blockedAccounting) {
                $fresh = PaymentIntent::query()->find($current->id);
                if ($fresh !== null && $fresh->status === 'paid' && $fresh->refunded_at === null) {
                    $metadata = (array) $fresh->metadata;
                    $metadata['refund_accounting_status'] = 'manual_review_required';
                    $metadata['refund_provider_reference'] = $providerRefundReference;
                    $metadata['refund_verified_status'] = $status;
                    $metadata['refund_reconciliation_required'] = true;
                    $metadata['refund_settlement_error'] = $exception->getMessage();
                    $metadata['refund_settlement_failed_at'] = now()->toISOString();
                    $metadata['refund_reason'] = $reason;
                    $fresh->forceFill(['metadata' => $metadata])->saveOrFail();
                }

                throw new RuntimeException(
                    'Provider refund is confirmed, but wallet reversal could not be completed safely; manual reconciliation is required.',
                    0,
                    $exception
                );
            }

            throw $exception;
        }
    }

    private function normalizeMinor(string $minor): string
    {
        return ltrim($minor, '0') ?: '0';
    }

    private function majorToMinor(string $major): string
    {
        $major = trim($major);
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $major)) {
            throw new RuntimeException('Provider refund amount is malformed.');
        }
        [$whole, $fraction] = array_pad(explode('.', $major, 2), 2, '');
        return $this->normalizeMinor($whole.str_pad($fraction, 2, '0'));
    }
}
