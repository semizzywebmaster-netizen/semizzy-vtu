<?php

namespace App\Services\Security;

use App\Models\CredentialHistory;
use App\Models\User;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;

class CredentialHistoryService
{
    public function assertPasswordIsFresh(User $user, string $password): void
    {
        if (Hash::check($password, (string) $user->password)) {
            throw ValidationException::withMessages([
                'password' => 'You cannot reuse your current password. Choose a new password.',
            ]);
        }

        $this->assertNotInHistory($user, 'password', $password, 'password');
    }

    public function recordPassword(User $user, string $oldHash): void
    {
        if (! $oldHash) {
            return;
        }

        CredentialHistory::create([
            'user_id' => $user->id,
            'credential_type' => 'password',
            'credential_hash' => $oldHash,
        ]);

        $this->prune($user, 'password', 5);
    }

    public function assertPinIsFresh(User $user, string $pin): void
    {
        if (filled($user->transaction_pin_hash) && Hash::check($pin, (string) $user->transaction_pin_hash)) {
            throw ValidationException::withMessages([
                'pin' => 'You cannot reuse your current transaction PIN. Choose a different PIN.',
            ]);
        }

        $this->assertNotInHistory($user, 'transaction_pin', $pin, 'transaction PIN');
    }

    private function assertNotInHistory(User $user, string $type, string $value, string $label): void
    {
        $recent = CredentialHistory::query()
            ->where('user_id', $user->id)
            ->where('credential_type', $type)
            ->latest('id')
            ->limit(5)
            ->get();

        foreach ($recent as $history) {
            if (Hash::check($value, (string) $history->credential_hash)) {
                throw ValidationException::withMessages([
                    'password' => $label === 'password'
                        ? 'You cannot reuse a previous password. Choose a different password.'
                        : 'You cannot reuse a previous transaction PIN. Choose a different PIN.',
                ]);
            }
        }
    }

    private function prune(User $user, string $type, int $keep): void
    {
        $ids = CredentialHistory::query()
            ->where('user_id', $user->id)
            ->where('credential_type', $type)
            ->latest('id')
            ->skip($keep)
            ->pluck('id');

        if ($ids->isNotEmpty()) {
            CredentialHistory::query()->whereIn('id', $ids)->delete();
        }
    }
}