<?php

namespace App\\Services\\Finance;

use App\\Models\\FinancialOperation;
use App\\Models\\User;
use App\\Models\\WalletAccount;
use App\\Models\\WalletMovement;
use Illuminate\\Support\\Facades\\DB;
use RuntimeException;

final class WalletReversalService
{
    public function reverse(WalletMovement $original, string $reason, ?User $actor = null): WalletMovement
    {
        $reason = trim($reason);
        if ($reason === '') {
            throw new RuntimeException('A reason is required to reverse a wallet movement.');
        }

        return DB::transaction(function () use ($original, $reason, $actor): WalletMovement {
            $original = WalletMovement::query()->whereKey($original->id)->lockForUpdate()->firstOrFail();
            if (($original->metadata['reversal_of_movement_id'] ?? null) !== null || $original->type === 'wallet_reversal') {
                throw new RuntimeException('A reversal movement cannot itself be reversed.');
            }

            $operationKey = 'wallet:reversal:'.$original->id;
            $existing = WalletMovement::query()
                ->where('wallet_account_id', $original->wallet_account_id)
                ->where('operation_key', $operationKey)
                ->first();
            if ($existing !== null) {
                if ((int) ($existing->metadata['reversal_of_movement_id'] ?? 0) !== (int) $original->id) {
                    throw new RuntimeException('Reversal operation key is bound to a different movement.');
                }
                return $existing;
            }

            // This service reverses posted available-balance movements only.
            // Reserve/release operations alter held funds and need a dedicated
            // paired hold-release workflow instead.
            $before = (string) $original->available_before_minor;
            $after = (string) $original->available_after_minor;
            if ((string) $original->held_before_minor !== (string) $original->held_after_minor || $before === $after) {
                throw new RuntimeException('Only movements that change available balance without changing held balance can be reversed here.');
            }

            $wallet = WalletAccount::query()->whereKey($original->wallet_account_id)->lockForUpdate()->firstOrFail();
            if ($wallet->status !== 'active') {
                throw new RuntimeException('The wallet must be active before a movement can be reversed.');
            }

            if ($this->compare($after, $before) > 0) {
                // The original increased available funds, so reversing it debits them.
                $amount = $this->subtract($after, $before);
                if ($this->compare((string) $wallet->available_minor, $amount) < 0) {
                    throw new RuntimeException('Insufficient available wallet balance to reverse the original credit.');
                }
                $newAvailable = $this->subtract((string) $wallet->available_minor, $amount);
            } else {
                // The original decreased available funds, so reversing it credits them.
                $amount = $this->subtract($before, $after);
                $newAvailable = $this->add((string) $wallet->available_minor, $amount);
            }

            $walletBefore = (string) $wallet->available_minor;
            $wallet->available_minor = $newAvailable;
            $wallet->saveOrFail();

            $reversal = WalletMovement::create([
                'wallet_account_id' => $wallet->id,
                'operation_key' => $operationKey,
                'reference' => 'REV-'.$original->reference,
                'type' => 'wallet_reversal',
                'amount_minor' => $amount,
                'currency' => $wallet->currency,
                'available_before_minor' => $walletBefore,
                'available_after_minor' => $newAvailable,
                'held_before_minor' => (string) $wallet->held_minor,
                'held_after_minor' => (string) $wallet->held_minor,
                'metadata' => [
                    'reversal_of_movement_id' => $original->id,
                    'original_reference' => $original->reference,
                    'reason' => $reason,
                    'actor_user_id' => $actor?->id,
                ],
            ]);

            FinancialOperation::query()
                ->where('reference', $original->reference)
                ->where('status', 'completed')
                ->get()
                ->each(function (FinancialOperation $operation): void {
                    $operation->status = 'reversed';
                    $operation->saveOrFail();
                });

            return $reversal;
        });
    }

    private function compare(string $left, string $right): int
    {
        $left = ltrim($left, '0') ?: '0';
        $right = ltrim($right, '0') ?: '0';
        return strlen($left) <=> strlen($right) ?: strcmp($left, $right);
    }

    private function subtract(string $left, string $right): string
    {
        if (function_exists('bcsub')) return bcsub($left, $right, 0);
        if ($this->compare($left, $right) < 0 || strlen(ltrim($left, '0') ?: '0') > 17) {
            throw new RuntimeException('Large wallet amounts require the BCMath PHP extension.');
        }
        return (string) ((int) $left - (int) $right);
    }

    private function add(string $left, string $right): string
    {
        if (function_exists('bcadd')) return bcadd($left, $right, 0);
        if (strlen(ltrim($left, '0') ?: '0') > 17 || strlen(ltrim($right, '0') ?: '0') > 17) {
            throw new RuntimeException('Large wallet amounts require the BCMath PHP extension.');
        }
        return (string) ((int) $left + (int) $right);
    }
}
