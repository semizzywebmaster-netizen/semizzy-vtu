<?php

namespace App\Services\Platform;

use App\Models\SystemSetting;
use App\Models\User;

class FeatureRolloutService
{
    public function all(): array
    {
        $raw = SystemSetting::query()->where('key', 'feature_rollouts')->value('value');
        if (! is_string($raw) || $raw === '') {
            return [];
        }

        $decoded = json_decode($raw, true);
        return is_array($decoded) ? $decoded : [];
    }

    public function allows(string $key, ?User $user): bool
    {
        // Administrators retain access to verify a rollout and recover from a bad setting.
        if ($user?->role === 'ADMIN') {
            return true;
        }

        $settings = $this->all();
        $flag = $settings[$key] ?? null;
        if (! is_array($flag)) {
            return true;
        }

        if (! (bool) ($flag['enabled'] ?? true)) {
            return false;
        }

        $percentage = max(0, min(100, (int) ($flag['percentage'] ?? 100)));
        if ($percentage === 0) {
            return false;
        }
        if ($percentage === 100) {
            return true;
        }
        if (! $user) {
            return false;
        }

        $bucket = (int) (sprintf('%u', crc32('semizzy-rollout:' . $key . ':' . $user->getAuthIdentifier())) % 100);
        return $bucket < $percentage;
    }
}
