<?php

namespace App\Services\Finance;

use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletMovement;
use Illuminate\Support\Facades\DB;
use RuntimeException;

class WalletCreditService
{
    public function credit(User $user, string $amountMajor, string $operationKey, string $reference, string $type, array $metadata = []): WalletAccount
    {
        $minor = $this->toMinor($amountMajor);

        return DB::transaction(function () use ($user, $minor, $operationKey, $reference, $type, $metadata): WalletAccount {
            $wallet = WalletAccount::query()
                ->where('user_id', $user->id)
                ->where('currency', 'NGN')
                ->lockForUpdate()
                ->first();

            if ($wallet === null) {
                $wallet = WalletAccount::create([
                    'user_id' => $user->id,
                    'currency' => 'NGN',
                    'available_minor' => '0',
                    'held_minor' => '0',
                    'status' => 'active',
                ]);
                $wallet = WalletAccount::query()->whereKey($wallet->id)->lockForUpdate()->firstOrFail();
            }

            if ($wallet->status !== 'active') {
                throw new RuntimeException('The user wallet must be active before it can be credited.');
            }

            $existing = WalletMovement::query()
                ->where('wallet_account_id', $wallet->id)
                ->where('operation_key', $operationKey)
                ->first();

            if ($existing) {
                return $wallet->fresh();
            }

            $before = (string) $wallet->available_minor;
            $after = $this->add($before, $minor);

            $wallet->available_minor = $after;
            $wallet->saveOrFail();

            WalletMovement::create([
                'wallet_account_id' => $wallet->id,
                'operation_key' => $operationKey,
                'reference' => $reference,
                'type' => $type,
                'amount_minor' => $minor,
                'currency' => 'NGN',
                'available_before_minor' => $before,
                'available_after_minor' => $after,
                'held_before_minor' => (string) $wallet->held_minor,
                'held_after_minor' => (string) $wallet->held_minor,
                'metadata' => $metadata,
            ]);

            return $wallet->fresh();
        });
    }

    private function toMinor(string $major): string
    {
        $major = trim($major);
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $major)) {
            throw new RuntimeException('Wallet credit amount must be a valid NGN amount.');
        }

        [$whole, $fraction] = array_pad(explode('.', $major, 2), 2, '');
        $minor = ltrim($whole.str_pad($fraction, 2, '0'), '0') ?: '0';

        if ($minor === '0') {
            throw new RuntimeException('Wallet credit amount must be greater than zero.');
        }

        return $minor;
    }

    private function add(string $a, string $b): string
    {
        if (function_exists('bcadd')) {
            return bcadd($a, $b, 0);
        }

        $a = ltrim($a, '0') ?: '0';
        $b = ltrim($b, '0') ?: '0';

        if (strlen($a) > 17 || strlen($b) > 17 || PHP_INT_SIZE < 8) {
            throw new RuntimeException('Large wallet amounts require the BCMath PHP extension.');
        }

        return (string) ((int) $a + (int) $b);
    }
}
