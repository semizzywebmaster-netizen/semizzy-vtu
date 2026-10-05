<?php

namespace App\Services\Finance;

use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AdminWalletFundingService
{
    public function fund(User $user, string $amountMajor, User $admin, string $note = ''): WalletAccount
    {
        $minor = $this->toMinor($amountMajor);

        return DB::transaction(function () use ($user, $admin, $minor, $amountMajor, $note): WalletAccount {
            $wallet = WalletAccount::query()
                ->where('user_id', $user->id)
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
                throw new RuntimeException('The user wallet must be active before it can be funded.');
            }

            $tier = max(1, min(3, (int) $user->tier));
            $limit = config("semizzy.user_tiers.{$tier}.balance_limit_minor");
            $before = (string) $wallet->available_minor;
            $after = $this->add($before, $minor);

            if ($limit !== null && $this->compare($after, (string) $limit) > 0) {
                throw new RuntimeException('Funding would exceed the user account balance limit for the current tier.');
            }

            $wallet->available_minor = $after;
            $wallet->saveOrFail();

            $operationId = (string) Str::uuid();
            WalletMovement::create([
                'wallet_account_id' => $wallet->id,
                'operation_key' => 'admin:fund:'.$operationId,
                'reference' => 'ADMIN-FUND-'.$operationId,
                'type' => 'admin_fund',
                'amount_minor' => $minor,
                'currency' => $wallet->currency,
                'available_before_minor' => $before,
                'available_after_minor' => $after,
                'held_before_minor' => (string) $wallet->held_minor,
                'held_after_minor' => (string) $wallet->held_minor,
                'metadata' => [
                    'admin_user_id' => $admin->id,
                    'target_user_id' => $user->id,
                    'amount_major' => $amountMajor,
                    'note' => $note !== '' ? $note : null,
                ],
            ]);

            return $wallet->fresh();
        });
    }

    private function toMinor(string $major): string
    {
        $major = trim($major);
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $major)) {
            throw new RuntimeException('Enter a valid NGN amount with up to two decimal places.');
        }

        [$whole, $fraction] = array_pad(explode('.', $major, 2), 2, '');
        $fraction = str_pad($fraction, 2, '0');
        $minor = ltrim($whole.$fraction, '0') ?: '0';

        if ($minor === '0') {
            throw new RuntimeException('Funding amount must be greater than zero.');
        }

        return $minor;
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
