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

            if (!$tx->user_id) {
                throw new RuntimeException('Crypto payment cannot settle without an owning user.');
            }

            $funding = (array) (($tx->metadata ?? [])['funding'] ?? []);
            $walletAmount = (string) ($funding['wallet_amount'] ?? '0');
            $feeAmount = (string) ($funding['fee_amount'] ?? '0');
            $customerAmount = (string) ($funding['customer_funding_amount'] ?? '0');

            if ($walletAmount === '0' || $customerAmount === '0') {
                throw new RuntimeException('Crypto funding settlement is missing its funding snapshot.');
            }

            if (strtoupper((string) $tx->fiat_currency) !== 'NGN') {
                throw new RuntimeException('Crypto wallet funding settlement currently requires NGN.');
            }

            $user = User::query()->findOrFail($tx->user_id);
            app(WalletCreditService::class)->credit(
                $user,
                $walletAmount,
                'crypto:funding:'.$tx->uuid,
                $settlementReference,
                'crypto_funding',
                [
                    'crypto_payment_transaction_id' => $tx->id,
                    'crypto_payment_reference' => $tx->reference,
                    'wallet_amount' => $walletAmount,
                    'funding_fee_amount' => $feeAmount,
                    'customer_funding_amount' => $customerAmount,
                    'asset' => $tx->asset,
                    'network' => $tx->network,
                    'tx_hash' => $tx->tx_hash,
                ]
            );

            $accountIds = DB::table('ledger_accounts')
                ->whereIn('code', [
                    'crypto_funding_clearing_ngn',
                    'user_wallet_liability_ngn',
                    'crypto_funding_fee_revenue_ngn',
                ])
                ->where('currency', 'NGN')
                ->where('status', 'active')
                ->pluck('id', 'code');

            foreach (['crypto_funding_clearing_ngn', 'user_wallet_liability_ngn', 'crypto_funding_fee_revenue_ngn'] as $code) {
                if (!$accountIds->has($code)) {
                    throw new RuntimeException('Required crypto funding ledger account is missing: '.$code);
                }
            }

            $toMinor = static function (string $major): string {
                $major = trim($major);
                if (!preg_match('/^\\d+(?:\\.\\d{1,2})?$/', $major)) {
                    throw new RuntimeException('Crypto funding ledger amount is invalid.');
                }
                [$whole, $fraction] = array_pad(explode('.', $major, 2), 2, '');
                return ltrim($whole.str_pad($fraction, 2, '0'), '0') ?: '0';
            };

            $customerMinor = $toMinor($customerAmount);
            $walletMinor = $toMinor($walletAmount);
            $feeMinor = $toMinor($feeAmount);

            $entries = [
                ['ledger_account_id' => $accountIds['crypto_funding_clearing_ngn'], 'debit_minor' => $customerMinor, 'credit_minor' => '0'],
                ['ledger_account_id' => $accountIds['user_wallet_liability_ngn'], 'debit_minor' => '0', 'credit_minor' => $walletMinor],
            ];

            if ($feeMinor !== '0') {
                $entries[] = [
                    'ledger_account_id' => $accountIds['crypto_funding_fee_revenue_ngn'],
                    'debit_minor' => '0',
                    'credit_minor' => $feeMinor,
                ];
            }

            app(LedgerService::class)->post(
                $settlementReference,
                'crypto_wallet_funding',
                'NGN',
                $entries,
                'Crypto wallet funding '.$tx->reference,
                [
                    'crypto_payment_transaction_id' => $tx->id,
                    'crypto_payment_reference' => $tx->reference,
                    'user_id' => $tx->user_id,
                    'wallet_amount' => $walletAmount,
                    'funding_fee_amount' => $feeAmount,
                    'customer_funding_amount' => $customerAmount,
                    'asset' => $tx->asset,
                    'network' => $tx->network,
                    'tx_hash' => $tx->tx_hash,
                ]
            );

            $tx->metadata = array_merge($tx->metadata ?? [], [
                'settled' => true,
                'settlement_reference' => $settlementReference,
                'settled_at' => now()->toIso8601String(),
                'wallet_credit' => [
                    'amount' => $walletAmount,
                    'currency' => 'NGN',
                ],
                'platform_revenue' => [
                    'type' => 'crypto_funding_fee',
                    'amount' => $feeAmount,
                    'currency' => 'NGN',
                ],
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
