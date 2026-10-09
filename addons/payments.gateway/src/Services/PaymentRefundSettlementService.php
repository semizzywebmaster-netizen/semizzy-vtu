<?php

namespace Semizzy\Addons\Payments\Services;

use App\Models\User;
use App\Models\WalletMovement;
use App\Services\Finance\WalletReversalService;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Semizzy\Addons\Payments\Models\PaymentIntent;

final class PaymentRefundSettlementService
{
    /**
     * Settle wallet accounting only after the caller has independently verified
     * a successful refund with the assigned provider (for example, from a
     * verified provider callback or an authenticated provider refund-status API).
     * A refund request/accepted response alone is not confirmation.
     */
    public function settleVerified(
        PaymentIntent $payment,
        string $providerRefundReference,
        string $verifiedProviderStatus,
        string $reason,
        ?User $actor = null
    ): PaymentIntent {
        $providerRefundReference = trim($providerRefundReference);
        $verifiedProviderStatus = strtolower(trim($verifiedProviderStatus));
        $reason = trim($reason);

        if ($providerRefundReference === '') {
            throw new RuntimeException('A verified provider refund reference is required.');
        }
        if (!in_array($verifiedProviderStatus, ['success', 'successful', 'succeeded', 'refunded', 'completed-bank-transfer', 'completed-momo', 'completed-mpgs', 'completed-offline', 'completed-preauth'], true)) {
            throw new RuntimeException('Provider refund status is not confirmed successful.');
        }
        if ($reason === '') {
            throw new RuntimeException('A reason is required to settle a wallet refund.');
        }

        try {
            return DB::transaction(function () use ($payment, $providerRefundReference, $verifiedProviderStatus, $reason, $actor): PaymentIntent {
                $locked = PaymentIntent::query()->whereKey($payment->id)->lockForUpdate()->firstOrFail();
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
                if (empty($metadata['refund_requested_at']) || empty($metadata['refund_provider'])) {
                    throw new RuntimeException('A refund request must be recorded before refund settlement.');
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
                $metadata['refund_verified_status'] = $verifiedProviderStatus;
                $metadata['refund_settled_at'] = now()->toISOString();
                $metadata['refund_settled_by'] = $actor?->id;

                $locked->forceFill([
                    'status' => 'refunded',
                    'refunded_at' => now(),
                    'metadata' => $metadata,
                ])->saveOrFail();

                return $locked->fresh();
            });
        } catch (\Throwable $exception) {
            // A provider may have completed the refund while the user's credited
            // funds have already been spent. Roll back the wallet reversal, but
            // persist an explicit reconciliation state outside that transaction.
            $blockedAccounting = in_array($exception->getMessage(), [
                'Insufficient available wallet balance to reverse the original credit.',
                'The original wallet funding movement was not found; refund settlement is blocked.',
                'The wallet must be active before a movement can be reversed.',
            ], true);

            if ($blockedAccounting) {
                $current = PaymentIntent::query()->find($payment->id);
                if ($current !== null && $current->status === 'paid' && $current->refunded_at === null) {
                    $metadata = (array) $current->metadata;
                    $metadata['refund_accounting_status'] = 'manual_review_required';
                    $metadata['refund_provider_reference'] = $providerRefundReference;
                    $metadata['refund_verified_status'] = $verifiedProviderStatus;
                    $metadata['refund_reconciliation_required'] = true;
                    $metadata['refund_settlement_error'] = $exception->getMessage();
                    $metadata['refund_settlement_failed_at'] = now()->toISOString();
                    $metadata['refund_reason'] = $reason;
                    $current->forceFill(['metadata' => $metadata])->saveOrFail();
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
}
