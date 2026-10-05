<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, Notifiable;

    protected $fillable = ['name', 'username', 'email', 'phone', 'password', 'role', 'status', 'tier', 'account_type', 'business_name', 'business_registration_number', 'business_type', 'business_address', 'business_state', 'business_country', 'merchant_verified_at', 'tier_upgrade_status', 'two_factor_enabled', 'two_factor_secret', 'transaction_pin_hash', 'security_lock_until', 'onboarding_completed_at', 'last_login_at', 'last_login_ip', 'referral_code', 'referred_by_id'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'merchant_verified_at' => 'datetime',
            'security_lock_until' => 'datetime',
            'onboarding_completed_at' => 'datetime',
            'last_login_at' => 'datetime',
            'two_factor_enabled' => 'boolean',
            'two_factor_secret' => 'encrypted',
            'transaction_pin_hash' => 'hashed',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool { return $this->role === 'ADMIN'; }
    public function hasRole(string|array $roles): bool { return in_array($this->role, (array) $roles, true); }

    public function walletAccounts(): HasMany { return $this->hasMany(WalletAccount::class); }

    public function devices(): HasMany { return $this->hasMany(UserDevice::class); }
    public function otpChallenges(): HasMany { return $this->hasMany(OtpChallenge::class); }
    public function loginActivities(): HasMany { return $this->hasMany(LoginActivity::class); }
    public function usernameHistory(): HasMany { return $this->hasMany(UsernameHistory::class); }

    public function isMerchant(): bool { return (int) $this->tier === 4 && $this->account_type === 'merchant'; }
    public function currentTier(): array { $tier = max(1, min(4, (int) $this->tier)); return config('semizzy.user_tiers.'.$tier, config('semizzy.user_tiers.1')); }
    public function securityEvents(): HasMany { return $this->hasMany(SecurityEvent::class); }
    public function referrals(): HasMany { return $this->hasMany(self::class, 'referred_by_id'); }

    public function permissionOverrides(): HasMany
    {
        return $this->hasMany(UserPermissionOverride::class);
    }

    public function hasPermission(string $permission): bool
    {
        $override = $this->permissionOverrides()->where('permission', $permission)->value('allowed');

        return $override !== null
            ? (bool) $override
            : in_array($permission, config('semizzy.role_permissions.'.$this->role, []), true);
    }
}
