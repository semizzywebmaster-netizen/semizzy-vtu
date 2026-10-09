<?php

namespace App\Services\Finance;

use App\Models\User;
use App\Models\WalletAccount;
use App\Models\WalletMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class AdminWalletDebitService
{
    public function debit(User $user, string $amountMajor, User $admin, string $note = '', ?string $idempotencyKey = null): WalletAccount
    {
        $minor = $this->toMinor($amountMajor);
        $idempotencyKey = $idempotencyKey !== null ? trim($idempotencyKey) : null;
        if ($idempotencyKey === '') {
            throw new RuntimeException('A valid idempotency key is required when one is supplied.');
        }

        return DB::transaction(function () use ($user, $admin, $minor, $amountMajor, $note, $idempotencyKey): WalletAccount {
            $wallet = WalletAccount::query()->where('user_id', $user->id)->where('currency', 'NGN')->lockForUpdate()->first();
            if (!$wallet) throw new RuntimeException('This user does not have a wallet yet.');
            if ($wallet->status !== 'active') throw new RuntimeException('The user wallet must be active before it can be debited.');

            if ($idempotencyKey !== null) {
                $existing = WalletMovement::query()
                    ->where('wallet_account_id', $wallet->id)
                    ->where('operation_key', 'admin:debit:'.$idempotencyKey)
                    ->first();
                if ($existing !== null) {
                    $metadata = (array) $existing->metadata;
                    if (
                        $existing->type !== 'admin_debit'
                        || (string) $existing->amount_minor !== $minor
                        || (int) ($metadata['admin_user_id'] ?? 0) !== (int) $admin->id
                        || (int) ($metadata['target_user_id'] ?? 0) !== (int) $user->id
                    ) {
                        throw new RuntimeException('This idempotency key has already been used for a different wallet debit.');
                    }
                    return $wallet->fresh();
                }
            }

            $before = (string) $wallet->available_minor;
            if ($this->compare($before, $minor) < 0) throw new RuntimeException('Insufficient available wallet balance for this debit.');
            $after = $this->subtract($before, $minor);
            $wallet->available_minor = $after;
            $wallet->saveOrFail();

            $operationId = $idempotencyKey ?? (string) Str::uuid();
            WalletMovement::create([
                'wallet_account_id' => $wallet->id,
                'operation_key' => 'admin:debit:'.$operationId,
                'reference' => 'ADMIN-DEBIT-'.strtoupper(Str::random(16)),
                'type' => 'admin_debit',
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
                    'idempotency_key' => $idempotencyKey,
                ],
            ]);

            return $wallet->fresh();
        });
    }

    private function toMinor(string $major): string
    {
        $major = trim($major);
        if (!preg_match('/^\d+(?:\.\d{1,2})?$/', $major)) throw new RuntimeException('Enter a valid NGN amount with up to two decimal places.');
        [$whole, $fraction] = array_pad(explode('.', $major, 2), 2, '');
        $minor = ltrim($whole.str_pad($fraction, 2, '0'), '0') ?: '0';
        if ($minor === '0') throw new RuntimeException('Debit amount must be greater than zero.');
        return $minor;
    }

    private function compare(string $a, string $b): int
    {
        $a = ltrim($a, '0') ?: '0';
        $b = ltrim($b, '0') ?: '0';
        return strlen($a) <=> strlen($b) ?: strcmp($a, $b);
    }

    private function subtract(string $a, string $b): string
    {
        if (function_exists('bcsub')) return bcsub($a, $b, 0);
        if (strlen(ltrim($a, '0') ?: '0') > 17) throw new RuntimeException('Large wallet amounts require the BCMath PHP extension.');
        return (string) ((int) $a - (int) $b);
    }
}
