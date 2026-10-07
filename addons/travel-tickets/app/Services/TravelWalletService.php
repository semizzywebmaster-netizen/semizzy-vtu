<?php

namespace Semizzy\Addons\TravelTickets\Services;

use App\Models\WalletAccount;
use App\Models\WalletMovement;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Semizzy\Addons\TravelTickets\Models\TravelBooking;

class TravelWalletService
{
    public function reserve(TravelBooking $booking): void
    {
        DB::transaction(function () use ($booking): void {
            $wallet = $this->wallet($booking);
            $key = "travel:{$booking->id}:reserve";

            if (WalletMovement::query()
                ->where('wallet_account_id', $wallet->id)
                ->where('operation_key', $key)
                ->exists()) {
                return;
            }

            $amount = $this->minor($booking->total);
            $available = (string) $wallet->available_minor;
            if ($this->compare($available, $amount) < 0) {
                throw new RuntimeException('Insufficient wallet balance.');
            }

            $beforeHeld = (string) $wallet->held_minor;
            $wallet->available_minor = $this->sub($available, $amount);
            $wallet->held_minor = $this->add($beforeHeld, $amount);
            $wallet->save();

            $this->movement(
                $wallet,
                $key,
                $booking,
                'reserve',
                $amount,
                $available,
                (string) $wallet->available_minor,
                $beforeHeld,
                (string) $wallet->held_minor
            );
        });
    }

    public function release(TravelBooking $booking): void
    {
        DB::transaction(function () use ($booking): void {
            $wallet = $this->wallet($booking);
            $key = "travel:{$booking->id}:release";

            if (WalletMovement::query()
                ->where('wallet_account_id', $wallet->id)
                ->where('operation_key', $key)
                ->exists()) {
                return;
            }

            $amount = $this->minor($booking->total);
            $available = (string) $wallet->available_minor;
            $held = (string) $wallet->held_minor;

            if ($this->compare($held, $amount) < 0) {
                throw new RuntimeException('Travel wallet hold is inconsistent.');
            }

            $wallet->held_minor = $this->sub($held, $amount);
            $wallet->available_minor = $this->add($available, $amount);
            $wallet->save();

            $this->movement(
                $wallet,
                $key,
                $booking,
                'release',
                $amount,
                $available,
                (string) $wallet->available_minor,
                $held,
                (string) $wallet->held_minor
            );
        });
    }

    public function settle(TravelBooking $booking): void
    {
        DB::transaction(function () use ($booking): void {
            $wallet = $this->wallet($booking);
            $key = "travel:{$booking->id}:settle";

            if (WalletMovement::query()
                ->where('wallet_account_id', $wallet->id)
                ->where('operation_key', $key)
                ->exists()) {
                return;
            }

            $amount = $this->minor($booking->total);
            $available = (string) $wallet->available_minor;
            $held = (string) $wallet->held_minor;

            if ($this->compare($held, $amount) < 0) {
                throw new RuntimeException('Travel wallet hold is inconsistent.');
            }

            $wallet->held_minor = $this->sub($held, $amount);
            $wallet->save();

            $this->movement(
                $wallet,
                $key,
                $booking,
                'settle',
                $amount,
                $available,
                (string) $wallet->available_minor,
                $held,
                (string) $wallet->held_minor
            );
        });
    }

    private function wallet(TravelBooking $booking): WalletAccount
    {
        $wallet = WalletAccount::query()
            ->where('user_id', $booking->user_id)
            ->where('currency', strtoupper((string) $booking->currency))
            ->lockForUpdate()
            ->first();

        if (!$wallet || $wallet->status !== 'active') {
            throw new RuntimeException('User wallet is not available.');
        }

        return $wallet;
    }

    private function movement(
        WalletAccount $wallet,
        string $key,
        TravelBooking $booking,
        string $type,
        string $amount,
        string $availableBefore,
        string $availableAfter,
        string $heldBefore,
        string $heldAfter
    ): void {
        WalletMovement::create([
            'wallet_account_id' => $wallet->id,
            'operation_key' => $key,
            'reference' => 'TRAVEL-' . $booking->id,
            'type' => $type,
            'amount_minor' => $amount,
            'currency' => strtoupper((string) $booking->currency),
            'available_before_minor' => $availableBefore,
            'available_after_minor' => $availableAfter,
            'held_before_minor' => $heldBefore,
            'held_after_minor' => $heldAfter,
            'metadata' => ['travel_booking_id' => $booking->id],
        ]);
    }

    private function minor(string|float|int $amount): string
    {
        $value = number_format((float) $amount, 2, '.', '');
        [$whole, $fraction] = array_pad(explode('.', $value, 2), 2, '0');
        return ltrim(($whole ?: '0') . str_pad($fraction, 2, '0'), '0') ?: '0';
    }

    private function add(string $a, string $b): string
    {
        if (function_exists('bcadd')) {
            return bcadd($a, $b, 0);
        }

        if (!$this->fitsNativeInteger($a) || !$this->fitsNativeInteger($b)) {
            throw new RuntimeException('Large wallet amounts require the BCMath PHP extension.');
        }

        return (string) ((int) $a + (int) $b);
    }

    private function sub(string $a, string $b): string
    {
        if (function_exists('bcsub')) {
            return bcsub($a, $b, 0);
        }

        if (!$this->fitsNativeInteger($a) || !$this->fitsNativeInteger($b)) {
            throw new RuntimeException('Large wallet amounts require the BCMath PHP extension.');
        }

        return (string) ((int) $a - (int) $b);
    }

    private function fitsNativeInteger(string $value): bool
    {
        $value = ltrim($value, '0');
        return ctype_digit($value === '' ? '0' : $value) && PHP_INT_SIZE >= 8 && strlen($value) <= 17;
    }

    private function compare(string $left, string $right): int
    {
        $left = ltrim($left, '0') ?: '0';
        $right = ltrim($right, '0') ?: '0';

        return strlen($left) <=> strlen($right) ?: strcmp($left, $right);
    }
}
