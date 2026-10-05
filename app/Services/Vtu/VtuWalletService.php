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
                if (! $this->fitsNativeInteger($available) || ! $this->fitsNativeInteger($total) || ! $this->fitsNativeInteger($held)) {
                    throw new RuntimeException('Large wallet amounts require the BCMath PHP extension.');
                }

                if ($this->compareIntegerStrings($available, $total) < 0) {
                    throw new RuntimeException('Insufficient wallet balance.');
                }

                $wallet->available_minor = (string) ((int) $available - (int) $total);
                $wallet->held_minor = (string) ((int) $held + (int) $total);
            }

            $wallet->save();
        });
    }

    private function fitsNativeInteger(string $value): bool
    {
        $value = ltrim($value, '0');
        return ctype_digit($value === '' ? '0' : $value)
            && PHP_INT_SIZE >= 8 && strlen($value) <= 17;
    }

    private function compareIntegerStrings(string $left, string $right): int
    {
        $left = ltrim($left, '0') ?: '0';
        $right = ltrim($right, '0') ?: '0';
        return strlen($left) <=> strlen($right) ?: strcmp($left, $right);
    }

    public function creditRefund(VtuTransaction $tx): void
    {
        DB::transaction(function () use ($tx): void {
            $wallet = WalletAccount::query()
                ->where('user_id', $tx->user_id)
                ->where('currency', $tx->currency)
                ->lockForUpdate()
                ->firstOrFail();

            $amount = (string) $tx->total_minor;
            $available = (string) $wallet->available_minor;

            if (function_exists('bcadd')) {
                $wallet->available_minor = bcadd($available, $amount, 0);
            } else {
                if (! $this->fitsNativeInteger($available) || ! $this->fitsNativeInteger($amount)) {
                    throw new RuntimeException('Large wallet amounts require the BCMath PHP extension.');
                }
                $wallet->available_minor = (string) ((int) $available + (int) $amount);
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
                if (! $this->fitsNativeInteger($held) || ! $this->fitsNativeInteger($total) || ! $this->fitsNativeInteger($available)) {
                    throw new RuntimeException('Large wallet amounts require the BCMath PHP extension.');
                }
                if ($this->compareIntegerStrings($held, $total) < 0) {
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
