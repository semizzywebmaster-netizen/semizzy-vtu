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

    protected $fillable = ['name', 'username', 'email', 'phone', 'password', 'role', 'status', 'tier', 'referral_code', 'referred_by_id'];
    protected $hidden = ['password', 'remember_token'];

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'phone_verified_at' => 'datetime',
            'password' => 'hashed',
        ];
    }

    public function isAdmin(): bool { return $this->role === 'ADMIN'; }
    public function hasRole(string|array $roles): bool { return in_array($this->role, (array) $roles, true); }

    public function devices(): HasMany { return $this->hasMany(UserDevice::class); }
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
