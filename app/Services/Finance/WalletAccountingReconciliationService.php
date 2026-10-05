<?php

namespace App\Services\Finance;

use App\Models\WalletAccount;
use App\Models\WalletMovement;
use RuntimeException;

class WalletAccountingReconciliationService
{
    public function reconcile(WalletAccount $wallet): array
    {
        $movements = WalletMovement::query()
            ->where('wallet_account_id', $wallet->id)
            ->orderBy('id')
            ->get();

        $previousAvailable = null;
        $previousHeld = null;
        $issues = [];

        foreach ($movements as $movement) {
            $availableBefore = (string) $movement->available_before_minor;
            $availableAfter = (string) $movement->available_after_minor;
            $heldBefore = (string) $movement->held_before_minor;
            $heldAfter = (string) $movement->held_after_minor;

            if ($previousAvailable !== null && ($availableBefore !== $previousAvailable || $heldBefore !== $previousHeld)) {
                $issues[] = "Movement {$movement->id} does not continue the previous wallet balance.";
            }

            if (strtoupper((string) $movement->currency) !== strtoupper((string) $wallet->currency)) {
                $issues[] = "Movement {$movement->id} has a currency mismatch.";
            }

            if ($availableAfter === '' || $heldAfter === '') {
                $issues[] = "Movement {$movement->id} has an invalid resulting balance.";
            }

            $previousAvailable = $availableAfter;
            $previousHeld = $heldAfter;
        }

        if ($previousAvailable !== null && (
            $previousAvailable !== (string) $wallet->available_minor
            || $previousHeld !== (string) $wallet->held_minor
        )) {
            $issues[] = 'Current wallet balance does not match the final immutable movement snapshot.';
        }

        if ($issues !== []) {
            throw new RuntimeException('Wallet accounting reconciliation failed: '.implode(' ', $issues));
        }

        return [
            'wallet_account_id' => $wallet->id,
            'movement_count' => $movements->count(),
            'available_minor' => (string) $wallet->available_minor,
            'held_minor' => (string) $wallet->held_minor,
            'balanced' => true,
        ];
    }
}
