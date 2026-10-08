<?php

namespace Semizzy\Addons\CryptoPayments\Services;

use Illuminate\Support\Facades\DB;
use RuntimeException;
use Semizzy\Addons\CryptoPayments\Models\CryptoPaymentTransaction;

class CryptoPaymentSettlementService
{
    private const TERMINAL = ['finished', 'confirmed', 'failed', 'expired', 'refunded', 'cancelled'];

    public function evaluate(CryptoPaymentTransaction $transaction): CryptoPaymentTransaction
    {
        return DB::transaction(function () use ($transaction) {
            $tx = CryptoPaymentTransaction::query()->lockForUpdate()->findOrFail($transaction->id);

            if (in_array($tx->status, self::TERMINAL, true)) {
                return $tx;
            }

            if ($tx->expires_at && $tx->expires_at->isPast() && !$this->hasSufficientPayment($tx)) {
                $tx->status = 'expired';
                $tx->save();
                return $tx;
            }

            if (!$this->isExpectedAssetNetwork($tx)) {
                $tx->status = 'failed';
                $tx->metadata = array_merge($tx->metadata ?? [], ['settlement_error' => 'asset_or_network_mismatch']);
                $tx->save();
                return $tx;
            }

            if (!$this->hasSufficientPayment($tx)) {
                $tx->status = 'underpaid';
                $tx->save();
                return $tx;
            }

            $required = max(1, (int) $tx->required_confirmations);
            $confirmations = (int) $tx->confirmations;

            if ($confirmations < $required) {
                $tx->status = 'confirming';
                $tx->save();
                return $tx;
            }

            $tx->status = 'finished';
            $tx->confirmed_at ??= now();
            $tx->paid_at ??= now();
            $tx->save();

            return $tx;
        });
    }

    public function canSettle(CryptoPaymentTransaction $transaction): bool
    {
        return $transaction->status === 'finished'
            && (int) $transaction->confirmations >= max(1, (int) $transaction->required_confirmations);
    }

    public function markSettled(CryptoPaymentTransaction $transaction, string $settlementReference): CryptoPaymentTransaction
    {
        if (!$this->canSettle($transaction)) {
            throw new RuntimeException('Crypto payment is not eligible for settlement.');
        }

        return DB::transaction(function () use ($transaction, $settlementReference) {
            $tx = CryptoPaymentTransaction::query()->lockForUpdate()->findOrFail($transaction->id);
            if (($tx->metadata['settlement_reference'] ?? null) === $settlementReference) {
                return $tx;
            }

            if (!$this->canSettle($tx)) {
                throw new RuntimeException('Crypto payment is no longer eligible for settlement.');
            }

            $tx->metadata = array_merge($tx->metadata ?? [], [
                'settled' => true,
                'settlement_reference' => $settlementReference,
                'settled_at' => now()->toIso8601String(),
            ]);
            $tx->save();

            return $tx;
        });
    }

    private function hasSufficientPayment(CryptoPaymentTransaction $tx): bool
    {
        if ($tx->crypto_amount === null || $tx->crypto_received === null) {
            return false;
        }

        return bccomp((string) $tx->crypto_received, (string) $tx->crypto_amount, 18) >= 0;
    }

    private function isExpectedAssetNetwork(CryptoPaymentTransaction $tx): bool
    {
        $metadata = $tx->metadata ?? [];
        $expectedAsset = strtoupper((string) ($metadata['expected_asset'] ?? $tx->asset));
        $actualAsset = strtoupper((string) ($metadata['actual_asset'] ?? $tx->asset));
        $expectedNetwork = strtoupper((string) ($metadata['expected_network'] ?? $tx->network ?? ''));
        $actualNetwork = strtoupper((string) ($metadata['actual_network'] ?? $tx->network ?? ''));

        if ($expectedAsset !== $actualAsset) {
            return false;
        }

        return $expectedNetwork === '' || $expectedNetwork === $actualNetwork;
    }
}
