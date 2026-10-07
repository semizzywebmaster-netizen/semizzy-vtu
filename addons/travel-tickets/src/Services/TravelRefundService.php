<?php

namespace Semizzy\Addons\TravelTickets\Services;

use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletMovement;
use Illuminate\Support\Facades\DB;
use RuntimeException;
use Semizzy\Addons\TravelTickets\Models\TravelBooking;
use Semizzy\Addons\TravelTickets\Models\TravelRefund;

class TravelRefundService
{
    public function request(TravelBooking $booking, ?string $note = null): TravelRefund
    {
        if ($booking->status !== 'cancelled') {
            throw new RuntimeException('Only cancelled travel bookings can be refunded.');
        }

        $existing = TravelRefund::where('travel_booking_id', $booking->id)
            ->whereIn('status', ['pending', 'approved'])
            ->latest('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        return TravelRefund::create([
            'travel_booking_id' => $booking->id,
            'user_id' => $booking->user_id,
            'amount' => $booking->total,
            'status' => 'pending',
            'note' => $note,
        ]);
    }

    public function approve(TravelRefund $refund, User $admin, ?string $note = null): TravelRefund
    {
        return DB::transaction(function () use ($refund, $admin, $note): TravelRefund {
            $current = TravelRefund::query()->lockForUpdate()->findOrFail($refund->id);

            if ($current->status === 'approved') {
                return $current;
            }

            if ($current->status !== 'pending') {
                throw new RuntimeException('Only pending travel refunds can be approved.');
            }

            $booking = TravelBooking::query()->lockForUpdate()->findOrFail($current->travel_booking_id);

            if ($booking->status !== 'cancelled') {
                throw new RuntimeException('The travel booking is not cancelled.');
            }

            $wallet = WalletAccount::query()
                ->where('user_id', $current->user_id)
                ->where('currency', strtoupper((string) $booking->currency))
                ->lockForUpdate()
                ->first();

            if (!$wallet || $wallet->status !== 'active') {
                throw new RuntimeException('User wallet is not available.');
            }

            $key = "travel:refund:{$current->id}";
            if (!WalletMovement::where('wallet_account_id', $wallet->id)->where('operation_key', $key)->exists()) {
                $amount = $this->minor($current->amount);
                $before = (string) $wallet->available_minor;
                $wallet->available_minor = $this->add($before, $amount);
                $wallet->save();

                WalletMovement::create([
                    'wallet_account_id' => $wallet->id,
                    'operation_key' => $key,
                    'reference' => 'TRAVEL-REFUND-' . $current->id,
                    'type' => 'refund',
                    'amount_minor' => $amount,
                    'currency' => strtoupper((string) $booking->currency),
                    'available_before_minor' => $before,
                    'available_after_minor' => (string) $wallet->available_minor,
                    'held_before_minor' => (string) $wallet->held_minor,
                    'held_after_minor' => (string) $wallet->held_minor,
                    'metadata' => ['travel_refund_id' => $current->id, 'travel_booking_id' => $booking->id],
                ]);
            }

            $current->update([
                'status' => 'approved',
                'approved_by' => $admin->id,
                'note' => $note !== null ? $note : $current->note,
            ]);

            return $current->fresh();
        });
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

        if (PHP_INT_SIZE < 8 || strlen(ltrim($a, '0') ?: '0') > 17 || strlen(ltrim($b, '0') ?: '0') > 17) {
            throw new RuntimeException('Large wallet amounts require the BCMath PHP extension.');
        }

        return (string) ((int) $a + (int) $b);
    }
}