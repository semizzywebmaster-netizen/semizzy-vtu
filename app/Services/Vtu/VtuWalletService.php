<?php

namespace App\Services\Vtu;

use App\Models\WalletAccount;
use App\Models\VtuTransaction;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class VtuWalletService
{
    public function reserve(VtuTransaction $tx): void
    {
        DB::transaction(function () use ($tx): void {
            $wallet = WalletAccount::query()
                ->where('user_id', $tx->user_id)
                ->where('currency', $tx->currency)
                ->lockForUpdate()
                ->first();

            if (! $wallet) {
                throw new RuntimeException('User wallet is not available.');
            }

            $available = (string) $wallet->available_minor;
            $total = (string) $tx->total_minor;
            $held = (string) $wallet->held_minor;

            if (function_exists('bccomp')) {
                if (bccomp($available, $total, 0) < 0) {
                    throw new RuntimeException('Insufficient wallet balance.');
                }
                $wallet->available_minor = bcsub($available, $total, 0);
                $wallet->held_minor = bcadd($held, $total, 0);
            } else {
                // Do not silently overflow PHP integers on shared hosts without BCMath.
                if (! ctype_digit($available) || ! ctype_digit($total) || ! ctype_digit($held)
                    || strlen(ltrim($available, '0')) > 18
                    || strlen(ltrim($total, '0')) > 18
                    || strlen(ltrim($held, '0')) > 18) {
                    throw new RuntimeException('Large wallet amounts require the BCMath PHP extension.');
                }

                if ((float) $available < (float) $total) {
                    throw new RuntimeException('Insufficient wallet balance.');
                }

                $wallet->available_minor = (string) ((int) $available - (int) $total);
                $wallet->held_minor = (string) ((int) $held + (int) $total);
            }

            $wallet->save();
        });
    }

    public function settle(VtuTransaction $tx, bool $success): void
    {
        DB::transaction(function () use ($tx, $success): void {
            $wallet = WalletAccount::query()
                ->where('user_id', $tx->user_id)
                ->where('currency', $tx->currency)
                ->lockForUpdate()
                ->firstOrFail();

            $total = (string) $tx->total_minor;
            $held = (string) $wallet->held_minor;
            $available = (string) $wallet->available_minor;

            if (function_exists('bccomp')) {
                if (bccomp($held, $total, 0) < 0) {
                    throw new RuntimeException('Wallet hold is inconsistent.');
                }
                $wallet->held_minor = bcsub($held, $total, 0);
                if (! $success) {
                    $wallet->available_minor = bcadd($available, $total, 0);
                }
            } else {
                if (! ctype_digit($held) || ! ctype_digit($total) || ! ctype_digit($available)
                    || strlen(ltrim($held, '0')) > 18
                    || strlen(ltrim($total, '0')) > 18
                    || strlen(ltrim($available, '0')) > 18) {
                    throw new RuntimeException('Large wallet amounts require the BCMath PHP extension.');
                }
                if ((float) $held < (float) $total) {
                    throw new RuntimeException('Wallet hold is inconsistent.');
                }
                $wallet->held_minor = (string) ((int) $held - (int) $total);
                if (! $success) {
                    $wallet->available_minor = (string) ((int) $available + (int) $total);
                }
            }

            $wallet->save();
        });
    }
}
