<?php

namespace App\Models;

use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable implements MustVerifyEmail
{
    use HasApiTokens, HasFactory, Notifiable;

    protected $fillable = ['name', 'username', 'email', 'phone', 'avatar_path', 'address', 'city', 'state', 'country', 'postal_code', 'date_of_birth', 'gender', 'occupation', 'identity_type', 'identity_number', 'identity_document_path', 'kyc_status', 'kyc_submitted_at', 'kyc_reviewed_at', 'kyc_rejection_reason', 'password', 'role', 'status', 'tier', 'account_type', 'business_name', 'business_registration_number', 'business_type', 'business_address', 'business_state', 'business_country', 'merchant_verified_at', 'tier_upgrade_status', 'two_factor_enabled', 'two_factor_secret', 'transaction_pin_hash', 'security_lock_until', 'onboarding_completed_at', 'last_login_at', 'last_login_ip', 'referral_code', 'referred_by_id'];
    protected $hidden = ['password', 'remember_token'];

    protected static function booted(): void
    {
        static::creating(function (self $user): void {
            if (! filled($user->username)) {
                $local = Str::before((string) $user->email, '@');
                $base = Str::lower(preg_replace('/[^a-z0-9]+/i', '', $local) ?: 'user');
                $base = substr($base, 0, 30);
                $candidate = $base;
                $suffix = 1;

                while (DB::table('users')->where('username', $candidate)->exists()) {
                    $suffixText = (string) $suffix++;
                    $candidate = substr($base, 0, max(1, 30 - strlen($suffixText))) . $suffixText;
                }

                $user->username = $candidate;
            }

            if (! filled($user->referral_code)) {
                do {
                    $candidate = 'SEM' . strtoupper(Str::random(8));
                } while (DB::table('users')->where('referral_code', $candidate)->exists());

                $user->referral_code = $candidate;
            }
        });
    }

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
            'date_of_birth' => 'date',
            'kyc_submitted_at' => 'datetime',
            'kyc_reviewed_at' => 'datetime',
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
    public function currentTier(): array { $tier = max(1, min(5, (int) $this->tier)); return config('semizzy.user_tiers.'.$tier, config('semizzy.user_tiers.1')); }
    public function securityEvents(): HasMany { return $this->hasMany(SecurityEvent::class); }
    public function referrals(): HasMany { return $this->hasMany(self::class, 'referred_by_id'); }

    public function permissionOverrides(): HasMany
    {
        return $this->hasMany(UserPermissionOverride::class);
    }

    public function hasPermission(string $permission): bool
    {
        // Addon permissions are authoritative only while the owning addon is
        // active. This prevents stale/removed addon permissions from granting
        // access to Core routes and keeps addon authorization isolated.
        $declaredByAddon = Addon::query()
            ->where('status', 'active')
            ->whereJsonContains('permissions', $permission)
            ->exists();

        if (!$declaredByAddon && str_contains($permission, '.')) {
            $knownAddonPermission = Addon::query()
                ->whereJsonContains('permissions', $permission)
                ->exists();

            if ($knownAddonPermission) {
                return false;
            }
        }

        $override = $this->permissionOverrides()->where('permission', $permission)->value('allowed');

        if ($override !== null) {
            return (bool) $override;
        }

        $addonRolePermissions = Addon::query()
            ->where('status', 'active')
            ->whereJsonContains('permissions', $permission)
            ->get(['manifest'])
            ->contains(function (Addon $addon): bool {
                $rolePermissions = data_get($addon->manifest, 'role_permissions.'.$this->role, []);
                return is_array($rolePermissions) && in_array(
                    $permission,
                    $rolePermissions,
                    true
                );
            });

        if ($addonRolePermissions) {
            return true;
        }

        return in_array($permission, config('semizzy.role_permissions.'.$this->role, []), true);
    }
}
